<div id="tabs">
    <ul>
        <li><a href="#tabs-1">Manuelle Buchung</a></li>
    </ul>
    <div id="tabs-1">
        [MESSAGE]
        <form action="index.php?module=fibu_buchungen&action=save_new" method="post">
            [FORMHANDLEREVENT]
            <div class="row">
                <div class="row-height">
                    <div class="col-xs-12 col-md-12 col-md-height">
                        <div class="inside inside-full-height">
                            <fieldset>
                                <legend>{|Freie Buchungsmaske (Dialogbuchen)|}</legend>
                                <table width="100%" border="0" class="mkTableFormular">
                                    <tr>
                                        <td>{|Datum|}:</td>
                                        <td><input type="text" name="datum" id="datum" value="[DATUM]" size="20"></td>
                                    </tr>
                                    <tr>
                                        <td>{|Belegnummer|}:</td>
                                        <td><input type="text" name="belegnummer" id="belegnummer" value="[BELEGNUMMER]" size="40"></td>
                                    </tr>
                                    <tr>
                                        <td>{|Buchungstext|}:</td>
                                        <td><input type="text" name="buchungstext" id="buchungstext" value="[BUCHUNGSTEXT]" size="80"></td>
                                    </tr>
                                    <tr>
                                        <td>{|Betrag|}:</td>
                                        <td><input type="text" name="betrag" id="betrag" value="[BETRAG]" size="20"></td>
                                    </tr>
                                    <tr>
                                        <td>{|W&auml;hrung|}:</td>
                                        <td><select name="waehrung" id="waehrung">[WAEHRUNG]</select></td>
                                    </tr>
                                    <tr>
                                        <td>{|Buchungsschl&uuml;ssel|}:</td>
                                        <td><select name="buchungsschluessel" id="buchungsschluessel">[BUCHUNGSSCHLUESSEL_OPTIONS]</select></td>
                                    </tr>
                                    <tr>
                                        <td>{|Sollkonto|}:</td>
                                        <td><input type="text" name="sollkonto" id="sollkonto" value="[SOLLKONTO]" size="40"></td>
                                    </tr>
                                    <tr>
                                        <td>{|Habenkonto|}:</td>
                                        <td><input type="text" name="habenkonto" id="habenkonto" value="[HABENKONTO]" size="40"></td>
                                    </tr>
                                </table>
                            </fieldset>
                            <input type="submit" name="submit" value="Buchen" style="float:right"/>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
