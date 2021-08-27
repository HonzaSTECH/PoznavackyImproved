<?php
namespace Poznavacky\Models\DatabaseItems;

use Poznavacky\Models\Exceptions\DatabaseException;
use Poznavacky\Models\Statics\Db;
use Poznavacky\Models\undefined;

/**
 * Třída reprezentující objekt poznávačky obsahující části
 * @author Jan Štěch
 */
class Group extends Folder
{
    public const TABLE_NAME = 'poznavacky';
    
    public const COLUMN_DICTIONARY = array(
        'id' => 'poznavacky_id',
        'name' => 'nazev',
        'url' => 'url',
        'class' => 'tridy_id',
        'partsCount' => 'casti'
    );
    
    protected const NON_PRIMITIVE_PROPERTIES = array(
        'class' => ClassObject::class
    );
    
    protected const DEFAULT_VALUES = array(
        'partsCount' => 0,
        'parts' => array()
    );
    
    protected const CAN_BE_CREATED = true;
    protected const CAN_BE_UPDATED = true;
    
    protected $class;
    protected $partsCount;
    
    protected $parts;
    protected $naturals;
    
    /**
     * Metoda nastavující všechny vlasnosti objektu (s výjimkou ID) podle zadaných argumentů
     * Při nastavení některého z argumentů na undefined, je hodnota dané vlastnosti také nastavena na undefined
     * Při nastavení některého z argumentů na null, není hodnota dané vlastnosti nijak pozměněna
     * @param string|undefined|null $name Název této poznávačky
     * @param string|undefined|null $url Reprezentace názvu poznávačky pro použití v URL
     * @param ClassObject|undefined|null $class Odkaz na objekt třídy, do které tato poznávačka patří
     * @param Part[]|undefined|null $parts Pole částí, jako objekty, na které je tato poznávačka rozdělená
     * @param int|undefined|null Počet částí, do kterých je tato poznávačka rozdělena (při vyplnění parametru $parts je
     *     ignorováno a je použita délka poskytnutého pole)
     * {@inheritDoc}
     * @see DatabaseItem::initialize()
     */
    public function initialize($name = null, $url = null, $class = null, $parts = null, $partsCount = null): void
    {
        //Kontrola nespecifikovaných hodnot (pro zamezení přepsání známých hodnot)
        if ($name === null) {
            $name = $this->name;
        }
        if ($url === null) {
            $url = $this->url;
        }
        if ($class === null) {
            $class = $this->class;
        }
        if ($parts === null) {
            $parts = $this->parts;
            if ($partsCount === null) {
                $partsCount = $this->partsCount;
            }
        } else {
            $partsCount = count($parts);
        }
        
        $this->name = $name;
        $this->url = $url;
        $this->class = $class;
        $this->parts = $parts;
        $this->partsCount = $partsCount;
    }
    
    /**
     * Metoda navracející ID třídy, do které tato poznávačka patří
     * @return ClassObject objekt třídy
     * @throws DatabaseException
     */
    public function getClass(): ClassObject
    {
        $this->loadIfNotLoaded($this->class);
        return $this->class;
    }
    
    /**
     * Metoda navracející počet částí v této poznávačce
     * @return int Počet částí poznávačky
     * @throws DatabaseException
     */
    public function getPartsCount(): int
    {
        $this->loadIfNotLoaded($this->partsCount);
        return $this->partsCount;
    }
    
    /**
     * Metoda navracející počet obrázků ve všech částech této poznávačky
     * @return int Počet obrázků v poznávačce
     */
    public function getPicturesCount(): int
    {
        $naturals = $this->getNaturals();
        $sum = 0;
        foreach ($naturals as $natural) {
            $sum += $natural->getPicturesCount();
        }
        return $sum;
    }
    
    /**
     * Metoda navracející pole náhodně zvolených obrázků z nějaké části této poznávačky jako objekty
     * Šance na výběr části je přímo úměrná počtu přírodnin, které obsahuje
     * Všechny přírodniny této poznávačky tak mají stejnou šanci, že jejich obrázek bude vybrán
     * Počet obrázků u jednotlivých přírodniny nemá na výběr vliv
     * @param int $count Požadovaný počet náhodných obrázků (není zajištěna absence duplikátů)
     * @return Picture[] Polé náhodně vybraných obrázků obsahující specifikovaný počet prvků
     * @throws DatabaseException Pokud se vyskytne chyba při práci s databází
     */
    public function getRandomPictures(int $count): array
    {
        $result = array();
        
        $naturals = $this->getNaturals();
        $naturalsCount = count($naturals);
        for ($i = 0; $i < $count; $i++) {
            $randomNaturalNum = rand(0, $naturalsCount - 1);
            $picture = $naturals[$randomNaturalNum]->getRandomPicture();
            if ($picture === null)  //Kontrola, zda byl u vybrané přírodniny alespoň jeden obrázek
            {
                $i--;
                continue;
            }
            $result[] = $picture;
        }
        
        return $result;
    }
    
