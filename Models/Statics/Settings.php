<?php

namespace Poznavacky\Models\Statics;

/**
 * Třída obsahující všechna technická nastavení pro aplikaci jako statické konstanty
 * @author Jan Štěch
 */
class Settings
{
    /**
     * Rozhoduje, zda má aplikace běžet v produkčním nebo vývojovém režimu.
     * Pokud je toto nastavení nastaveno na FALSE, množství funkcí, které na lokálním serveru nefungují
     * nebo působí problémy, jako vynucené SSL spojení, je vypnuto
     */
    public const PRODUCTION_ENVIRONMENT = false;

    /**
     * Aktuální verze aplikace
     * Při vydání nové verze je nutné nezapomenout přidat ID nového GitHub release do konstanty ChangelogManager::RELEASE_IDS
     */
    public const VERSION = '4.1.2';

    /**
     * Server hostující MySQL databázi pro aplikaci
     */
    public const DB_HOST = 'localhost';

    /**
     * Uživatelské jméno pro přístup do databáze aplikace
     */
    public const DB_USERNAME = 'root';

    /**
     * Heslo pro přístup do databáze aplikace
     */
    public const DB_PASSWORD = '';

    /**
     * Jméno databáze pro aplikaci
     */
    public const DB_NAME = 'poznavacky';

    /**
     * Pokud je aplikace ve vývojovém režimu, všechny odchozí e-maily jsou odesílány na tuto e-mailovou adresu.
     * Toto zamezí spamování skutečných uživatelů
     */
    public const DEVELOPMENT_EMAIL_COLLECTOR = 'dummy@poznavacky.com';

    /**
     * Server pro odchozí e-maily
     */
    public const SMTP_HOST = 'smtp.poznavacky.com';

    /**
     * Port pro odchozí e-maily
     */
    public const SMTP_PORT = '587';

    /**
     * Uživatelské jméno pro odesílání e-mailů
     */
    public const EMAIL_USERNAME = 'info@poznavacky.com';

    /**
     * Heslo k účtu pro odesílání e-mailů
     */
    public const EMAIL_PASSWORD = 'SECRET';

    /**
     * Počet URL adres k obrázkům pro testovací stránku odeslaný v jednom požadavku
     */
    public const TEST_PICTURES_SENT_PER_REQUEST = 20;

    /**
     * Maximální povolený poměr (špatné znaky / všechny znaky), aby byla odpověď na testovou otázku uznána
     */
    public const ANSWER_TOLERANCE = 0.34;

}

