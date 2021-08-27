var smallTablet = 672;

$(function()
{
    //přidání třídy disabled tlačítkům a inputům, které nelze zpočátku využít
    $(".url-fieldset label, #url-input, #url-confirm-button, .preview-buttons-fieldset .btn").addClass("disabled");

    resizeMainImg();

    //event listenery tlačítek
    $("#url-confirm-button").click(function(event) {pictureSelected(event)});
    //nastaven setTimeout s intervalem 0 na změnu pořadí volaných funkcí (tato se nyní správně volá později než funkce spravující custom select box ze souboru generic.js)
    $("#add-natural-select .custom-options .custom-option").click(function() {setTimeout(function() {naturalSelected()}), 0});
    $("#submit-button").click(function(event) {submitPicture(event)});

    //event listener umožňující potvrzení URL adresy odenterováním
    $("#url-input").on("keyup", function(event)
    {
        if (event.keyCode === 13)
        {
            $("#url-confirm-button").click();
        }
    });

    //event listenery kontrolující správné načtení obrázku po zadání url adresy
    //chyba při načítání obrázku
    $("#preview-img-hidden").on("error", function()
    {
        $("#preview-img").attr("src", "images/blank.gif");
        $("#submit-button").addClass("disabled");
    });
    //obrázek načten úspěšně
    $("#preview-img-hidden").on("load", function()
    {
        $("#preview-img").attr("src", $("#preview-img-hidden").attr("src"));
        $("#submit-button").removeClass("disabled");
    });
})

$(window).resize(function()
{
    resizeMainImg();
})

/**
 * Funkce nastavující výšku #preview-img a .preview-buttons-fieldset tak, aby byla shodná s šířkou #preview-img
 */
function resizeMainImg(){
    let imageWidth = $("#add-pictures-form-wrapper #preview-img").outerWidth()
    $("#add-pictures-form-wrapper #preview-img").css("height", imageWidth);

    //nastavení výšky .preview-buttons-fieldset pouze v případě, že se zobrazuje vedle #preview-img a ne pod ním
    if ($(window).width() >= smallTablet)
    {
        $(".preview-buttons-fieldset").css("height", $("#add-pictures-form-wrapper #preview-img").height());
    }
    else 
    {
        $(".preview-buttons-fieldset").css("height", "auto");
    }
}

/**
 * Funkce nastavující název vybrané přírodniny
 */
function naturalSelected()
{
    let selectedNatural = "";

    //odstranění počtu obrázku dané přirodniny v závorce
    var arr = $("#add-natural-select .custom-options .selected").text();
    for (var i = arr.length - 1; arr[i] != '('; i--) {}
    for (var j = 0; j < i - 1; j++) {selectedNatural += arr[j];}

    $("#duck-link").attr("href", "https://duckduckgo.com/?q=" + selectedNatural + "&iax=images&ia=images");
    $("#google-link").attr("href", "https://www.google.com/search?q=" + selectedNatural + "&tbm=isch");
    $("#natural-name-hidden").val(selectedNatural);

    $(".url-fieldset label, #url-input, #url-confirm-button, #duck-link, #google-link").removeClass("disabled");
}

/**
 * Funkce nastavující adresu pro skrytý náhled obrázku
 * @param {event} event 
 */
function pictureSelected(event)
{
    event.preventDefault();

    //nahraď https na začátku http (funguje častěji)
    let url = $("#url-input").val();
    let re = /^https:\/\//;
    url = url.replace(re, "http://")

    $("#preview-img-hidden").attr("src", url);
    $("#preview-img").attr("src", "images/loading.svg");
    $("#submit-button").addClass("disabled");

    //kontrola správného načtení pomocí event listenerů v hlavní funkci
}

/**
 * Funkce odesílající požadavek na uložení obrázku
 * @param {event} event 
 */
function submitPicture(event)
{
    event.preventDefault();

    let url = document.location.href;
    if (url[url.length - 1] === '/'){ url = url.substr(0, url.length - 1); } //odstranění trailing slashe
    url = url.substr(0, url.lastIndexOf("/")); //odstranění názvu posledního kontroleru
    url += "/submit-picture"
    let naturalName = $("#add-natural-select .custom-options .selected").text();
    naturalName = naturalName.trim();    //Ořezání whitespace
    naturalName = naturalName.substr(0, naturalName.lastIndexOf("(") - 1); //Odstranění mezery následované závorkami s počtem obrázků

    $.post(url,
        {
            naturalName: naturalName,
            url: $("#url-input").val()
        },
        function (response, status)
        {
            ajaxCallback(response, status,
                function (messageType, message, data)
                {
                    if (messageType === "success")
                    {
                        newMessage(message, "success");

                        //Reset HTML
                        $("#url-input").val("");
                        $("#preview-img").attr("src", "images/blank.gif");
                        $("#submit-button").addClass("disabled");
                        
                        //Zvyš počet obrázků u přírodniny v select boxu
                        let optionText = $(".custom-select-main>span").text().trim();
                        let countPosition = optionText.search(/\(\d+\)$/) + 1;
                        let count = optionText.substring(countPosition, optionText.length - 1);
                        optionText = optionText.replace(/\(\d+\)$/, '(' + (++count) + ')');
                        $(".custom-select-main span").text(optionText);
                        $(".custom-options>.custom-option.selected").text(optionText);
                    }
                    else if (messageType === "error")
                    {
                        newMessage(message, "error");
                    }
                }
            );
        },
        "json"
    );
}