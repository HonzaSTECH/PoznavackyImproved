<?php
namespace Poznavacky\Models;

use Poznavacky\Models\DatabaseItems\LoggedUser;
use Poznavacky\Models\DatabaseItems\User;
use Poznavacky\Models\Exceptions\AccessDeniedException;
use Poznavacky\Models\Exceptions\DatabaseException;
use Poznavacky\Models\Security\DataValidator;
use Poznavacky\Models\Statics\Db;
use \InvalidArgumentException;
use \RangeException;
use \RuntimeException;

/**
 * Třída umožňující změnu hesla uživatele po odeslání formuláře na recover-password stránce
 * @author Jan Štěch
 */
class TokenPasswordChanger
{
    private string $token;
    private string $pass;
    private string $repass;
    private int $userId;
    private bool $verified = false;
    private bool $checked = false;
    
    /**
     * Konstruktor nastavující privátní vlastnosti objektu které jsou později ověřovány
     * @param string $token Kód pro obnovu hesla odeslaný z formuláře na password-recovery stránce
     * @param string $pass Heslo odeslané z formuláře
     * @param string $repass Heslo znovu odeslané z formuláře
     */
    public function __construct(string $token, string $pass, string $repass)
    {
        $this->token = $token;
        $this->pass = $pass;
        $this->repass = $repass;
    }
    
    /**
     * Metoda ověřující platnost kódu pro obnovení hesla a ukládající ID uživatele s jehož účtem je svázán
     * @throws AccessDeniedException Pokud není kód v databázi nalezen
     * @throws DatabaseException Pokud se při práci s databází vyskytne chyba
     */
    public function verifyToken(): void
    {
        $codeVerificator = new PasswordRecoveryCodeVerificator();
        $this->userId = $codeVerificator->verifyCode($this->token);
        if (empty($this->userId)) {
            (new Logger())->warning('Uživatel s přístupem na stránku pro obnovení hesla se pokusil změnit heslo z IP adresy {ip}, avšak jeho kód (hash {hash}) je neplatný',
                array('ip' => $_SERVER['REMOTE_ADDR'], 'hash' => md5($this->token)));
            throw new AccessDeniedException(AccessDeniedException::REASON_RECOVER_INVALID_TOKEN);
        }
        $this->verified = true;
    }
    
    /**
     * Metoda odstraňující použitý kód pro obnovu hesla z databáze
     * @throws DatabaseException Pokud se při práci s databází vyskytne chyba
     */
    public function devalueToken(): void
    {
        $codeVerificator = new PasswordRecoveryCodeVerificator();
        $codeVerificator->deleteCode($this->token);
        (new Logger())->info('Použitý kód pro obnovu hesla (hash {hash}) byl odstraněn z databáze',
            array('hash' => md5($this->token)));
    }
    
    /**
     * Metoda ověřující, zda je možné zadané heslo použít
     * @throws AccessDeniedException Pokud se hesla neshodují
     */
    function checkPasswords(): void
    {
        $validator = new DataValidator();
        try {
            $validator->checkLength($this->pass, DataValidator::USER_PASSWORD_MIN_LENGTH,
                DataValidator::USER_PASSWORD_MAX_LENGTH, DataValidator::TYPE_USER_PASSWORD);
            $validator->checkCharacters($this->pass, DataValidator::USER_PASSWORD_ALLOWED_CHARS,
                DataValidator::TYPE_USER_PASSWORD);
        } catch (RangeException $e) {
            if ($e->getMessage() === 'long') {
                (new Logger())->notice('Pokus o změnu hesla pomocí kódu pro obnovení (hash {hash}) k uživatelskému účtu s ID {userId} z IP adresy {ip} selhal kvůli nepřijatelné délce hesla',
                    array('hash' => md5($this->token), 'userId' => $this->userId, 'ip' => $_SERVER['REMOTE_ADDR']));
                throw new AccessDeniedException(AccessDeniedException::REASON_REGISTER_PASSWORD_TOO_LONG, null, null);
            } else {
                if ($e->getMessage() === 'short') {
                    (new Logger())->notice('Pokus o změnu hesla pomocí kódu pro obnovení (hash {hash}) k uživatelskému účtu s ID {userId} z IP adresy {ip} selhal kvůli nepřijatelné délce hesla',
                        array('hash' => md5($this->token), 'userId' => $this->userId, 'ip' => $_SERVER['REMOTE_ADDR']));
                    throw new AccessDeniedException(AccessDeniedException::REASON_REGISTER_PASSWORD_TOO_SHORT, null,
                        null);
                }
            }
        } catch (InvalidArgumentException $e) {
            (new Logger())->notice('Pokus o změnu hesla pomocí kódu pro obnovení (hash {hash}) k uživatelskému účtu s ID {id} z IP adresy {ip} selhal kvůli přitomnosti nepovolených znaků',
                array('hash' => md5($this->token), 'userId' => $this->userId, 'ip' => $_SERVER['REMOTE_ADDR']));
            throw new AccessDeniedException(AccessDeniedException::REASON_REGISTER_PASSWORD_INVALID_CHARACTERS, null,
                null);
        }
        
        if ($this->pass !== $this->repass) {
            (new Logger())->notice('Pokus o změnu hesla pomocí kódu pro obnovení (hash {hash}) z IP adresy {ip} selhal kvůli neshodě mezi zadanými hesly',
                array('hash' => md5($this->token), 'ip' => $_SERVER['REMOTE_ADDR']));
            throw new AccessDeniedException(AccessDeniedException::REASON_REGISTER_DIFFERENT_PASSWORDS, null, null);
        }
        $this->checked = true;
    }
    
    /**
     * Metoda měnící heslo uživatele
     * @return boolean TRUE, pokud je heslo úspěšně změněno
     * @throws DatabaseException Pokud se při práci s databází vyskytne chyba
     * @throws RuntimeException Pokud zatím nebyl ověřen kód pro obnovu hesla nebo platnost hesel
     */
    function changePassword(): bool
    {
        if (!($this->verified && $this->checked)) {
            throw new RuntimeException('Zatím nebyl ověřen kód pro obnovu hesla nebo platnost hesel. Pokud toto čtete, kontaktujte prosím správce');
        }
        Db::executeQuery('UPDATE '.User::TABLE_NAME.' SET '.LoggedUser::COLUMN_DICTIONARY['hash'].' = ? WHERE '.
                         LoggedUser::COLUMN_DICTIONARY['id'].' = ?',
            array(password_hash($this->pass, PASSWORD_DEFAULT), $this->userId));
        (new Logger())->info('Pomocí kódu pro obnovení hesla (hash {hash}) bylo z IP adresy {ip} změněno heslo k uživatelskému účtu s ID {userId}',
            array('ip' => $_SERVER['REMOTE_ADDR'], 'hash' => md5($this->token), 'userId' => $this->userId));
        return true;
    }
}

