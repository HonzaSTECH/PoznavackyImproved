<?php
/** 
 * Třída obsahující metodu pro ošetření dat proti XSS útoku
 * @author Jan Štěch
 */
class AntiXssSanitizer
{
    /**
     * Metoda ošetřující proměnnou proti XSS útoku
     * V případě, že je proměnná řetězec, číslo nebo bool, je jednoduše aplikována metoda htmlspecialchars()
     * V případě, že je promenná pole, jsou rekurzivně touto metodou ošetřeny všechny jeho prvky
     * V případě, že se jedná o instanci modelu dědícího z DatabaseItem, jsou ošetřeny stejným způsobem všechny jeho definované vlastnosti
     * V případě, že je proměnná instancí třídy DateTime, nebo má hodnotu NULL, nic se s proměnnou neděje
     * V případě, že se jedná o instanci nějaké jiné třídy, je vyhozena výjimka, protože není jasné, jak se má takový objekt ošetřit
     * @param mixed $data Proměná k ošetření
     * @throws ErrorException V případě, že je proměnná instancí třídy jiné, než DateTime nebo třídy dědící ze třídy DatabaseItem
     * @return mixed Ošetřená hodnota stejného datového typu
     */
    public function sanitize($data)
    {
        if (gettype($data) === 'NULL')
        {
            //NULL může zůstat NULL
            return null;
        }
        if (gettype($data) === 'array')
        {
            //Ošetři postupně všechny prvky pole
            foreach ($data as $key => $value) { $data[$key] = $this->sanitize($value); }
        }
        else if ($data instanceof DatabaseItem)
        {
            //Rekurze na vnořeném objektu
            $data->sanitizeSelf();
        }
        else if ($data instanceof DateTime)
        {
            //DateTime je bezpečný - https://stackoverflow.com/q/64624144/14011077
            return $data;
        }
        else if (gettype($data) === 'object')
        {
            //Neznámý typ objektu
            throw new ErrorException('Couldn\'t sanitize object of type '.get_class($data).' against XSS attack.');
        }
        else
        {
            //boolean, integer, double, string
            $data = htmlspecialchars($data, ENT_QUOTES);
        }
        return $data;
    }
}