    /**
     * Metoda navracející objekty přírodnin ze všech částí této poznávačky
     * Pokud zatím nebyly načteny části této poznávačky, budou načteny z databáze
     * @return Natural[] Pole přírodnin patřících do této poznávačky jako objekty
     * @throws DatabaseException
     */
    public function getNaturals(): array
    {
        if (!$this->isDefined($this->naturals)) {
            $this->loadNaturals();
        }
        return $this->naturals;
    }
    
    /**
     * Metoda načítající seznam přírodnin patřících do všech částí této poznávačky a ukládající jejich instance do
     * vlastnosti $naturals jako pole
     * @throws DatabaseException
     */
    private function loadNaturals(): void
    {
        $this->loadIfNotLoaded($this->parts);
        
        $naturals = array();
        $naturalIds = array();
        foreach ($this->parts as $part) { //Projeď přírodniny všech částí, jednu po druhé
            $partNaturals = $part->getNaturals();
            foreach ($partNaturals as $partNatural) { //Vyfiltruj duplicitní přírodniny (vyskytují se ve více částech)
                if (!in_array($partNatural->getId(), $naturalIds)) {
                    $naturalIds[] = $partNatural->getId();
                    $naturals[] = $partNatural;
                }
            }
        }
        $this->naturals = $naturals;
    }
    
    /**
     * Metoda navracející část patřící do této poznávačky jako pole objektů
     * @return array Pole částí jako objekty
     * @throws DatabaseException
     */
    public function getParts(): array
    {
        /*
        Po znovunačítání edit stránky je z nějakýho důvodu vlastnost parts nastavena na NULL
        Tohle je tak trochu hack, ale prostě se mi nepodařilo zjistit, kde se sakra do té vlastnosti
        dostane NULL, když je nastavená jako protected a neukládá se do databáze (takže DatabaseItem
        s ní nepracuje s výjimkou jejího nastavení na undefined v konstruktoru
        */
        if (!$this->isDefined($this->parts) || $this->parts === null) {
            $this->loadParts();
        }
        return $this->parts;
    }
    
    /**
     * Metoda načítající části patřící do této poznávačky a ukládající je jako vlastnost
     * @throws DatabaseException
     */
    private function loadParts(): void
    {
        $this->loadIfNotLoaded($this->id);
        
        $result = Db::fetchQuery('SELECT '.Part::COLUMN_DICTIONARY['id'].','.Part::COLUMN_DICTIONARY['name'].','.
                                 Part::COLUMN_DICTIONARY['url'].','.Part::COLUMN_DICTIONARY['naturalsCount'].','.
                                 Part::COLUMN_DICTIONARY['picturesCount'].' FROM '.Part::TABLE_NAME.' WHERE '.
                                 Part::COLUMN_DICTIONARY['group'].' = ?', array($this->id), true);
        if ($result === false || count($result) === 0) {
            //Žádné části nenalezeny
            $this->parts = array();
        } else {
            $this->parts = array();
            foreach ($result as $partData) {
                $part = new Part(false, $partData[Part::COLUMN_DICTIONARY['id']]);
                $part->initialize($partData[Part::COLUMN_DICTIONARY['name']], $partData[Part::COLUMN_DICTIONARY['url']],
                    $this, null, $partData[Part::COLUMN_DICTIONARY['naturalsCount']],
                    $partData[Part::COLUMN_DICTIONARY['picturesCount']]);
                $this->parts[] = $part;
            }
        }
    }
    
