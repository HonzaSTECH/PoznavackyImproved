<?php
namespace Poznavacky\Controllers;

use BadMethodCallException;
use ErrorException;
use Poznavacky\Models\Logger;
use Poznavacky\Models\Security\AccessChecker;
use Poznavacky\Models\Security\AntiXssSanitizer;
use Poznavacky\Models\Statics\UserManager;

/**
 * Třída směrovače přesměrovávající uživatele z index.php na správný kontroler
 * @author Jan Štěch
 */
class RooterController extends SynchronousController
{

    private const ROUTES_INI_FILE = 'routes.ini';
    private const INI_ARRAY_SEPARATOR = ',';
    //Význam následujících tří konstant je blíže popsán v souboru routes.ini
    private const CLEAR_SELECTION_PREFIX = '$';
    private const IGNORE_SELECTION_VALUE = 'ignore';
    private const NON_SELECTION_VALUE = 'skip';

    /**
     * Metoda zpracovávající zadanou URL adresu a přesměrovávající uživatele na zvolený kontroler
     * @param array $parameters Pole parametrů, na indexu 0 musí být nezpracovaná URL adresa
     */
    public function process(array $parameters): void
    {
        $url = $parameters[0];
        $parsedURL = parse_url($url)['path'];               // Z http(s)://domena.net/abc/def/ghi získá /abc/def/ghi
        $parsedURL = trim($parsedURL, '/');        // Odstranění prvního (a existuje i posledního) lomítka
        $parsedURL = trim($parsedURL);                      // Odstranění mezer na začátku a na konci
        $urlArguments = explode('/', $parsedURL);  // Rozbití řetězce do pole podle lomítek

        if ($urlArguments[0] === '') { $urlArguments = array(); }

        $controllerName = null;

        $iniRoutes = parse_ini_file(self::ROUTES_INI_FILE, true);

        //Rozlišení proměnných a neproměnných URL parametrů
        $controllersUrls = array_keys($iniRoutes['Controllers']);
        $urlVariablesArr = array_diff($urlArguments, $controllersUrls); //Získá pole jednotlivých proměnných URL parametrů
        $urlVariablesPositions = array_keys($urlVariablesArr);
        $urlVariablesValues = array_values($urlVariablesArr);
        for ($i = 0, $j = 0; $i < count($urlArguments); $i++)
        {
            if (in_array($i, $urlVariablesPositions))
            {
                $urlArguments[$i] = '<'.$j.'>';
                $j++; //Číslo proměnné
            }
        }

        //$urlArgumenty nyní obsahuje pole URL parametrů, kde jsou proměnné parametry nahrazeny značkami <x>

        //Nalezení kontroleru k použití
        $path = '/'.implode('/',$urlArguments);
        $controllerName = @$iniRoutes['Routes'][$path];
        if (empty($controllerName))
        {
            //Cesta nenalezena
            header('HTTP/1.0 404 Not Found');
            exit();
        }
        $pathToController = $this->classExists($controllerName.self::CONTROLLER_EXTENSION, self::CONTROLLER_FOLDER);
        $this->controllerToCall = new $pathToController;

        //Získání seznamu nastavní složek
        $selections = explode(self::INI_ARRAY_SEPARATOR, @$iniRoutes['Selections'][$path]);
        if (empty($selections[0])) { $selections = array(); }

        //Získání seznamu kontrol, které musí být provedeny
        $shortPath = ''; //Cesta začínající posledním neproměnným kontrolerem
        for ($i = count($urlArguments) - 1; $i >= 0 && !in_array($urlArguments[$i], $controllersUrls); $i--)
        {
            $shortPath = '/'.$urlArguments[$i].$shortPath;
        }
        if ($i >= 0) { $shortPath = '/'.$urlArguments[$i].$shortPath; }
        else { $shortPath = $path; }

        $checks = explode(self::INI_ARRAY_SEPARATOR, @$iniRoutes['Checks'][$shortPath]);
        if (empty($checks[0])) { $checks = array(); }

        //Získání seznamu pohledu pro použití
        self::$views = explode(self::INI_ARRAY_SEPARATOR, @$iniRoutes['Views'][$shortPath]);
        if (empty(self::$views[0])) { self::$views = array(); }

        //Získání seznamu získávačů dat pro pohledy
        $dataGetters = array();
        foreach (self::$views as $view)
        {
            $dataGetterName = @$iniRoutes['DataGetters'][$view];
            if (empty($dataGetterName)) { continue; }
            $pathToDataGetter = $this->classExists($dataGetterName.self::DATA_GETTER_EXTENSION, self::DATA_GETTER_FOLDER);
            $dataGetter = new $pathToDataGetter;
            $dataGetters[] = $dataGetter;
        }

        /* Všechny potřebné informace z routes.ini získány */

        //Nastavení složek
        if (!$this->setFolders($selections, $urlVariablesValues)) //Předávání podle reference zajistí, že se spotřebované parametry odeberou
        {
            header('HTTP/1.0 404 Not Found');
            exit();
        }

        //Provedení kontrol
        if (!$this->runChecks($checks))
        {
            header('HTTP/1.0 403 Forbidden');
            exit();
        }

        //Získání dat pro zobrazení v obalových pohledech
        foreach ($dataGetters as $dataGetter)
        {
            self::$data = array_merge(self::$data, $returnedData = $dataGetter->get());
        }

        //Získání dat pro zobrazení v hlavním pohledu
        $this->controllerToCall->process($urlVariablesValues);

        if ($this->controllerToCall instanceof SynchronousController)
        {
            //Synchroní kontroler - nastav hlavičky stránky a základní pohled
            self::$data['title'] = SynchronousController::$pageHeader['title'];
            self::$data['description'] = SynchronousController::$pageHeader['description'];
            self::$data['keywords'] = SynchronousController::$pageHeader['keywords'];
            self::$data['cssFiles'] = SynchronousController::$pageHeader['cssFiles'];
            self::$data['jsFiles'] = SynchronousController::$pageHeader['jsFiles'];
            self::$data['bodyId'] = SynchronousController::$pageHeader['bodyId'];
        }
      # else { /*AJAX kontroler - nezobrazuj žádný pohled*/ }
    }

