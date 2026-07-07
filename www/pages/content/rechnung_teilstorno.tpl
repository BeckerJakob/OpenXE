<div id="tabs">
    <ul>
        <li><a href="#tabs-1">[TABTEXT]</a></li>
    </ul>
    <div id="tabs-1">
        <div class="info">[INFOTEXT]</div>
        <br>
        <script type="text/javascript">
            $(document).on('input change', '.rechnung-teilstorno-menge', function() {
                var menge = parseFloat(String($(this).val()).replace(',', '.'));
                var nettoEinzel = parseFloat(String($(this).data('netto-einzel')).replace(',', '.'));
                if (isNaN(menge)) {
                    menge = 0;
                }
                if (isNaN(nettoEinzel)) {
                    nettoEinzel = 0;
                }
                var betrag = menge * nettoEinzel;
                var $betrag = $(this).closest('tr').find('.rechnung-teilstorno-betrag');
                $betrag.text(
                    betrag.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2})
                );
            });
            function updateBetragStornoPreview() {
                var betrag = parseFloat(String($('#betragstorno_betrag').val()).replace(',', '.'));
                var steuersatz = parseFloat(String($('#betragstorno_steuersatz').val()).replace(',', '.'));
                var modus = $('input[name="betragstorno_modus"]:checked').val();
                if (isNaN(betrag)) {
                    betrag = 0;
                }
                if (isNaN(steuersatz)) {
                    steuersatz = 0;
                }

                var netto = betrag;
                var brutto = betrag;
                if (modus === 'brutto') {
                    netto = steuersatz > -100 ? betrag / (1 + (steuersatz / 100)) : 0;
                } else {
                    brutto = betrag * (1 + (steuersatz / 100));
                }
                var steuer = brutto - netto;

                $('#betragstorno_netto').text(netto.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#betragstorno_steuer').text(steuer.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#betragstorno_brutto').text(brutto.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            }
            $(document).on('input change', '#betragstorno_betrag, #betragstorno_steuersatz, input[name="betragstorno_modus"]', updateBetragStornoPreview);
            function updateStornoMode() {
                var mode = $('input[name="rechnung_storno_mode"]:checked').val();
                $('.rechnung-storno-mode-content').hide();
                $('.rechnung-storno-mode-' + mode).show();
            }
            $(document).on('change', 'input[name="rechnung_storno_mode"]', updateStornoMode);
            $(document).ready(function() {
                updateBetragStornoPreview();
                updateStornoMode();
            });
        </script>
        <form action="" method="post">
            [MESSAGE]
            <fieldset>
                <legend>{|Art der Stornierung|}</legend>
                <table width="100%" border="0" class="mkTableFormular">
                    <tr>
                        <td>
                            <label><input type="radio" name="rechnung_storno_mode" value="positionen" [RECHNUNG_STORNO_MODE_POSITIONEN_CHECKED]> {|Positionen stornieren|}</label>
                            &nbsp;&nbsp;
                            <label><input type="radio" name="rechnung_storno_mode" value="betrag" [RECHNUNG_STORNO_MODE_BETRAG_CHECKED]> {|Betrag gutschreiben|}</label>
                        </td>
                    </tr>
                </table>
            </fieldset>
            <br>
            <fieldset class="rechnung-storno-mode-content rechnung-storno-mode-betrag">
                <legend>{|Betrag gutschreiben|}</legend>
                <table width="100%" border="0" class="mkTableFormular">
                    <tr>
                        <td width="160">{|Gutschriftbetrag|}:</td>
                        <td><input type="text" name="betragstorno_betrag" id="betragstorno_betrag" value="[BETRAGSTORNO_BETRAG]" size="12"></td>
                        <td width="120">{|Betragstyp|}:</td>
                        <td>
                            <label><input type="radio" name="betragstorno_modus" value="brutto" [BETRAGSTORNO_BRUTTO_CHECKED]> {|brutto|}</label>
                            <label><input type="radio" name="betragstorno_modus" value="netto" [BETRAGSTORNO_NETTO_CHECKED]> {|netto|}</label>
                        </td>
                    </tr>
                    <tr>
                        <td>{|Steuersatz|}:</td>
                        <td><select name="betragstorno_steuersatz" id="betragstorno_steuersatz">[BETRAGSTORNO_STEUERSATZ_OPTIONS]</select></td>
                        <td>{|Vorschau|}:</td>
                        <td>
                            {|Netto|}: <span id="betragstorno_netto">0,00</span>,
                            {|USt|}: <span id="betragstorno_steuer">0,00</span>,
                            {|Brutto|}: <span id="betragstorno_brutto">0,00</span>
                        </td>
                    </tr>
                    <tr>
                        <td>{|Grund / Text|}:</td>
                        <td colspan="3"><input type="text" name="betragstorno_grund" value="[BETRAGSTORNO_GRUND]" style="width:100%;"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td colspan="3"><button name="submit" value="betrag_speichern" class="ui-button-icon">Betragsgutschrift erstellen</button></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td colspan="3"><button name="submit" value="abbrechen" class="ui-button-icon">Vorgang abbrechen</button></td>
                    </tr>
                </table>
            </fieldset>
            <br>
            <div class="row rechnung-storno-mode-content rechnung-storno-mode-positionen">
                <div class="row-height">
		            <div class="col-xs-14 col-md-12 col-md-height">
			            <div class="inside inside-full-height">
				            <fieldset>
                                <legend>{|Positionen stornieren|}</legend>
                                [TABLE]
                            </fieldset>
                        </div>
               		</div>
		            <div class="col-xs-14 col-md-2 col-md-height">
			            <div class="inside inside-full-height">
                            <fieldset>
                                <table width="100%" border="0" class="mkTableFormular">
                                    <legend>{|Aktionen|}</legend>
                                    <tr><td><button name="submit" id="speichern" value="speichern" class="ui-button-icon" style="width:100%";>Stornierung buchen</button></td></tr>
                                    <tr><td><button name="submit" id="abbrechen" value="abbrechen" class="ui-button-icon" style="width:100%";>Vorgang abbrechen</button></td></tr>
                                </table>
                            </fieldset>
                        </div>
               		</div>
                </div>
            </div>
        </form>
    </div>
</div>
