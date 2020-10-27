<?php
/** 
 * Třída reprezentující hlášení obrázku
 * @author Jan Štěch
 */
class Report extends DatabaseItem
{
    public const TABLE_NAME = 'hlaseni';
    
    public const COLUMN_DICTIONARY = array(
        'id' => 'hlaseni_id',
        'picture' => 'obrazky_id',
        'reason' => 'duvod',
        'additionalInformation' => 'dalsi_informace',
        'reportersCount' => 'pocet'
    );
    
    protected const NON_PRIMITIVE_PROPERTIES = array(
        'picture' => Picture::class
    );
    
    protected const DEFAULT_VALUES = array(
        'additionalInformation' => null,
        'reportersCount' => 1
    );
    
    protected const CAN_BE_CREATED = true;
    protected const CAN_BE_UPDATED = true;
    
    // PŘI ÚPRAVĚ KONSTANT NUTNÉ UPRAVIT I PROMĚNNÉ V REPORT.JS
    const ALL_REASONS = array(self::REASON_NOT_DISPLAYING, self::REASON_LONG_LOADING, self::REASON_INCORRECT_NATURAL, self::REASON_CONTAINS_NAME, self::REASON_BAD_RESOLUTION, self::REASON_COPYRIGHT, self::REASON_OTHER, self::REASON_OTHER_ADMIN);
    const ADMIN_REQUIRING_REASONS = array(self::REASON_COPYRIGHT, self::REASON_OTHER_ADMIN);
    const LONG_LOADING_AVAILABLE_DELAYS = array('>2 s', '>5 s', '>10 s', '>20 s');
    const INCORRECT_NATURAL_DEFAULT_INFO = 'Nezadáno';
    
    const REASON_NOT_DISPLAYING = 'Obrázek se nezobrazuje správně';
    const REASON_LONG_LOADING = 'Obrázek se načítá příliš dlouho';
    const REASON_INCORRECT_NATURAL = 'Obrázek zobrazuje nesprávnou přírodninu';
    const REASON_CONTAINS_NAME = 'Obrázek obsahuje název přírodniny';
    const REASON_BAD_RESOLUTION = 'Obrázek má příliš špatné rozlišení';
    const REASON_COPYRIGHT = 'Obrázek porušuje autorská práva';
    const REASON_OTHER = 'Jiný důvod (řeší správce třídy)';
    const REASON_OTHER_ADMIN = 'Jiný důvod (řeší správce služby)';
    
    protected $picture;
    protected $reason;
    protected $additionalInformation;
    protected $reportersCount;
    
    /**
     * Metoda nastavující všechny vlasnosti objektu (s výjimkou ID) podle zadaných argumentů
     * Při nastavení některého z argumentů na undefined, je hodnota dané vlastnosti také nastavena na undefined
     * Při nastavení některého z argumentů na null, není hodnota dané vlastnosti nijak pozměněna
     * @param Picture|undefined|null $picture Odkaz na objekt obrázku, ke kterému se toto hlášení vztahuje
     * @param string|undefined|null $reason Důvod hlášení (musí být jedna z konstant této třídy začínající "REASON_")
     * @param string|undefined|null $additionalInformation Další informace o hlášení odeslané uživatelem
     * @param int|undefined|null $reportersCount Počet uživatelů, kteří odeslali hlášení tohoto typu
     * {@inheritDoc}
     * @see DatabaseItem::initialize()
     */
    public function initialize($picture = null, $reason = null, $additionalInformation = null, $reportersCount = null)
    {
        //Kontrola nespecifikovaných hodnot (pro zamezení přepsání známých hodnot)
        if ($picture === null){ $picture = $this->picture; }
        if ($reason === null){ $reason = $this->reason; }
        if ($additionalInformation === null){ $additionalInformation = $this->additionalInformation; }
        if ($reportersCount === null){ $reportersCount = $this->reportersCount; }
        
        $this->picture = $picture;
        $this->reason = $reason;
        $this->additionalInformation = $additionalInformation;
        $this->reportersCount = $reportersCount;
    }
        
    /**
     * Metoda navracející objekt nahlášeného obrázku
     * @return Picture Nahlášený obrázek
     */
    public function getPicture()
    {
        $this->loadIfNotLoaded($this->picture);
        return $this->picture;
    }
    
    /**
     * Metoda navracející ID nahlášeného obrázku
     * @return int ID obrázku
     */
    public function getPictureId()
    {
        $this->loadIfNotLoaded($this->picture);
        return $this->picture->getId();
    }
    
    /**
     * Metoda navracející URL nahlášeného obrázku
     * @return string Zdroj obrázku
     */
    public function getUrl()
    {
        $this->loadIfNotLoaded($this->picture);
        return $this->picture->getSrc();
    }
    
    /**
     * Metoda navracející řetězec se zařazením obrázku ve formátu <Název třídy> / <Název poznávačky> / <Název části> / <Název přírodniny>
     * @return string Řetězec obsahující cestu k obrázku
     */
    public function getPicturePath()
    {
        $this->loadIfNotLoaded($this->picture);
        $natural = $this->picture->getNatural();
        $part = $natural->getPart();  //TODO - Natural->getPart() již neexistuje
        $group = $part->getGroup();
        $class = $group->getClass();
        return $class->getName().' / '.$group->getName().' / '.$part->getName();
    }
    
    /**
     * Metoda navracející objekt části, do které patří nahlášený obrázek
     * @return Part Objekt části, do které nahlášený obrázek patří
     */
    public function getPartWithPicture()
    {
        $this->loadIfNotLoaded($this->picture);
        $natural = $this->picture->getNatural();
        return $natural->getPart();  //TODO - Natural->getPart() již neexistuje
    }
    
    /**
     * Metoda navracející název přírodniny, ke které byl nahlášený obrázek nahrán
     * @return string Název přírodniny na obrázku
     */
    public function getNaturalName()
    {
        $this->loadIfNotLoaded($this->picture);
        $natural = $this->picture->getNatural();
        return $natural->getName();
    }
    
    /**
     * Metoda navracející důvod hlášení
     * @return string Důvod hlášení (měl by být jednou z konstant této třídy)
     */
    public function getReason()
    {
        $this->loadIfNotLoaded($this->reason);
        return $this->reason;
    }
    
    /**
     * Metoda navracející další informace o hlášení
     * @return string Další informace o hlášení (pokud žádné nebyly poskytnuty, tak prázdný řetězec)
     */
    public function getAdditionalInformation()
    {
        $this->loadIfNotLoaded($this->additionalInformation);
        return $this->additionalInformation;
    }
    
    /**
     * Metoda navracející počet hlášení stejného typu
     * @return int Počet hlášení
     */
    public function getReportersCount()
    {
        $this->loadIfNotLoaded($this->reportersCount);
        return $this->reportersCount;
    }
    
    /**
     * Metoda zvyšující počet hlášení tohoto typu o 1
     */
    public function increaseReportersCount()
    {
        $this->loadIfNotLoaded($this->reportersCount);
        $this->reportersCount++;
    }
}