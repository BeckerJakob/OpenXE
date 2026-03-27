<?php
/*
**** COPYRIGHT & LICENSE NOTICE *** DO NOT REMOVE ****
*
* Xentral (c) Xentral ERP Sorftware GmbH, Fuggerstrasse 11, D-86150 Augsburg, * Germany 2019
*
* This file is licensed under the Embedded Projects General Public License *Version 3.1.
*
* You should have received a copy of this license from your vendor and/or *along with this file; If not, please visit www.wawision.de/Lizenzhinweis
* to obtain the text of the corresponding license version.
*
**** END OF COPYRIGHT & LICENSE NOTICE *** DO NOT REMOVE ****
*/
/*
*   Copyright (c) 2023 OpenXE project
*/
?>
<?php
use Xentral\Modules\DatevExport\DatevExport;
use Xentral\Modules\DatevExport\ConsistencyException;
class Exportbuchhaltung
{
    /** @var Application $app */
    var $app;
    var $belegnummer;
    var $headerwritten = false;
    var SimpleXMLElement $document_xml;
    var ZipArchive $zip;
    var ZipArchive $zipbelege;

    function typen($rechnung, $gutschrift, $verbindlichkeit, $lieferantengutschrift) : array {
        return(
            array(
                array(
                    'typ' => 'rechnung',
                    'subtable' => 'rechnung_position',
                    'kennzeichen' => 'S',
                    'kennzeichen_negativ' => 'H',
                    'field_belegnr' => 'b.belegnr',
                    'field_name' => 'b.name',
                    'field_date' => 'datum',
                    'field_auftrag' => 'MAKE_SET(3,b.auftrag,(SELECT auftrag.internet FROM auftrag WHERE auftrag.id = auftragid))',
                    'field_zahlweise' => 'CONCAT(UCASE(LEFT(b.zahlungsweise, 1)),SUBSTRING(b.zahlungsweise, 2))',
                    'field_kontonummer' => 'a.kundennummer_buchhaltung',
                    'field_kundennummer' => 'b.kundennummer',
                    'field_betrag_gesamt' => 'b.soll',
                    'field_betrag' => 'p.umsatz_brutto_gesamt',
                    'field_land' => 'b.land',
                    'field_gegenkonto' => null,
                    'condition_where' => ' AND b.status IN (\'freigegeben\',\'versendet\',\'storniert\')',
                    'Buchungstyp' => 'SR',
                    'document_type' => 2,
                    'do' => $rechnung,
                    'pdf' => 'print'
                ),
                array(
                    'typ' => 'gutschrift',
                    'subtable' => 'gutschrift_position',
                    'kennzeichen' => 'H',
                    'kennzeichen_negativ' => 'S',
                    'field_belegnr' => 'b.belegnr',
                    'field_name' => 'b.name',
                    'field_date' => 'datum',
                    'field_auftrag' => '\'\'',
                    'field_zahlweise' => '\'\'',
                    'field_kontonummer' => 'a.kundennummer_buchhaltung',
                    'field_kundennummer' => 'b.kundennummer',
                    'field_betrag_gesamt' => 'b.soll',
                    'field_betrag' => 'p.umsatz_brutto_gesamt',
                    'field_land' => 'b.land',
                    'field_gegenkonto' => null,
                    'condition_where' => ' AND b.status IN (\'freigegeben\',\'versendet\')',
                    'Buchungstyp' => '',
                    'document_type' => 2,
                    'do' => $gutschrift,
                    'pdf' => 'print'
                ),
                array(
                    'typ' => 'verbindlichkeit',
                    'subtable' => 'verbindlichkeit_position',
                    'kennzeichen' => 'H',
                    'kennzeichen_negativ' => 'S',
                    'field_belegnr' => 'b.rechnung',
                    'field_name' => 'a.name',
                    'field_date' => 'rechnungsdatum',
                    'field_auftrag' => 'b.auftrag',
                    'field_zahlweise' => '\'\'',
                    'field_kontonummer' => 'a.lieferantennummer_buchhaltung',
                    'field_kundennummer' => 'a.lieferantennummer',
                    'field_betrag_gesamt' => 'b.betrag',
                    'field_betrag' => 'p.preis*p.menge*((100+p.steuersatz)/100)',
                    'field_land' => 'a.land',
                    'field_gegenkonto' => '(SELECT sachkonto FROM kontorahmen k WHERE k.id = p.kontorahmen)',
                    'condition_where' => ' AND b.status IN (\'freigegeben\', \'abgeschlossen\')',
                    'Buchungstyp' => '',
                    'document_type' => 1,
                    'do' => $verbindlichkeit,
                    'pdf' => 'load'
                ),
                array(
                    'typ' => 'lieferantengutschrift',
                    'subtable' => 'lieferantengutschrift_position',
                    'kennzeichen' => 'S',
                    'kennzeichen_negativ' => 'H',
                    'field_belegnr' => 'b.rechnung',
                    'field_name' => 'a.name',
                    'field_date' => 'rechnungsdatum',
                    'field_auftrag' => '\'\'',
                    'field_zahlweise' => '\'\'',
                    'field_kontonummer' => 'a.lieferantennummer_buchhaltung',
                    'field_kundennummer' => 'a.lieferantennummer',
                    'field_betrag_gesamt' => 'b.betrag',
                    'field_betrag' => 'p.preis*p.menge*((100+p.steuersatz)/100)',
                    'field_land' => 'a.land',
                    'field_gegenkonto' => '(SELECT sachkonto FROM kontorahmen k WHERE k.id = p.kontorahmen)',
                    'condition_where' => ' AND b.status IN (\'freigegeben\', \'abgeschlossen\')',
                    'Buchungstyp' => '',
                    'document_type' => 1,
                    'do' => $lieferantengutschrift,
                    'pdf' => 'load'
                )
            )
        );
    }
    /**
    * Exportbelegepositionen constructor.
    *
    * @param Application $app
    * @param bool        $intern
    */
    public function __construct($app, $intern = false)
    {
        $this->app = $app;

        $this->document_xml = new SimpleXMLElement('<archive xmlns="http://xml.datev.de/bedi/tps/document/v06.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://xml.datev.de/bedi/tps/document/v06.0 Document_v060.xsd" version="6.0" generatingSystem="OpenXE"></archive>');

        $this->document_xml->addAttribute("version", "6.0");
        $this->document_xml->addAttribute("generatingSystem", "OpenXE");
        $this->document_xml->AddChild("header");
        $this->document_xml->header->AddChild("date");
        $this->document_xml->header->date = date_create('now')->format("Y-m-d\TH:i:s");;
        $this->document_xml->AddChild("content");

        $this->zip = new ZipArchive();
        $this->zipbelege = new ZipArchive();

        if ($intern == true) {
            return;
        }

        $this->app->ActionHandlerInit($this);
        $this->app->ActionHandler("export", "ExportBuchhaltungList");
        $this->app->ActionHandlerListen($app);
        $this->app->erp->Headlines('Buchhaltung Export DATEV');
    }

