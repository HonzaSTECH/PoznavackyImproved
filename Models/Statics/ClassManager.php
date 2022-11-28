<?php

namespace Poznavacky\Models\Statics;

use Poznavacky\Models\Exceptions\DatabaseException;
use Poznavacky\Models\DatabaseItems\ClassObject;
use Poznavacky\Models\DatabaseItems\User;
use \DateTime;
use \Exception;

/**
 * Třída získávající informace o třídě z databáze, například za účelem pro získání ID z názvu a obráceně
 * Dále ověřuje zda třída nebo poznávačka do ní patřící existuje.
 * @author Jan Štěch
 */
class ClassManager
{
    /**
     * Metoda získávající z databáze seznam všech tříd, které splňují jisté podmínky, jako objekty
     * Podmínky, které musí splňovat:
     * 1) Jejich přístupový kód musí být stejný jako první argument této metody
     * 2) Její status musí být nastaven jako soukromý
     * 3) Uživatel, jehož ID je specifikováno jako druhý argument této metody nesmí být členem daných tříd
     * @param int $code Kód podle kterého vyhledáváme
     * @param int $userId ID uživatele, který se pokouší použít kód k získání přístupu do nových tříd
     * @return ClassObject[] Pole tříd, které splňují podmínky výše, jako objekty, nebo prázdné pole, pokud žádné
     *     takové třídy neexistují
     * @throws DatabaseException
     * @throws Exception Pokud se nepodaří vytvořit objekt DateTime
     */
    public static function getNewClassesByAccessCode(int $code, int $userId): array
    {
        $result = Db::fetchQuery('
        SELECT
        '.ClassObject::TABLE_NAME.'.'.ClassObject::COLUMN_DICTIONARY['id'].', '.ClassObject::TABLE_NAME.'.'.
            ClassObject::COLUMN_DICTIONARY['name'].', '.ClassObject::TABLE_NAME.'.'.
            ClassObject::COLUMN_DICTIONARY['url'].', '.ClassObject::TABLE_NAME.'.'.
            ClassObject::COLUMN_DICTIONARY['status'].' AS "c_status", '.ClassObject::TABLE_NAME.
            '.'.ClassObject::COLUMN_DICTIONARY['groupsCount'].', '.ClassObject::TABLE_NAME.'.'.
            ClassObject::COLUMN_DICTIONARY['code'].', '.ClassObject::TABLE_NAME.'.'.
            ClassObject::COLUMN_DICTIONARY['readonly'].',
        '.User::TABLE_NAME.'.'.User::COLUMN_DICTIONARY['id'].', '.User::TABLE_NAME.'.'.User::COLUMN_DICTIONARY['name'].
            ', '.User::TABLE_NAME.'.'.User::COLUMN_DICTIONARY['email'].', '.User::TABLE_NAME.'.'.
            User::COLUMN_DICTIONARY['lastLogin'].', '.User::TABLE_NAME.'.'.
            User::COLUMN_DICTIONARY['addedPictures'].', '.User::TABLE_NAME.'.'.
            User::COLUMN_DICTIONARY['guessedPictures'].', '.User::TABLE_NAME.'.'.
            User::COLUMN_DICTIONARY['karma'].', '.User::TABLE_NAME.'.'.
            User::COLUMN_DICTIONARY['status'].' AS "u_status"
        FROM '.ClassObject::TABLE_NAME.'
        JOIN '.User::TABLE_NAME.' ON '.ClassObject::COLUMN_DICTIONARY['admin'].' = '.User::COLUMN_DICTIONARY['id'].'
        WHERE '.ClassObject::COLUMN_DICTIONARY['code'].' = ? AND '.ClassObject::TABLE_NAME.'.'.
            ClassObject::COLUMN_DICTIONARY['status'].' = ? AND '.
            ClassObject::COLUMN_DICTIONARY['id'].' NOT IN
        (
            SELECT tridy_id FROM clenstvi WHERE uzivatele_id = ?
        )
        ', array($code, ClassObject::CLASS_STATUS_PRIVATE, $userId), true);

        //Kontrola, zda je navrácen alespoň jeden výsledek
        if (!$result) {
            return array();
        }

        $classes = array();
        foreach ($result as $classInfo) {
            $classAdmin = new User(false, $classInfo[User::COLUMN_DICTIONARY['id']]);
            $classAdmin->initialize($classInfo[User::COLUMN_DICTIONARY['name']],
                $classInfo[User::COLUMN_DICTIONARY['email']],
                new DateTime($classInfo[User::COLUMN_DICTIONARY['lastLogin']]),
                $classInfo[User::COLUMN_DICTIONARY['addedPictures']],
                $classInfo[User::COLUMN_DICTIONARY['guessedPictures']], $classInfo[User::COLUMN_DICTIONARY['karma']],
                $classInfo['u_status']);
            $class = new ClassObject(false, $classInfo[ClassObject::COLUMN_DICTIONARY['id']]);
            $class->initialize($classInfo[ClassObject::COLUMN_DICTIONARY['name']],
                $classInfo[ClassObject::COLUMN_DICTIONARY['url']], $classInfo['c_status'],
                $classInfo[ClassObject::COLUMN_DICTIONARY['code']],
                $classInfo[ClassObject::COLUMN_DICTIONARY['readonly']], null,
                $classInfo[ClassObject::COLUMN_DICTIONARY['groupsCount']], null, $classAdmin);
            $classes[] = $class;
        }

        return $classes;
    }
}

