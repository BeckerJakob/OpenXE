[POSITIONENMESSAGE]
<form method="post" action="#tabs-2">   
    <div class="row" [POSITIONHINZUFUEGENHIDDEN]>        
        <div class="row-height">
            <div class="col-xs-14 col-md-12 col-md-height">                
                <div class="inside inside-full-height">
                    <fieldset>
                        <legend>{|Manuelle Position hinzuf&uuml;gen|}</legend>
                        <table width="100%" border="0" class="mkTableFormular">
                            <tr>
                                <td>{|Bezeichnung|}:</td>
                                <td><input type="text" name="man_bezeichnung" id="man_bezeichnung" value="" size="40"></td>
                            </tr>
                            <tr>
                                <td>{|Menge|}:</td>
                                <td><input type="number" name="man_menge" id="man_menge" value="1" step="0.0001" min="0.0001" size="10"></td>
                            </tr>
                            <tr>
                                <td>{|Preis|}:</td>
                                <td><input type="number" name="man_preis" id="man_preis" value="0" step="0.01" size="10"></td>
                            </tr>
                            <tr>
                                <td>{|Steuersatz %|}:</td>
                                <td><input type="number" name="man_steuersatz" id="man_steuersatz" value="[STANDARDSTEUERSATZ]" step="0.01" min="0" size="10"></td>
                            </tr>
                            <tr>
                                <td>{|Sachkonto|}:</td>
                                <td><input type="text" name="man_sachkonto" id="man_sachkonto" value="" size="20"></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <input type="checkbox" name="man_bruttoeingabe" value="1" />{|Bruttopreis eingeben|}
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td><button name="submit" value="manuelle_position_hinzufuegen" class="ui-button-icon">{|Manuelle Position hinzuf&uuml;gen|}</button></td>
                            </tr>
                        </table>
                    </fieldset>
                </div>
            </div>
        </div>
    </div>
</form>
<div hidden>
<form method="post" action="#tabs-2">   
    <div class="row" [POSITIONHINZUFUEGENHIDDEN]>        
        <div class="row-height">
            <div class="col-xs-14 col-md-12 col-md-height">                
                <div class="inside inside-full-height">
                    <fieldset>
                        <legend style="float:left">Offene Artikel aus Wareneing&auml;ngen:</legend>
                        <div class="filter-box filter-usersave" style="float:right;">
                            <div class="filter-block filter-inline">
                                <div class="filter-title">{|Filter|}</div>
                                <ul class="filter-list">
                                    <li class="filter-item">
                                        <label for="passende" class="switch">
                                        <input type="checkbox" id="passende">
                                        <span class="slider round"></span>
                                      </label>
                                        <label for="passende">{|Nur passende (Bestellung/Rechnungsnummer)|}</label>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        [PAKETDISTRIBUTION]
                    </fieldset>
                </div>
            </div>
            <div class="col-xs-14 col-md-2 col-md-height">
                <div class="inside inside-full-height">
                    <fieldset>
                        <table width="100%" border="0" class="mkTableFormular">
                            <legend>{|Aktionen|}</legend>
                            <tr>
                                <td><input type="checkbox" id="auswahlallewareneingaenge" onchange="allewareneingaengeauswaehlen();" />{|alle markieren|}</td>
                            </tr>                          
                            <tr>
                                <td><input type="checkbox" name="bruttoeingabe" value="1" />Bruttopreise eingeben</td>
                            </tr>                                  
                            <tr>
                                <td><button [SAVEDISABLED] name="submit" value="positionen_hinzufuegen" class="ui-button-icon" style="width:100%;">Hinzuf&uuml;gen</button></td>
                            </tr>
                        </table>
                    </fieldset>
                </div>
            </div>
        </div>
    </div>
</form>
</div>
<form method="post" action="#tabs-2">
    <div class="row">
        <div class="row-height">
            <div class="col-xs-14 col-md-12 col-md-height">
                <div class="inside inside-full-height">
                    [POSITIONEN]                 
                </div>
            </div>
            <div class="col-xs-14 col-md-2 col-md-height">
                <div class="inside inside-full-height">
                    <fieldset>
                        <table width="100%" border="0" class="mkTableFormular">
                            <legend>{|Aktionen|}</legend>
                            <tr [SACHKONTOCHANGEHIDDEN]>
                                <td><input type="checkbox" id="auswahlalle" onchange="alleauswaehlen();" />{|alle markieren|}</td>
                            </tr>                          
                            <tr [POSITIONHINZUFUEGENHIDDEN]>
                                <td><button [SAVEDISABLED] name="submit" value="positionen_entfernen" class="ui-button-icon" style="width:100%;">Entfernen</button></td>
                            </tr>
                            <tr [SACHKONTOCHANGEHIDDEN]>
                                <td><input type="text" name="positionen_sachkonto" id="positionen_sachkonto" value="" size="20"></td>
                            </tr>
                            <tr [SACHKONTOCHANGEHIDDEN]>
                                <td><button name="submit" value="positionen_kontorahmen_setzen" class="ui-button-icon" style="width:100%;">Sachkonto setzen</button></td>
                            </tr>
                        </table>
                    </fieldset>
                </div>
            </div>
        </div>
    </div>
</form>
<script>
    function allewareneingaengeauswaehlen()
    {
      var wert = $('#auswahlallewareneingaenge').prop('checked');
      $('#verbindlichkeit_paketdistribution_list').find(':checkbox').prop('checked',wert);
    }
    function alleauswaehlen()
    {
      var wert = $('#auswahlalle').prop('checked');
      $('#verbindlichkeit_positionen').find(':checkbox').prop('checked',wert);
    }
</script>
