<?php
namespace Poznavacky\Controllers\Menu\Management\ClassObject;

use Poznavacky\Controllers\SynchronousController;
use Poznavacky\Models\DatabaseItems\Group;
use Poznavacky\Models\DatabaseItems\Natural;
use Poznavacky\Models\DatabaseItems\Part;
use Poznavacky\Models\Exceptions\AccessDeniedException;
use Poznavacky\Models\Exceptions\DatabaseException;
use Poznavacky\Models\Statics\UserManager;
use Poznavacky\Models\Logger;

/**
 * Kontroler starající se o stránku umožňující úpravu poznávaček pro administrátory tříd
 * @author Jan Štěch
 */
class EditController extends SynchronousController
{
    
    /**
     * Metoda nastavující hlavičku stránky a pohled k zobrazení
     * @param array $parameters Parametry pro zpracování kontrolerem (nevyužíváno)
     * @throws DatabaseException
     * @throws AccessDeniedException Pokud není přihlhášen žádný uživatel
     * @see SynchronousController::process()
     */
    public function process(array $parameters): void
    {
        (new Logger())->info('Přístup na stránku pro úpravu poznávačky s ID {groupId} patřící do třídy s ID {classId} uživatelem s ID {userId} z IP adresy {ip}',
            array(
                'groupId' => $_SESSION['selection']['group']->getId(),
                'classId' => $_SESSION['selection']['class']->getId(),
                'userId' => UserManager::getId(),
                'ip' => $_SERVER['REMOTE_ADDR']
            ));
        
        self::$pageHeader['title'] = 'Upravit poznávačku';
        self::$pageHeader['description'] = 'Nástroj pro vlastníky tříd umožňující snadnou úpravu poznávaček.';
        self::$pageHeader['keywords'] = '';
        self::$pageHeader['cssFiles'] = array('css/menu.css');
        self::$pageHeader['jsFiles'] = array('js/generic.js', 'js/menu.js', 'js/ajaxMediator.js', 'js/edit.js');
        self::$pageHeader['bodyId'] = 'edit-group';
        
        //Metoda získání URL poznávaček a jmen přírodnin napsaná podle jednoho komentáře pod touto odpovědí na StackOverflow: https://stackoverflow.com/a/1119029/14011077
        self::$data['groupList'] = array_map(function (Group $group): string
        {
            return $group->getUrl();
        }, $_SESSION['selection']['class']->getGroups());
        self::$data['naturalList'] = array_map(function (Natural $natural): string
        {
            return mb_strtolower($natural->getName());
        }, $_SESSION['selection']['class']->getNaturals());
        self::$data['groupName'] = $_SESSION['selection']['group']->getName();
        self::$data['groupUrl'] = $_SESSION['selection']['group']->getUrl();
        //Seznam objektu částí vrať ořezaný od všech nepotřebných údajů
        //Kvůli rekurzi (části odkazují na poznávačky, ty na třídu a vše funguje i zpětně) by se muselo proti XSS ošetřovat ohromné množství proměnných
        self::$data['parts'] = array_map(function (Part $part): Part
        {
            $strippedPart = new Part(false);
            $strippedNaturals = array_map(function (Natural $natural)
            {
                $strippedNatural = new Natural(false);
                $strippedNatural->initialize($natural->getName()); //Potřebujeme jenom název přírodniny
                return $strippedNatural;
            }, $part->getNaturals());
            $strippedPart->initialize($part->getName(), $part->getUrl(), null,
                $strippedNaturals);   //Potřebujeme jenom název a URL části a seznam přírodnin
            return $strippedPart;
        }, $_SESSION['selection']['group']->getParts());
    }
}

