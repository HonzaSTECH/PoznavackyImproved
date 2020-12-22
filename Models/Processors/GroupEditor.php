<?php
namespace Poznavacky\Models\Processors;

use Poznavacky\Models\DatabaseItems\Folder;
use Poznavacky\Models\DatabaseItems\Group;
use Poznavacky\Models\DatabaseItems\Natural;
use Poznavacky\Models\DatabaseItems\Part;
use Poznavacky\Models\Exceptions\AccessDeniedException;
use Poznavacky\Models\Security\DataValidator;
use Poznavacky\Models\Statics\Db;
use \InvalidArgumentException;
use \RangeException;

/**
 * Model zpracovávající změny poznávačky zadané uživatelem na edit stránce
 * @author Jan Štěch
 */
class GroupEditor
{
    private $group;
    private $partsToSave;
    
    /**
     * Konstruktor nastavující objekt poznávačky, která může být tímto objektem modifikována, jako vlastnost
     * @param Group $group Objekt poznávačky, kterou bude možné tímto objektem upravit
     */
    public function __construct(Group $group)
    {
        $this->group = $group;
        $this->group->getId(); //Vynuť načtení ID potřebného pro volání některých metod - po změně vlastností již nepůjde nalézt v databázi
    }
    
    /**
     * Metoda kontrolující nové jméno na délku, znaky a unikátnost a přejmenovávající ji
     * Změna není trvale uložena do databáze, pro to je potřeba zavolat metodu GroupEditor::commit()
     * @param string $newName Nový název třídy (nemusí být ošetřen)
     * @throws AccessDeniedException Pokud název poznávačky není unikátní, nevyhovuje jeho délka nebo obsahuje nepovolené znaky
     */
    public function rename(string $newName): void
    {
        $validator = new DataValidator();
        
        //Ověř délku a znaky názvu poznávačky
        try
        {
            $validator->checkLength($newName, DataValidator::GROUP_NAME_MIN_LENGTH, DataValidator::GROUP_NAME_MAX_LENGTH, DataValidator::TYPE_GROUP_NAME);
            $validator->checkCharacters($newName, DataValidator::GROUP_NAME_ALLOWED_CHARS, DataValidator::TYPE_GROUP_NAME);
        }
        catch (RangeException $e)
        {
            switch ($e->getMessage())
            {
                case 'short':
                    throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_GROUP_NAME_TOO_SHORT, null, $e);
                    break;
                case 'long':
                    throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_GROUP_NAME_TOO_LONG, null, $e);
                    break;
            }
        }
        catch (InvalidArgumentException $e)
        {
            throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_GROUP_NAME_INVALID_CHARACTERS, null, $e);
        }
        
        //Ověř unikátnost názvu - toto nelze udělat pomocí třídy DataValidator, protože je možné, že poznávačka nebyla přejmenována a název tak již existuje a přitom je platný
        //Musí být proto porovnáno ID u záznamů se shodnou URL adresou
        $result = Db::fetchQuery('SELECT '.Group::COLUMN_DICTIONARY['id'].' FROM '.Group::TABLE_NAME.' WHERE '.Group::COLUMN_DICTIONARY['url'].' = ? AND '.Group::COLUMN_DICTIONARY['class'].' = ? LIMIT 2', array($this->group->getUrl(), $this->group->getClass()->getId()), false);
        //if ($result === false) { /* Žádná poznávačka se stejným URL nebyla ve třídě nalezena - platné přejmenování */ }
        //if ($result[Group::COLUMN_DICTIONARY['id']] === $this->group->getId()) { /* Nalezena poznávačka se stejným URL i ID - poznávačka nebyla přejmenována */ }
        if ($result[Group::COLUMN_DICTIONARY['id']] !== $this->group->getId())
        {
            //Nalezena poznávačka se stejným URL a rozdílným ID - přejmenování na název příliš podobný jiné poznávačce
            throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_DUPLICATE_GROUP);
        }
        
        $this->group->rename($newName);
    }
    
    /**
     * Metoda tvořící z pole obecných objektů pole částí s objekty přírodnin a ukládající ho jako vlastnost tohoto objektu
     * Přírodniny jsou spojeny s jejich ekvivalenty v databázi (podle názvu necitlivého na velká písmena) nebo jsou vytvořeny nové objekty, které však zatím nejsou uloženy do databáze
     * Názvy přírodnin i částí jsou touto metodou ošetřeny
     * @param array $partsArray Pole obecných objektů definující vždy název části a seznam názvů přírodnin, které do ní mají být přidány
     * @throws AccessDeniedException Pokud se v některé části vyskytuje tatáž přírodnina vícekrát nebo pokud některý z názvů nesplňuje podmínky v oblasti délky, znaků nebo unikátnosti
     */
    public function unpackParts(array $partsArray): void
    {
        $partsObjects = array();
        $partUrls = array();
        $validator = new DataValidator();
        foreach ($partsArray as $partData)
        {
            $naturalNamesArray = array();           //Názvy přírodnin, tak jak byla zadána uživatelem
            $naturalNamesUppercaseArray = array();  //Názvy přírodnin převedená do velkých písmen
            $availableNaturals = array();           //Objekty přírodnin, která již v databázi existují a které názvem odpovídají některým přírodninám v poli $naturalNamesArray
            $naturals = array();                    //Objekty přírodnin pro uložení do poznávačky
            
            //Získej seznam jmen přírodnin v části
            foreach ($partData->naturals as $naturalName)
            {
                //Kontrola, zda již přírodnina v této části neexistuje
                if (in_array(mb_strtoupper($naturalName), $naturalNamesUppercaseArray))
                {
                    throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_DUPLICATE_NATURAL);
                }
                $naturalNamesArray[] = $naturalName;
                $naturalNamesUppercaseArray[] = mb_strtoupper($naturalName);
            }
            if (count($naturalNamesArray) > 0) //Proveď tuhle šaškárnu pouze pokud uživatel přidal do části nějaké přírodniny
            {
                //Získej data všech přírodnin v části podle názvu (nezávisle na velikosti písmen)
                $inQuestionmarks = rtrim(str_repeat('?,', count($naturalNamesUppercaseArray)), ',');
                $result = Db::fetchQuery('SELECT '.Natural::COLUMN_DICTIONARY['id'].', '.Natural::COLUMN_DICTIONARY['name'].', '.Natural::COLUMN_DICTIONARY['picturesCount'].' FROM '.Natural::TABLE_NAME.' WHERE '.Natural::COLUMN_DICTIONARY['class'].' = ? AND UPPER('.Natural::COLUMN_DICTIONARY['name'].') IN ('.$inQuestionmarks.')', array_merge(array($this->group->getClass()->getId()), $naturalNamesUppercaseArray), true);
                if ($result === false) { $result = array(); } //Žádné existující přírodniny nenalezeny
                
                //Přepiš získané výsledky do asociativního pole, kde klíčem bude název přírodniny velkými písmeny
                foreach ($result as $naturalData)
                {
                    $natural = new Natural(false, $naturalData[Natural::COLUMN_DICTIONARY['id']]);
                    $natural->initialize($naturalData[Natural::COLUMN_DICTIONARY['name']], null, $naturalData[Natural::COLUMN_DICTIONARY['picturesCount']], $this->group->getClass());
                    $availableNaturals[mb_strtoupper($naturalData[Natural::COLUMN_DICTIONARY['name']])] = $natural;
                }
                
                //Poskládej pole přírodnin pro uložení do části
                foreach ($naturalNamesArray as $naturalName)
                {
                    if (isset($availableNaturals[mb_strtoupper($naturalName)]))
                    {
                        //Existující přírodnina
                        $naturals[] = $availableNaturals[mb_strtoupper($naturalName)];
                    }
                    else
                    {
                        //Nová přírodnina
    					//Zkontroluj, zda je název přírodniny platný
                        try
                        {
        					$validator->checkLength($naturalName, DataValidator::NATURAL_NAME_MIN_LENGTH, DataValidator::NATURAL_NAME_MAX_LENGTH, DataValidator::TYPE_NATURAL_NAME);
                            $validator->checkCharacters($naturalName, DataValidator::NATURAL_NAME_ALLOWED_CHARS, DataValidator::TYPE_NATURAL_NAME);
                        }
                        catch (RangeException $e)
                        {
                            switch ($e->getMessage())
                            {
                                case 'short':
                                    throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_NATURAL_NAME_TOO_SHORT, null, $e);
                                    break;
                                case 'long':
                                    throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_NATURAL_NAME_TOO_LONG, null, $e);
                                    break;
                            }
                        }
                        catch (InvalidArgumentException $e)
                        {
                            throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_NATURAL_NAME_INVALID_CHARACTERS, null, $e);
                        }
                        
    					$natural = new Natural(true);
                        $natural->initialize($naturalName, null, null, $this->group->getClass());
                        $naturals[] = $natural;
                    }
                }
            }
            
			//Zkontroluj, zda je název části platný
            $partName = $partData->name;
            try
            {
                $validator->checkLength($partName, DataValidator::PART_NAME_MIN_LENGTH, DataValidator::PART_NAME_MAX_LENGTH, DataValidator::TYPE_PART_NAME);
                $validator->checkCharacters($partName, DataValidator::PART_NAME_ALLOWED_CHARS, DataValidator::TYPE_PART_NAME);
            }
            catch (RangeException $e)
            {
                switch ($e->getMessage())
                {
                    case 'short':
                        throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_PART_NAME_TOO_SHORT, null, $e);
                        break;
                    case 'long':
                        throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_PART_NAME_TOO_LONG, null, $e);
                        break;
                }
            }
            catch (InvalidArgumentException $e)
            {
                throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_PART_NAME_INVALID_CHARACTERS, null, $e);
            }
            
            //Zkontroluj unikátnost (pouze vůči již rozbaleným částem - všechny části uložené v databázi budou smazány a nahrazeny při potvrzování změn)
            $partUrl = Folder::generateUrl($partName);
            if (in_array($partUrl, $partUrls))
            {
                throw new AccessDeniedException(AccessDeniedException::REASON_MANAGEMENT_EDIT_GROUP_DUPLICATE_PART);
            }
            $partUrls[] = $partUrl;
            
            $part = new Part(true);
            $part->initialize($partName, Folder::generateUrl($partData->name), $this->group, $naturals, count($naturals));
            $partsObjects[] = $part;
        }
        $this->partsToSave = $partsObjects;
    }
    
    /**
     * Metoda trvale ukládající všechny změny provedené v poznávačce, kterou tento objekt upravuje
     */
    public function commit(): void
    {
        //Ulož nové jméno poznávačky
        $this->group->save();
        
        //Nahraď části v databázi i v objektu uloženém v $_SESSION['selection']['group']
        $this->group->replaceParts($this->partsToSave);
        
        //Ulož všechny nové přírodniny do databáze a spoj je s částmi
        foreach ($this->partsToSave as $part)
        {
            foreach ($part->getNaturals() as $natural)
            {
                if ($natural->isNew()) { $natural->save(); }
                Db::executeQuery('INSERT INTO prirodniny_casti (prirodniny_id,casti_id) VALUES (?,?)', array($natural->getId(), $part->getId())); //Tohle se provede pro každou přírodninu, ale asi se nedá nic dělat
            }
            //Aktualizuj počet obrázků u části
            Db::executeQuery('UPDATE casti SET obrazky = (SELECT SUM(prirodniny.obrazky) FROM prirodniny WHERE prirodniny.prirodniny_id IN (SELECT prirodniny_casti.prirodniny_id FROM prirodniny_casti WHERE prirodniny_casti.casti_id = ?)) WHERE casti.casti_id = ?', array($part->getId(), $part->getId()));
        }        
    }
}