    function addfile($name, $contents, $guid, $document_type) {
        $this->zipbelege->addFromString($name, $contents);
        $document = $this->document_xml->content->AddChild("document");
        $document->addAttribute("type", $document_type);
        $document->addAttribute("processID", "1");
        $document->addAttribute("guid", $guid);
        $extension = $document->AddChild("extension");
        $extension->addAttribute("xsi:type","File","http://www.w3.org/2001/XMLSchema-instance");
        $extension->addAttribute("name",$name);
    }

    private function isGenericPaymentText(string $buchungstext): bool
    {
        $buchungstext = trim($buchungstext);

        return $buchungstext === ''
            || in_array($buchungstext, array('Zahlung', 'Manuelle Buchung'), true);
    }

    private function normalizePaymentText(string $buchungstext): string
    {
        $buchungstext = trim($buchungstext);

        if ($buchungstext === '') {
            return '';
        }

        if (stripos($buchungstext, 'Zahlung ') === 0) {
            return $buchungstext;
        }

        return 'Zahlung '.$buchungstext;
    }

    function ExportBuchhaltungList() {
        $submit = $this->app->Secure->GetPOST('submit');
        $von_form = $this->app->Secure->GetPOST("von");
        $bis_form = $this->app->Secure->GetPOST("bis");
        $von = date_create($this->app->erp->ReplaceDatum(true, $von_form, true));
        $bis = date_create($this->app->erp->ReplaceDatum(true, $bis_form, true));
        $projektkuerzel = $this->app->Secure->GetPOST("projekt");
        $projekt = $this->app->erp->ReplaceProjekt(true, $projektkuerzel, true);
        $rgchecked = (bool)$this->app->Secure->GetPOST("rechnung");
        $gschecked = (bool)$this->app->Secure->GetPOST("gutschrift");
        $vbchecked = (bool)$this->app->Secure->GetPOST("verbindlichkeit");
        $lgchecked = (bool)$this->app->Secure->GetPOST("lieferantengutschrift");
        $bankchecked = (bool)$this->app->Secure->GetPOST("bankbuchungen");
        $diffignore = (bool)$this->app->Secure->GetPOST("diffignore");
        $sachkonto = $this->app->Secure->GetPOST('sachkonto');
        $sachkontofehlend = $this->app->Secure->GetPOST('sachkontofehlend');
        $pdfexport = (bool)$this->app->Secure->GetPOST("pdfexport");
        $format = $this->app->Secure->GetPOST('format');
        $buchungsstapel_export = (bool)$this->app->Secure->GetPOST("buchungsstapel_export");
        $stammdaten_export = (bool)$this->app->Secure->GetPOST("stammdaten_export");
        $sachkonto_kennung = null;
        $sachkontofehlend_kennung = null;
        $msg = "";
        // Preload values
        if (empty($submit)) {
            $von = date_create('now')->modify('first day of last month');
            $von_form = $this->app->erp->ReplaceDatum(false,$von->format('Y-m-d'),false);
            $bis = date_create('now')->modify('last day of last month');
            $bis_form = $this->app->erp->ReplaceDatum(false,$bis->format('Y-m-d'),false);
            $rgchecked = true;
            $gschecked = true;
            $vbchecked = true;
            $lgchecked = true;
            $bankchecked = true;
            $buchungsstapel_export = true;
            $stammdaten_export = true;
            $sachkonto = $this->app->User->GetParameter('exportbuchhaltung_sachkonto');
            $sachkontofehlend = $this->app->User->GetParameter('exportbuchhaltung_sachkontofehlend');
            $pdfexport = (bool)$this->app->User->GetParameter('exportbuchhaltung_pdfexport');
        } else {
            $this->app->User->SetParameter('exportbuchhaltung_sachkonto', $sachkonto);
            $this->app->User->SetParameter('exportbuchhaltung_sachkontofehlend', $sachkontofehlend);
            $this->app->User->SetParameter('exportbuchhaltung_pdfexport', $pdfexport);
        }

        if ($buchungsstapel_export) {
            $rgchecked = true;
            $gschecked = true;
            $vbchecked = true;
            $lgchecked = true;
            $bankchecked = true;
        }

        if (!empty($sachkonto)) {
            $sachkonto_kennung = explode(' ', $sachkonto)[0];
        }
        if (!empty($sachkontofehlend)) {
            $sachkontofehlend_kennung = explode(' ', $sachkontofehlend)[0];
        }
        $missing_obligatory = array();
        $buchhaltung_berater = $this->app->erp->Firmendaten('buchhaltung_berater');
        $buchhaltung_mandant = $this->app->erp->Firmendaten('buchhaltung_mandant');
        $buchhaltung_wj_beginn = $this->app->erp->Firmendaten('buchhaltung_wj_beginn');
        $buchhaltung_sachkontenlaenge = $this->app->erp->Firmendaten('buchhaltung_sachkontenlaenge');
        $buchhaltung_berater = $this->app->erp->Firmendaten('buchhaltung_berater');
        if (empty($buchhaltung_berater)) {
            $missing_obligatory[] = "Berater";
        }
        $buchhaltung_mandant = $this->app->erp->Firmendaten('buchhaltung_mandant');
        if (empty($buchhaltung_mandant)) {
            $missing_obligatory[] = "Mandant";
        }
        $buchhaltung_wj_beginn = $this->app->erp->Firmendaten('buchhaltung_wj_beginn');
        if (empty($buchhaltung_wj_beginn)) {
            $missing_obligatory[] = "Wirtschaftsjahr";
        }
        $buchhaltung_sachkontenlaenge = $this->app->erp->Firmendaten('buchhaltung_sachkontenlaenge');
        if (empty($buchhaltung_sachkontenlaenge)) {
            $missing_obligatory[] = "Sachkontenl&auml;nge";
        }
        if (!empty($missing_obligatory)) {
            $msg = "<div class=warning>Angaben in den Grundeinstellungen fehlen: ".implode(", ",$missing_obligatory).".</div>";
        }
        //---------- DOWNLOAD HERE
        if ($submit == 'Download') {
            $dataok = true;
            $export_buchungsstapel = (bool)$buchungsstapel_export;
            $export_stammdaten = (bool)$stammdaten_export;
            $pdfexport = (bool)$pdfexport && $export_buchungsstapel;

            if (!$export_buchungsstapel && !$export_stammdaten) {
                $msg = "<div class=error>Bitte mindestens eine Exportdatei ausw&auml;hlen.</div>";
                $dataok = false;
            }

            if (
              $export_buchungsstapel &&
              !$rgchecked &&
              !$gschecked &&
              !$vbchecked &&
              !$lgchecked &&
              !$bankchecked
            ) {
                $msg = "<div class=error>Bitte mindestens eine Belegart oder Bank/Kasse ausw&auml;hlen.</div>";
                $dataok = false;
            }
            $buchhaltung_wj_beginn_date = null;
            if ($export_buchungsstapel) {
                if (!($von instanceof DateTime) || !($bis instanceof DateTime)) {
                    $msg = "<div class=error>Ung&uuml;ltiger Datumsbereich.</div>";
                    $dataok = false;
                } else {
                    $von_next_year = clone $von;
                    $von_next_year = $von_next_year->modify("+1 year");

                    $buchhaltung_wj_beginn_date = date_create(date_format($von,'Y').$buchhaltung_wj_beginn);
                    if (!($buchhaltung_wj_beginn_date instanceof DateTime)) {
                        $msg = "<div class=error>Ung&uuml;ltiger Datumsbereich.</div>";
                        $dataok = false;
                    } else {
                        if ($buchhaltung_wj_beginn_date > $von) {
                            $buchhaltung_wj_beginn_date = $buchhaltung_wj_beginn_date->modify("-1 year");
                        }

                        $buchhaltung_wj_beginn_next_year = clone $buchhaltung_wj_beginn_date;
                        $buchhaltung_wj_beginn_next_year->modify("+1 year");

                        if ($bis < $von || $bis > $von_next_year || $bis >= $buchhaltung_wj_beginn_next_year) {
                            $msg = "<div class=error>Ung&uuml;ltiger Datumsbereich.</div>";
                            $dataok = false;
                        }
                    }
                }
            }

            if ($dataok) {
                $dateiname_zip_belege_temp = null;
                $dateiname_zip_belege = null;

                try {
                    $export_files = array();
                    $belege = array();
                    $zusaetzliche_buchungen = array();

                    if ($export_buchungsstapel) {
                        $typen = $this->typen($rgchecked, $gschecked, $vbchecked, $lgchecked);
                        foreach ($typen as $typkey => $typvalue) {
                            if (!$typvalue['do']) {
                                continue;
                            }

                            $where = "b.".$typvalue['field_date']." BETWEEN '".date_format($von,"Y-m-d")."' AND '".date_format($bis,"Y-m-d")."' AND (b.projekt=$projekt OR $projekt=0)".$typvalue['condition_where'];
                            $sql = "SELECT
                                b.id,
                                ".$typvalue['field_belegnr']." AS belegnr,
                                ".$typvalue['field_auftrag']." AS auftrag,
                                ".$typvalue['field_zahlweise']." AS zahlweise,
                                IF(".$typvalue['field_kontonummer']." <> '',".$typvalue['field_kontonummer'].",".$typvalue['field_kundennummer'].") AS kundennummer,
                                ".$typvalue['field_name']." AS name,
                                b.ustid ustid,
                                a.ustid ustid_adresse,
                                b.".$typvalue['field_date']." AS datum,
                                ".$typvalue['field_betrag_gesamt']." AS betrag_gesamt,
                                b.waehrung,
                                ".$typvalue['field_land']." AS land
                            FROM
                                ".$typvalue['typ']." b
                                INNER JOIN adresse a ON a.id = b.adresse
                            WHERE
                                ".$where;
                            $belegearr = $this->app->DB->SelectArr($sql);

                            $belege[$typkey] = array(
                                'table' => $typvalue['typ'],
                                'typ' => $typvalue['typ'],
                                'kennzeichen' => $typvalue['kennzeichen'],
                                'kennzeichen_negativ' => $typvalue['kennzeichen_negativ'],
                                'field_gegenkonto' => $typvalue['field_gegenkonto'],
                                'Buchungstyp' => $typvalue['Buchungstyp'],
                                'pdf' => $typvalue['pdf'],
                                'document_type' => $typvalue['document_type'],
                                'belege' => array(),
                            );

                            foreach ($belegearr as $value) {
                                if (empty($value['ustid'])) {
                                    $value['ustid'] = $value['ustid_adresse'];
                                }
                                $belege[$typkey]['belege'][$value['id']] = $value;
                                $belege[$typkey]['belege'][$value['id']]['typ'] = $typvalue['typ'];
                            }

                            $sql_gegenkonto = !empty($typvalue['field_gegenkonto']) ? $typvalue['field_gegenkonto'] : "NULL";
                            $sql = "SELECT
                                b.id AS beleg_id,
                                p.id AS pos_id,
                                ROUND(".$typvalue['field_betrag'].",2) AS betrag,
                                ".$sql_gegenkonto." AS gegenkonto,
                                p.steuersatz AS pos_steuersatz,
                                b.waehrung AS pos_waehrung
                            FROM
                                ".$typvalue['typ']." b
                                LEFT JOIN ".$typvalue['subtable']." p ON b.id = p.".$typvalue['typ']."
                            WHERE
                                ".$where;
                            $posarr = $this->app->DB->SelectArr($sql);

                            foreach ($posarr as $pos) {
                                $tmpsteuersatz = null;
                                $tmpsteuertext = '';
                                $erloes = '';
                                $this->app->erp->GetSteuerPosition($typvalue['typ'], $pos['pos_id'], $tmpsteuersatz, $tmpsteuertext, $erloes);
                                if ($tmpsteuersatz === null && $pos['pos_steuersatz'] !== null && $pos['pos_steuersatz'] !== '') {
                                    $tmpsteuersatz = (float)$pos['pos_steuersatz'];
                                }
                                if ($tmpsteuersatz === null) {
                                    $tmpsteuersatz = 0;
                                }
                                $pos['steuersatz'] = $tmpsteuersatz;
                                $pos['erloes'] = $erloes;

                                if (!isset($belege[$typkey]['belege'][$pos['beleg_id']])) {
                                    continue;
                                }
                                $belege[$typkey]['belege'][$pos['beleg_id']]['positionen'][] = $pos;
                            }
                        }

                        if ($bankchecked) {
                            $zusaetzliche_buchungen = $this->collectDatevZusatzbuchungen($von, $bis, $projekt);
                        }

                        if ($pdfexport) {
                            $dateiname_zip_belege_temp = $this->app->erp->GetTMP().uniqid("Export_Buchhaltung_Belege_", true).".zip";
                            $dateiname_zip_belege = 'Export_Buchhaltung_Belege_'.date('Y-m-d').'.zip';
                            if ($this->zipbelege->open($dateiname_zip_belege_temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                                throw new \RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
                            }

                            foreach ($belege as $typ => $belege_zu_typ) {
                                foreach ($belege_zu_typ['belege'] as $beleg_key => $beleg) {
                                    $allowed_file_types = array('pdf', 'xml');
                                    $allowed_link_file_types = array('pdf');
                                    $action = $belege_zu_typ['pdf'];

                                    if ($belege_zu_typ['typ'] === 'rechnung' && $this->app->DB->Select("SELECT xmlrechnung FROM rechnung WHERE id = ".$beleg['id'])) {
                                        $action = 'load';
                                        $allowed_link_file_types = array('xml');
                                    }

                                    switch ($action) {
                                        case 'print':
                                            switch ($belege_zu_typ['typ']) {
                                                case 'rechnung':
                                                    if (class_exists('GutschriftPDFCustom')) {
                                                        $Brief = new RechnungPDFCustom($this->app, $projekt);
                                                    } else {
                                                        $Brief = new RechnungPDF($this->app, $projekt);
                                                    }
                                                    $Brief->GetRechnung($beleg['id']);
                                                break;
                                                case 'gutschrift':
                                                    if (class_exists('RechnungPDFCustom')) {
                                                        $Brief = new GutschriftPDFCustom($this->app, $projekt);
                                                    } else {
                                                        $Brief = new GutschriftPDF($this->app, $projekt);
                                                    }
                                                    $Brief->GetGutschrift($beleg['id']);
                                                break;
                                                default:
                                                    $this->app->Tpl->AddMessage('error', "Belegdatei nicht geladen, Druckvorgang fehlgeschlagen: ".$beleg['belegnr']);
                                                    $dataok = false;
                                                break;
                                            }

                                            if ($dataok) {
                                                $tmpfile = $Brief->displayTMP();
                                                $file_name = $beleg['belegnr'].".pdf";
                                                $guid = $this->app->DB->Select("SELECT UUID() uuid FROM DUAL");
                                                $this->addfile(ucfirst($belege_zu_typ['typ'])."_".$file_name, file_get_contents($tmpfile), $guid, $belege_zu_typ['document_type']);
                                                if (empty($belege[$typ]['belege'][$beleg_key]['guid'])) {
                                                    $belege[$typ]['belege'][$beleg_key]['guid'] = $guid;
                                                }
                                            }
                                        break;
                                        case 'load':
                                            $file_ids = $this->app->erp->GetDateiSubjektObjekt('%', $belege_zu_typ['typ'], $beleg['id']);
                                            $suffix = "";
                                            $count = 0;
                                            foreach ($file_ids as $file_id) {
                                                $ending = strtolower($this->app->erp->GetDateiEndung($file_id));
                                                if (!in_array($ending, $allowed_file_types, true)) {
                                                    continue;
                                                }

                                                $file_contents = $this->app->erp->GetDatei($file_id);
                                                $file_name = filter_var($beleg['belegnr'], FILTER_SANITIZE_EMAIL).$suffix.".".$ending;
                                                $guid = $this->app->DB->Select("SELECT UUID() uuid FROM DUAL");
                                                $this->addfile(ucfirst($belege_zu_typ['typ'])."_".$file_name, $file_contents, $guid, $belege_zu_typ['document_type']);
                                                $count++;
                                                $suffix = "_".$count;

                                                if (empty($belege[$typ]['belege'][$beleg_key]['guid']) && in_array($ending, $allowed_link_file_types, true)) {
                                                    $belege[$typ]['belege'][$beleg_key]['guid'] = $guid;
                                                }
                                            }
                                        break;
                                        default:
                                            $this->app->Tpl->AddMessage('error', "Belegdatei nicht geladen: ".$beleg['belegnr']);
                                            $dataok = false;
                                        break;
                                    }

                                    if (empty($belege[$typ]['belege'][$beleg_key]['guid'])) {
                                        $this->app->Tpl->AddMessage('error', "Belegdatei fehlt: ".$beleg['belegnr']);
                                        $dataok = false;
                                    }
                                }
                            }

                            $dom = new \DOMDocument('1.0');
                            $dom->loadXML($this->document_xml->asXML());
                            $dom->encoding = 'UTF-8';
                            $dom->preserveWhiteSpace = true;
                            $dom->formatOutput = true;
                            $xml_pretty = $dom->saveXML();
                            $this->zipbelege->addFromString("document.xml", $xml_pretty);

                            if (!$this->zipbelege->close()) {
                                throw new \RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
                            }
                            clearstatcache(true, $dateiname_zip_belege_temp);
                        }
                    }

                    if ($dataok && $export_buchungsstapel) {
                        $filename_csv = "EXTF_".date('Ymd')."_Buchungsstapel_DATEV_export.csv";
                        $export_files[$filename_csv] = DatevExport::createBuchungsstapelCSV(
                            beleg_data: $belege,
                            berater: $buchhaltung_berater,
                            mandant: $buchhaltung_mandant,
                            bearbeiter: $this->getDatevUserKuerzel(),
                            wj_beginn: $buchhaltung_wj_beginn_date,
                            sachkontenlaenge: (int)$buchhaltung_sachkontenlaenge,
                            von: $von,
                            bis: $bis,
                            filename: $filename_csv,
                            diffignore: $diffignore,
                            sachkonto_differences: $sachkonto_kennung,
                            sachkonto_missing: $sachkontofehlend_kennung,
                            format: $format,
                            zusaetzliche_buchungen: $zusaetzliche_buchungen,
                            include_testbuchung: ((int)$this->app->erp->Firmendaten('neuesdatevformattestbuchung') === 1)
                        );
                    }

                    if ($dataok && $export_stammdaten) {
                        $filename_stammdaten = "EXTF_".date('Ymd')."_Stammdaten_DebitorenKreditoren_DATEV_export.csv";
                        $export_files[$filename_stammdaten] = DatevExport::createDebitorenKreditorenStammdatenCSV(
                            adress_rows: $this->collectDatevStammdatenRows($projekt),
                            berater: $buchhaltung_berater,
                            mandant: $buchhaltung_mandant,
                            wirtschaftsjahr_beginn: $buchhaltung_wj_beginn,
                            sachkontenlaenge: (int)$buchhaltung_sachkontenlaenge,
                            bearbeiter: $this->getDatevUserKuerzel(),
                            filename: $filename_stammdaten,
                            format: $format
                        );
                    }

                    if ($dataok && !empty($export_files)) {
                        if ($pdfexport || count($export_files) > 1) {
                            $dateinamezip = 'Export_Buchhaltung_'.date('Y-m-d').'.zip';
                            $dateinamezip_temp = $this->app->erp->GetTMP().uniqid("Export_Buchhaltung_", true).".zip";
                            $zip = new ZipArchive;
                            if ($zip->open($dateinamezip_temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                                throw new \RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
                            }

                            foreach ($export_files as $filename => $contents) {
                                $zip->addFromString($filename, $contents);
                            }

                            if ($pdfexport && $dateiname_zip_belege_temp !== null && file_exists($dateiname_zip_belege_temp)) {
                                if (!$zip->addFile($dateiname_zip_belege_temp, $dateiname_zip_belege)) {
                                    @unlink($dateinamezip_temp);
                                    throw new \RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
                                }
                            }

                            if (!$zip->close()) {
                                @unlink($dateinamezip_temp);
                                throw new \RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
                            }

                            clearstatcache(true, $dateinamezip_temp);
                            header('Content-Type: application/zip');
                            header("Content-Disposition: attachment; filename=$dateinamezip");
                            header('Content-Length: ' . filesize($dateinamezip_temp));
                            readfile($dateinamezip_temp);
                            unlink($dateinamezip_temp);
                            if ($dateiname_zip_belege_temp !== null && file_exists($dateiname_zip_belege_temp)) {
                                unlink($dateiname_zip_belege_temp);
                            }
                        } else {
                            reset($export_files);
                            $single_filename = key($export_files);
                            $single_contents = current($export_files);

                            header("Content-Disposition: attachment; filename=" . $single_filename);
                            header("Pragma: no-cache");
                            header("Expires: 0");
                            echo $single_contents;
                        }

                        $this->app->ExitXentral();
                    }
                } catch (ConsistencyException $e) {
                    if ($dateiname_zip_belege_temp !== null && file_exists($dateiname_zip_belege_temp)) {
                        unlink($dateiname_zip_belege_temp);
                    }

                    $msg = "<div class=error>Inkonsistente Daten";
                    if ($e->getMessage() !== '') {
                        $msg .= " (".$e->getMessage().")";
                    }
                    $msg .= ": <br>";

                    $data = $e->getData();
                    $count = 0;
                    foreach ($data as $item) {
                        $msg .= ucfirst($item['typ'])." ".$item['belegnr']." (Kopf ".$this->app->erp->ReplaceMengeBetrag(false, $item['betrag_gesamt'], false)." Positionen ".$this->app->erp->ReplaceMengeBetrag(false, $item['betrag_summe'], false).")<br>";
                        $count++;
                        if ($count == 10) {
                            $msg .= "...";
                            break;
                        }
                    }
                    $msg .= "</div>";
                } catch (\RuntimeException $e) {
                    if ($dateiname_zip_belege_temp !== null && file_exists($dateiname_zip_belege_temp)) {
                        unlink($dateiname_zip_belege_temp);
                    }
                    $msg = "<div class=error>".$e->getMessage()."</div>";
                }
            }
        }
        //---------- DOWNLOAD HERE
        $this->app->erp->MenuEintrag("index.php?module=exportbuchhaltung&action=export", "&Uuml;bersicht");
        $this->app->erp->MenuEintrag("index.php?module=importvorlage&action=uebersicht", "Zur&uuml;ck");
        $this->app->YUI->AutoComplete("projekt", "projektname", 1);
        $this->app->YUI->DatePicker("von");
        $this->app->YUI->DatePicker("bis");
        $this->app->YUI->AutoComplete('sachkonto', 'sachkonto');
        $this->app->YUI->AutoComplete('sachkontofehlend', 'sachkonto');
        $this->app->Tpl->ADD('MESSAGE', $msg);
        $this->app->Tpl->SET('RGCHECKED',$rgchecked?'checked':'');
        $this->app->Tpl->SET('GSCHECKED',$gschecked?'checked':'');
        $this->app->Tpl->SET('VBCHECKED',$vbchecked?'checked':'');
        $this->app->Tpl->SET('LGCHECKED',$lgchecked?'checked':'');
        $this->app->Tpl->SET('BANKCHECKED',$bankchecked?'checked':'');
        $this->app->Tpl->SET('DIFFIGNORE',$diffignore?'checked':'');
        $this->app->Tpl->SET('PDFEXPORT',$pdfexport?'checked':'');
        $this->app->Tpl->SET('BUCHUNGSSTAPELEXPORT',$buchungsstapel_export?'checked':'');
        $this->app->Tpl->SET('STAMMDATENEXPORT',$stammdaten_export?'checked':'');
        $this->app->Tpl->SET('VON', $von_form);
        $this->app->Tpl->SET('BIS', $bis_form);
        $this->app->Tpl->SET('PROJEKT', $projektkuerzel);
        $this->app->Tpl->SET('SACHKONTO', $sachkonto);
        $this->app->Tpl->SET('SACHKONTOFEHLEND', $sachkontofehlend);
        $this->app->Tpl->Parse('PAGE', "exportbuchhaltung_export.tpl");
    }
    private function getDatevUserKuerzel(): string
    {
        $usernamearr = explode(' ', strtoupper($this->app->User->GetName()." X X"));
        if (count($usernamearr) < 2) {
            return $usernamearr[0][0].$usernamearr[0][1];
        }

        return $usernamearr[0][0].$usernamearr[1][0];
    }

    private function collectDatevStammdatenRows(int $projekt = 0): array
    {
        $sql = "SELECT
            TRIM(COALESCE(NULLIF(a.kundennummer_buchhaltung, ''), NULLIF(a.kundennummer, ''))) AS debitorenkonto,
            TRIM(COALESCE(NULLIF(a.lieferantennummer_buchhaltung, ''), NULLIF(a.lieferantennummer, ''))) AS kreditorenkonto,
            a.name,
            a.strasse,
            a.plz,
            a.ort,
            a.land,
            a.ustid,
            a.adresszusatz,
            a.telefon,
            a.email,
            a.telefax
        FROM adresse a
        WHERE
            a.geloescht = 0
            AND (a.projekt = ".$projekt." OR ".$projekt." = 0)
            AND (
                TRIM(COALESCE(NULLIF(a.kundennummer_buchhaltung, ''), NULLIF(a.kundennummer, ''))) <> ''
                OR TRIM(COALESCE(NULLIF(a.lieferantennummer_buchhaltung, ''), NULLIF(a.lieferantennummer, ''))) <> ''
            )
        ORDER BY a.name";

        return $this->app->DB->SelectArr($sql);
    }

    private function collectDatevZusatzbuchungen(DateTime $von, DateTime $bis, int $projekt = 0): array
    {
        return array_merge(
            $this->collectDatevZahlungsverkehrBuchungen($von, $bis, $projekt),
            $this->collectDatevDialogbuchungen($von, $bis, $projekt)
        );
    }

    private function collectDatevZahlungsverkehrBuchungen(DateTime $von, DateTime $bis, int $projekt = 0): array
    {
        $sql = "SELECT
            fb.id,
            fb.datum,
            fb.betrag,
            fb.waehrung,
            fb.von_typ,
            fb.von_id,
            fb.nach_typ,
            fb.nach_id,
            COALESCE(
                NULLIF(r.belegnr, ''),
                NULLIF(g.belegnr, ''),
                NULLIF(v.buha_belegfeld1, ''),
                NULLIF(v.rechnung, ''),
                NULLIF(v.belegnr, '')
            ) AS belegnr,
            COALESCE(NULLIF(ar.kundennummer_buchhaltung, ''), NULLIF(ag.kundennummer_buchhaltung, '')) AS debitor,
            COALESCE(NULLIF(ar.kundennummer, ''), NULLIF(ag.kundennummer, '')) AS debitor_fallback,
            av.lieferantennummer_buchhaltung AS kreditor,
            av.lieferantennummer AS kreditor_fallback,
            kr.sachkonto AS sachkonto,
            kb.datevkonto AS bank_datev,
            kk.datevkonto AS kasse_datev,
            fb.internebemerkung AS intern,
            kz.buchungstext AS kontoauszug_buchungstext,
            fb.buchungsschluessel AS buchungsschluessel,
            r.projekt AS rechnung_projekt,
            g.projekt AS gutschrift_projekt,
            v.projekt AS verbindlichkeit_projekt,
            ka.projekt AS kasse_projekt,
            kb.projekt AS bank_projekt,
            kk.projekt AS kassekonten_projekt,
            kr.projekt AS kontorahmen_projekt
        FROM
            fibu_buchungen fb
            LEFT JOIN rechnung r
                ON (fb.von_typ='rechnung' AND fb.von_id=r.id)
                OR (fb.nach_typ='rechnung' AND fb.nach_id=r.id)
            LEFT JOIN adresse ar ON ar.id = r.adresse
            LEFT JOIN gutschrift g
                ON (fb.von_typ='gutschrift' AND fb.von_id=g.id)
                OR (fb.nach_typ='gutschrift' AND fb.nach_id=g.id)
            LEFT JOIN adresse ag ON ag.id = g.adresse
            LEFT JOIN verbindlichkeit v
                ON (fb.von_typ='verbindlichkeit' AND fb.von_id=v.id)
                OR (fb.nach_typ='verbindlichkeit' AND fb.nach_id=v.id)
            LEFT JOIN adresse av ON av.id = v.adresse
            LEFT JOIN kontorahmen kr
                ON (fb.von_typ='kontorahmen' AND fb.von_id=kr.id)
                OR (fb.nach_typ='kontorahmen' AND fb.nach_id=kr.id)
            LEFT JOIN kontoauszuege kz
                ON (fb.von_typ='kontoauszuege' AND fb.von_id=kz.id)
                OR (fb.nach_typ='kontoauszuege' AND fb.nach_id=kz.id)
            LEFT JOIN konten kb ON kb.id = kz.konto
            LEFT JOIN kasse ka
                ON (fb.von_typ='kasse' AND fb.von_id=ka.id)
                OR (fb.nach_typ='kasse' AND fb.nach_id=ka.id)
            LEFT JOIN konten kk ON kk.id = ka.konto
        WHERE
            fb.datum BETWEEN '".date_format($von,"Y-m-d")."' AND '".date_format($bis,"Y-m-d")."'
            AND (fb.von_typ IN ('kontoauszuege','kasse') OR fb.nach_typ IN ('kontoauszuege','kasse'))
            AND (
                $projekt = 0
                OR r.projekt = $projekt
                OR g.projekt = $projekt
                OR v.projekt = $projekt
                OR ka.projekt = $projekt
                OR kb.projekt = $projekt
                OR kk.projekt = $projekt
                OR kr.projekt = $projekt
            )";

        $buchungen = array();
        $zahlungen = $this->app->DB->SelectArr($sql);
        foreach ($zahlungen as $row) {
            $geldkonto = !empty($row['bank_datev']) ? $row['bank_datev'] : $row['kasse_datev'];
            if (empty($geldkonto)) {
                continue;
            }

            $debitor = !empty($row['debitor']) ? $row['debitor'] : $row['debitor_fallback'];
            $kreditor = !empty($row['kreditor']) ? $row['kreditor'] : $row['kreditor_fallback'];
            if (!empty($debitor)) {
                $gegenkonto = $debitor;
            } elseif (!empty($kreditor)) {
                $gegenkonto = $kreditor;
            } else {
                $gegenkonto = !empty($row['sachkonto']) ? $row['sachkonto'] : '9999';
            }

            $money_in = in_array($row['nach_typ'], array('kontoauszuege', 'kasse'), true);
            $kennzeichen = $money_in ? 'S' : 'H';
            if ((float)$row['betrag'] < 0) {
                $kennzeichen = ($kennzeichen === 'S') ? 'H' : 'S';
            }

            $betrag = abs((float)$row['betrag']);
            if ($betrag == 0.0) {
                continue;
            }

            $belegfeld1 = !empty($row['belegnr']) ? $row['belegnr'] : ('FB'.$row['id']);
            $internerZahlungstext = trim((string)$row['intern']);
            $kontoauszugZahlungstext = $this->normalizePaymentText((string)$row['kontoauszug_buchungstext']);
            if (empty($debitor) && empty($kreditor) && empty($row['sachkonto'])) {
                $buchungstext = 'Vorkasse/ohne Beleg';
            } elseif (!empty($row['belegnr'])) {
                $buchungstext = 'Zahlung '.$row['belegnr'];
            } elseif (!$this->isGenericPaymentText($internerZahlungstext)) {
                $buchungstext = $internerZahlungstext;
            } elseif ($kontoauszugZahlungstext !== '') {
                $buchungstext = $kontoauszugZahlungstext;
            } elseif ($internerZahlungstext !== '') {
                $buchungstext = $internerZahlungstext;
            } else {
                $buchungstext = 'Zahlung';
            }

            $buchungen[] = array(
                'Umsatz' => number_format($betrag, 2, ',', ''),
                'Soll-/Haben-Kennzeichen' => $kennzeichen,
                'WKZ Umsatz' => $row['waehrung'],
                'Konto' => $geldkonto,
                'Gegenkonto (ohne BU-Schlüssel)' => $gegenkonto,
                'BU-Schlüssel' => $row['buchungsschluessel'],
                'Belegdatum' => date_format(date_create($row['datum']), "dm"),
                'Belegfeld 1' => mb_strimwidth($belegfeld1, 0, 36),
                'Belegfeld 2' => 'FB'.$row['id'],
                'Buchungstext' => mb_strimwidth($buchungstext, 0, 60),
            );
        }

        return $buchungen;
    }

    private function collectDatevDialogbuchungen(DateTime $von, DateTime $bis, int $projekt = 0): array
    {
        $sql = "SELECT
            fb.id,
            fb.datum,
            fb.betrag,
            fb.waehrung,
            fb.internebemerkung AS intern,
            fb.buchungsschluessel AS buchungsschluessel,
            kr_soll.sachkonto AS sollkonto,
            kr_haben.sachkonto AS habenkonto,
            kr_soll.projekt AS sollkonto_projekt,
            kr_haben.projekt AS habenkonto_projekt
        FROM
            fibu_buchungen fb
            INNER JOIN kontorahmen kr_soll
                ON fb.von_typ = 'kontorahmen'
                AND fb.von_id = kr_soll.id
            INNER JOIN kontorahmen kr_haben
                ON fb.nach_typ = 'kontorahmen'
                AND fb.nach_id = kr_haben.id
        WHERE
            fb.datum BETWEEN '".date_format($von,"Y-m-d")."' AND '".date_format($bis,"Y-m-d")."'
            AND fb.von_typ = 'kontorahmen'
            AND fb.nach_typ = 'kontorahmen'
            AND (
                $projekt = 0
                OR kr_soll.projekt = $projekt
                OR kr_haben.projekt = $projekt
            )";

        $buchungen = array();
        $dialogbuchungen = $this->app->DB->SelectArr($sql);
        foreach ($dialogbuchungen as $row) {
            $betragRaw = (float)$row['betrag'];
            $betrag = abs($betragRaw);
            if ($betrag == 0.0) {
                continue;
            }

            $konto = $row['sollkonto'];
            $gegenkonto = $row['habenkonto'];
            $kennzeichen = 'S';

            if ($betragRaw < 0) {
                $konto = $row['habenkonto'];
                $gegenkonto = $row['sollkonto'];
            }

            if (empty($konto) || empty($gegenkonto)) {
                continue;
            }

            $buchungstext = !empty($row['intern']) ? $row['intern'] : 'Manuelle Buchung';
            $belegfeld1 = 'FB'.$row['id'];

            $buchungen[] = array(
                'Umsatz' => number_format($betrag, 2, ',', ''),
                'Soll-/Haben-Kennzeichen' => $kennzeichen,
                'WKZ Umsatz' => $row['waehrung'],
                'Konto' => $konto,
                'Gegenkonto (ohne BU-Schlüssel)' => $gegenkonto,
                'BU-Schlüssel' => $row['buchungsschluessel'],
                'Belegdatum' => date_format(date_create($row['datum']), "dm"),
                'Belegfeld 1' => mb_strimwidth($belegfeld1, 0, 36),
                'Belegfeld 2' => 'FB'.$row['id'],
                'Buchungstext' => mb_strimwidth($buchungstext, 0, 60),
            );
        }

        return $buchungen;
    }
}