    /**
     * Metoda pro nastavení objektu třídy, poznávačky nebo části do $_SESSON['selection']
     * Při přenastavení nějaké složky jsou z pole $_SESSON['selection'] vymazány všechny podčásti té stávající
     * @param array $selections Pole řetězců popisující význam a pořadí argumentů ve druhém argumentu, povolené hodnoty řetězců jsou 'class', 'group', 'part' a hodnoty konstant této třídy obsahující v názvu slovo 'SELECTOR'
     * @param array $urlVariablesValues Pole argumentů získaných z URL pro zpracování, pořadí prvků musí odpovídat hodnotám v prvním argumentu
     * @return bool TRUE, pokud se povedlo všechny složky nastavit, FALSE, pokud některá ze složek nebyla podle URL v databázi nalezena
     */
    private function setFolders(array $selections, array &$urlVariablesValues): bool
    {
        $aChecker = new AccessChecker();
        for ($i = 0; $i < count($selections); $i++)
        {
            $selection = $selections[$i];
            if ($selection[0] === self::CLEAR_SELECTION_PREFIX)
            {
                unset($_SESSION['selection'][substr($selection, 1)]);
                continue;
            }
            else if ($selection === self::NON_SELECTION_VALUE) { continue; }
            else if ($selection === self::IGNORE_SELECTION_VALUE)
            {
                array_shift($urlVariablesValues);
                continue;
            }
            $currentUrl = array_shift($urlVariablesValues);

            $folderClass = null;
            $alreadySet = false;
            switch ($selection)
            {
                case 'class':
                    $folderClass = 'Poznavacky\\Models\\DatabaseItems\\ClassObject';
                    $alreadySet = ($aChecker->checkClass() && $_SESSION['selection']['class']->getUrl() === $currentUrl);
                    break;
                case 'group':
                    $folderClass = 'Poznavacky\\Models\\DatabaseItems\\Group';
                    $alreadySet = ($aChecker->checkGroup() && $_SESSION['selection']['group']->getUrl() === $currentUrl);
                    break;
                case 'part':
                    $folderClass = 'Poznavacky\\Models\\DatabaseItems\\Part';
                    $alreadySet = ($aChecker->checkPart() && $_SESSION['selection']['part']->getUrl() === $currentUrl);
                    break;
            }

            //Kontrola, zda není složka se stejným URL již náhodou zvolena
            if ($alreadySet) { continue; }
            //Uložení objektu třídy/poznávačky/části do $_SESSION
            $folder = new $folderClass(false, 0);
            $folder->initialize(null, $currentUrl);
            try { $folder->load(); }
            catch (BadMethodCallException $e)
            {
                //Třída/poznávačka/část splňující daná kritéria neexistuje
                return false;
            }
            $_SESSION['selection'][$selection] = $folder;

            //Vymaž podsložky přepsané složky
            if ($selection === 'class')
            {
                unset($_SESSION['selection']['group']);
                unset($_SESSION['selection']['part']);
            }
            if ($selection === 'group'){ unset($_SESSION['selection']['part']); }
        }
        return true;
    }

