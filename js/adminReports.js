$(function() { $("#tab3-link").addClass("active-tab"); }); //Nabarvi zvolenou záložku

// mělo by být zahrnuto v resolveReports.js - tento soubor ale byl změněn
// dočasně proto přesouvám sem, aby byla zachována funkčnost
// později, po úpravě samotného administrate pohledu, by měl být opět dostačující soubor resolveReports.js

function showPicture(url)
{
    $("#image-preview img").attr("src", url);
    $("#image-preview").show();
    $("#overlay").show();
}
var currentReportValues = new Array(2);
function editPicture(event)
{
    //Dočasné znemožnění ostatních akcí u všech hlášení
    $(".report-action").addClass("grayscale-temp-report");
    $(".report-action").addClass("grayscale");
    $(".report-action").attr("disabled", "");

    //Získat <tr> element upravované řádky
    let row = $(event.target.parentNode.parentNode.parentNode);
    row.attr("id", "editable-report-row");

    //Uložení současných hodnot
    for (let i = 0; i <= 1; i++)
    {
        currentReportValues[i] = $("#editable-report-row .report-field:eq("+ i +")").val();
    }

    /*
    Pokud nebyla změněna přírodnina, bude v currentReportValues[0] uloženo NULL
    V takovém případě nahradíme tuto hodnotu textem zobrazeném v <select> elementu
    Tento text je innerText prvního <option> elementu
    */
    if (currentReportValues[0] === null){ currentReportValues[0] = $("#editable-report-row .report-field:eq(0)>option:eq(0)").text(); }

    $("#editable-report-row .report-action").hide();                    //Skrytí ostatních tlačítek akcí
    $("#editable-report-row .report-edit-buttons").show();                //Zobrazení tlačítek pro uložení nebo zrušení editace
    $("#editable-report-row .report-field").addClass("editable-field");    //Obarvení políček (//TODO)
    $("#editable-report-row .report-field").removeAttr("readonly");    //Umožnění editace (pro <input>)
    $("#editable-report-row .report-field").removeAttr("disabled");    //Umožnění editace (pro <select>)
}
function cancelPictureEdit()
{
    //Opětovné zapnutí ostatních tlačítek akcí
    $(".grayscale-temp-report").removeAttr("disabled");
    $(".grayscale-temp-report").removeClass("grayscale grayscale-temp-report");

    //Obnova hodnot vstupních polí
    for (let i = 0; i <= 1; i++)
    {
        $("#editable-report-row .report-field:eq("+ i +")").val(currentReportValues[i]);
    }

    $("#editable-report-row .report-action").show();                        //Znovuzobrazení ostatních tlačítek akcí
    $("#editable-report-row .report-edit-buttons").hide();                    //Skrytí tlačítek pro uložení nebo zrušení editace
    $("#editable-report-row .report-field").removeClass("editable-field");    //Odbarvení políček
    $("#editable-report-row input.report-field").attr("readonly", "");        //Znemožnění editace (pro <input>)
    $("#editable-report-row select.report-field").attr("disabled", "");    //Znemožnění editace (pro <select>)

    $("#editable-report-row").removeAttr("id");
}
function confirmPictureEdit(picId)
{
    //Uložení nových hodnot
    for (let i = 0; i <= 1; i++)
    {
        currentReportValues[i] = $("#editable-report-row .report-field:eq("+ i +")").val();
    }

    //Odeslat data na server
    $.post("administrate/report-action",
        {
            action: 'update picture',
            pictureId: picId,
            natural: currentReportValues[0],
            url: currentReportValues[1]
        },
        function (response, status)
        {
            ajaxCallback(response, status,
                function (messageType, message, data)
                {
                    if (messageType === "success")
                    {
                        //Reset DOM
                        cancelPictureEdit();
                        //TODO - zobraz (možná) nějak úspěchovou hlášku - ideálně ne jako alert() nebo jiný popup
                        //alert(message);
                    }
                    if (messageType === "error")
                    {
                        //TODO - zobraz nějak chybovou hlášku - ideálně ne jako alert() nebo jiný popup
                        alert(message);
                    }
                    else
                    {
                        //Aktualizuj údaje u hlášení stejného obrázku v DOM
                        let reportsToUpdateCount = $("#reports-table .picture-id" + picId).length;
                        for (let i = 0; i < reportsToUpdateCount; i++)
                        {
                            for (let j = 0; j <= 1; j++)
                            {
                                $("#reports-table .picture-id" + picId + ":eq(" + i + ") .report-field:eq("+ j +")").val(currentReportValues[j]);
                            }
                        }
                    }
                }
            );
        },
        "json"
    );
}
function deletePicture(event, picId, asAdmin = false)
{
    var ajaxUrl = "administrate/report-action";

    $.post(ajaxUrl,
        {
            action: 'delete picture',
            pictureId: picId
        },
        function (response, status)
        {
            ajaxCallback(response, status,
                function (messageType, message, data)
                {
                    if (messageType === "error")
                    {
                        //TODO - zobraz nějak chybovou hlášku - ideálně ne jako alert() nebo jiný popup
                        alert(message);
                    }
                    else
                    {
                        //Odebrání všechna hlášení daného obrázku z DOM
                        $("#reports-table .picture-id" + picId).remove();
                    }
                }
            );
        },
        "json"
    );
}
function deleteReport(event, reportId, asAdmin = false)
{
    var ajaxUrl = "administrate/report-action";

    $.post(ajaxUrl,
        {
            action: 'delete report',
            reportId: reportId
        },
        function (response, status)
        {
            ajaxCallback(response, status,
                function(messageType, message, data)
                {
                    if (messageType === "error")
                    {
                        //TODO - zobraz nějak chybovou hlášku - ideálně ne jako alert() nebo jiný popup
                        alert(message);
                    }
                    else
                    {
                        //Odebrání hlášení z DOM
                        event.target.parentNode.parentNode.parentNode.remove();
                    }
                }
            );
        },
        "json"
    );
}