    /**
     * Metoda získávající hlášení všech obrázků patřících k přírodninám, které jsou součástí této poznávačky
     * @return Report[] Pole objektů hlášení
     * @throws DatabaseException
     */
    public function getReports(): array
    {
        $this->loadIfNotLoaded($this->id);
        
        //Získání důvodů hlášení vyřizovaných správcem třídy
        $availableReasons = array_diff(Report::ALL_REASONS, Report::ADMIN_REQUIRING_REASONS);
        
        $in = str_repeat('?,', count($availableReasons) - 1).'?';
        $sqlArguments = array_values($availableReasons);
        $sqlArguments[] = $this->id;
        $result = Db::fetchQuery('
            SELECT
            '.Report::TABLE_NAME.'.'.Report::COLUMN_DICTIONARY['id'].' AS "hlaseni_id", '.Report::TABLE_NAME.'.'.
                                 Report::COLUMN_DICTIONARY['reason'].' AS "hlaseni_duvod", '.Report::TABLE_NAME.'.'.
                                 Report::COLUMN_DICTIONARY['additionalInformation'].' AS "hlaseni_dalsi_informace", '.
                                 Report::TABLE_NAME.'.'.Report::COLUMN_DICTIONARY['reportersCount'].' AS "hlaseni_pocet",
            '.Picture::TABLE_NAME.'.'.Picture::COLUMN_DICTIONARY['id'].' AS "obrazky_id", '.Picture::TABLE_NAME.'.'.
                                 Picture::COLUMN_DICTIONARY['src'].' AS "obrazky_zdroj", '.Picture::TABLE_NAME.'.'.
                                 Picture::COLUMN_DICTIONARY['enabled'].' AS "obrazky_povoleno",
            '.Natural::TABLE_NAME.'.'.Natural::COLUMN_DICTIONARY['id'].' AS "prirodniny_id", '.Natural::TABLE_NAME.'.'.
                                 Natural::COLUMN_DICTIONARY['name'].' AS "prirodniny_nazev", '.Natural::TABLE_NAME.'.'.
                                 Natural::COLUMN_DICTIONARY['picturesCount'].' AS "prirodniny_obrazky"
            FROM hlaseni
            JOIN '.Picture::TABLE_NAME.' ON '.Report::TABLE_NAME.'.'.Report::COLUMN_DICTIONARY['picture'].' = '.
                                 Picture::TABLE_NAME.'.'.Picture::COLUMN_DICTIONARY['id'].'
            JOIN '.Natural::TABLE_NAME.' ON '.Picture::TABLE_NAME.'.'.Picture::COLUMN_DICTIONARY['natural'].' = '.
                                 Natural::TABLE_NAME.'.'.Natural::COLUMN_DICTIONARY['id'].'
            WHERE '.Report::TABLE_NAME.'.'.Report::COLUMN_DICTIONARY['reason'].' IN ('.$in.')
            AND '.Natural::TABLE_NAME.'.'.Natural::COLUMN_DICTIONARY['id'].' IN (
                SELECT prirodniny_id 
                FROM prirodniny_casti 
                WHERE casti_id IN (
                    SELECT '.Part::COLUMN_DICTIONARY['id'].' 
                    FROM '.Part::TABLE_NAME.' 
                    WHERE '.Part::COLUMN_DICTIONARY['group'].' = ?
                )
            );
        ', $sqlArguments, true);
        
        if ($result === false) {
            //Žádná hlášení nenalezena
            return array();
        }
        
        $reports = array();
        foreach ($result as $reportInfo) {
            $natural = new Natural(false, $reportInfo['prirodniny_id']);
            $natural->initialize($reportInfo['prirodniny_nazev'], null, $reportInfo['prirodniny_obrazky']);
            $picture = new Picture(false, $reportInfo['obrazky_id']);
            $picture->initialize($reportInfo['obrazky_zdroj'], $natural, $reportInfo['obrazky_povoleno']);
            $report = new Report(false, $reportInfo['hlaseni_id']);
            $report->initialize($picture, $reportInfo['hlaseni_duvod'], $reportInfo['hlaseni_dalsi_informace'],
                $reportInfo['hlaseni_pocet']);
            $reports[] = $report;
        }
        
        return $reports;
    }
    
    /**
     * Metoda nastavující poznávačce nový název a podle něj aktualizuje i URL
     * Počítá se s tím, že jméno již bylo ošetřeno na délku, znaky a unikátnost
     * Změna není uložena do databáze, aby bylo nové jméno trvale uloženo, musí být zavolána metoda Group::save()
     * @param string $newName Nový název třídy
     */
    public function rename(string $newName): void
    {
        $this->name = $newName;
        $this->url = $this->generateUrl($newName);
    }
    
    /**
     * Metoda nahrazující všechny staré poznávačky patřící do této poznávačky novými
     * Změny jsou provedeny na úrovni databáze - staré poznávačky jsou smazány a jsou nahrazeny novými
     * I počet částí v této poznávačce je touto metodou aktualizován
     * Vlastnosti $parts a $partsCount tohoto objektu jsou aktualizovány
     * @param array $newParts Pole nových částí jako objekty
     * @throws DatabaseException
     */
    public function replaceParts(array $newParts): void
    {
        $this->loadIfNotLoaded($this->id);
        
        //Smaž staré části z databáze
        Db::executeQuery('DELETE FROM '.Part::TABLE_NAME.' WHERE '.Part::COLUMN_DICTIONARY['group'].' = ?',
            array($this->id));
        
        //Ulož do databáze nové části
        foreach ($newParts as $part) {
            $part->save();
        }
        
        //Aktualizuj počet částí poznávačky
        Db::executeQuery('UPDATE '.self::TABLE_NAME.' SET '.self::COLUMN_DICTIONARY['partsCount'].' = ? WHERE '.
                         self::COLUMN_DICTIONARY['id'].' = ?', array(count($newParts), $this->id));
        
        //Nahraď poznávačky a aktualizuj počet částí uložený ve vlastnostech tohoto objektu 
        $this->parts = $newParts;
        $this->partsCount = count($newParts);
    }
}