    /**
     * Metoda provádějící všechny bezpečnostní kontroly, například zda je přihlášený uživatel správcem zvolené třídy
     * @param array $checks Pole řetězců popisujících kontroly, které se musejí provést, jejich význam je blíže popsán v souboru routes.ini
     * @return bool TRUE, pokud všechny kontroly proběhnou v pořádku, FALSE, pokud ne
     */
    private function runChecks(array $checks): bool
    {
        $aChecker = new AccessChecker();
        foreach ($checks as $check)
        {
            switch ($check)
            {
                case 'user':
                    if (!$aChecker->checkUser()) { return false; }
                    break;
                case 'systemAdmin':
                    if (!$aChecker->checkSystemAdmin()) { return false; }
                    break;
                case 'class':
                    if (!$aChecker->checkClass()) { return false; }
                    break;
                case 'classAccess':
                    if (!$_SESSION['selection']['class']->checkAccess(UserManager::getId(), true))
                    {
                        unset($_SESSION['selection']);
                        return false;
                    }
                    break;
                case 'classAdmin':
                    if (!$_SESSION['selection']['class']->checkAdmin(UserManager::getId())) { return false; }
                    break;
                case 'group':
                    if (!$aChecker->checkGroup()) { return false; }
                    break;
            }
        }
        return true;
    }

    /**
     * Metoda navracející cestu k dalšímu (vnitřenějšímu) nepoužitému pohledu
     * Tato metoda je volána z pohledů v místech, kde má být dynamicky vložen vnitřnější pohled
     * @return string Cesta k pohledu, která může být okamžitě použita v příkazu include nebo require
     */
    private function getNextView(): string
    {
        return self::VIEW_FOLDER.'/'.array_shift(self::$views).'.phtml';
    }

    /**
     * Metoda zahrnující pohled a vypysující do něj proměnné z vlastnosti $data
     * Pokud je vlastnost SynchronousController::$view prázdná, není vypsáno nic
     * @throws ErrorException Pokud selže ošetření dat proti XSS útoku
     */
    public function displayView(): void
    {
        if (!empty(self::$views))
        {
            //Vytvoř pole ošetřených hodnot
            $sanitized = $this->sanitizeData(self::$data);

            //Přejmenuj klíče v originálním poli neošetřených hodnot
            foreach (self::$data as $key => $value)
            {
                self::$data['_'.$key.'_'] = $value;
                unset(self::$data[$key]);
            }

            extract(self::$data);
            extract($sanitized);
            require self::VIEW_FOLDER.'/'.array_shift(self::$views).'.phtml';
        }
    }

    /**
     * Metoda ošetřující všechny hodnoty určené k využití pohledem proti XSS útoku
     * @param array $data Pole proměnných k ošetření
     * @return mixed Pole s ošetřenými hodnotami
     * @throws ErrorException Pokud se pokouším ošetřit nepodporovaný datový typ
     */
    private function sanitizeData(array $data): array
    {
        $sanitizer = new AntiXssSanitizer();
        foreach ($data as $key => $value)
        {
            $data[$key] = $sanitizer->sanitize($value);
        }
        return $data;
    }
}

