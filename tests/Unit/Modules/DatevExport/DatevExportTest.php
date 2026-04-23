<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\DatevExport;

use DateTime;
use PHPUnit\Framework\TestCase;
use Xentral\Modules\DatevExport\DatevExport;

require_once dirname(__DIR__, 4).'/classes/Modules/DatevExport/DatevExport.php';

final class DatevExportTest extends TestCase
{
    public function testCreateBuchungsstapelCsvSortsRowsGloballyByDate(): void
    {
        $csv = DatevExport::createBuchungsstapelCSV(
            $this->createRechnungBelegData('2026-01-09', 109.00, 109.00, '2026-400010', 'Marion Kripfgans'),
            '1234',
            '5678',
            'JK',
            new DateTime('2026-01-01'),
            4,
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            format: 'UTF-8',
            zusaetzliche_buchungen: array(
                array(
                    'Umsatz' => '109,00',
                    'Soll-/Haben-Kennzeichen' => 'S',
                    'WKZ Umsatz' => 'EUR',
                    'Konto' => '1230',
                    'Gegenkonto (ohne BU-Schlüssel)' => '10025',
                    'BU-Schlüssel' => '',
                    'Belegdatum' => '0301',
                    'Belegfeld 1' => '2026-400010',
                    'Belegfeld 2' => 'FB36',
                    'Buchungstext' => 'Zahlung 2026-400010',
                    'export_date' => '2026-01-03',
                    'row_group' => 30,
                    'source_id' => 36,
                    'source_type' => 'zahlungsverkehr',
                ),
                array(
                    'Umsatz' => '3,10',
                    'Soll-/Haben-Kennzeichen' => 'H',
                    'WKZ Umsatz' => 'EUR',
                    'Konto' => '1230',
                    'Gegenkonto (ohne BU-Schlüssel)' => '4970',
                    'BU-Schlüssel' => '',
                    'Belegdatum' => '0301',
                    'Belegfeld 1' => 'PAYPAL-FEE',
                    'Belegfeld 2' => 'FB37',
                    'Buchungstext' => 'PayPal-Gebühr',
                    'export_date' => '2026-01-03',
                    'row_group' => 40,
                    'source_id' => 37,
                    'source_type' => 'belegsachkonto',
                ),
            )
        );

        $buchungszeilen = $this->getBuchungszeilen($csv);

        self::assertSame(
            array('Zahlung 2026-400010', 'PayPal-Gebühr', 'Marion Kripfgans'),
            array_column($buchungszeilen, 'Buchungstext')
        );
        self::assertSame(array('0301', '0301', '0901'), array_column($buchungszeilen, 'Belegdatum'));
    }

    public function testCreateBuchungsstapelCsvKeepsDifferenceBeforeAdditionalRowsOnSameDate(): void
    {
        $csv = DatevExport::createBuchungsstapelCSV(
            $this->createRechnungBelegData('2026-01-03', 119.00, 100.00, '2026-400010', 'Marion Kripfgans'),
            '1234',
            '5678',
            'JK',
            new DateTime('2026-01-01'),
            4,
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            diffignore: false,
            sachkonto_differences: '9999',
            format: 'UTF-8',
            zusaetzliche_buchungen: array(
                array(
                    'Umsatz' => '109,00',
                    'Soll-/Haben-Kennzeichen' => 'S',
                    'WKZ Umsatz' => 'EUR',
                    'Konto' => '1230',
                    'Gegenkonto (ohne BU-Schlüssel)' => '10025',
                    'BU-Schlüssel' => '',
                    'Belegdatum' => '0301',
                    'Belegfeld 1' => '2026-400010',
                    'Belegfeld 2' => 'FB36',
                    'Buchungstext' => 'Zahlung 2026-400010',
                    'export_date' => '2026-01-03',
                    'row_group' => 30,
                    'source_id' => 36,
                    'source_type' => 'zahlungsverkehr',
                ),
            )
        );

        $buchungszeilen = $this->getBuchungszeilen($csv);

        self::assertSame(
            array('Marion Kripfgans', 'Differenz', 'Zahlung 2026-400010'),
            array_column($buchungszeilen, 'Buchungstext')
        );
        self::assertSame('9999', $buchungszeilen[1]['Gegenkonto (ohne BU-Schlüssel)']);
    }

    public function testCreateBuchungsstapelCsvUsesUstIdInEuDestinationFieldForInvoices(): void
    {
        $csv = DatevExport::createBuchungsstapelCSV(
            $this->createRechnungBelegData(
                '2026-01-09',
                109.00,
                109.00,
                '2026-400010',
                'Marion Kripfgans',
                'FR12345678901',
                'FR'
            ),
            '1234',
            '5678',
            'JK',
            new DateTime('2026-01-01'),
            4,
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            format: 'UTF-8',
            ursprung_ustid: 'DE123456789'
        );

        $buchungszeilen = $this->getBuchungszeilen($csv);

        self::assertSame('FR12345678901', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Bestimmung)']);
        self::assertSame('DE123456789', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Ursprung)']);
        self::assertSame('', $buchungszeilen[0]['Land']);
    }

    public function testCreateBuchungsstapelCsvKeepsOriginUstIdEmptyWhenInvoiceHasNoUstIdAndIsNotMarkedAsEuDestination(): void
    {
        $csv = DatevExport::createBuchungsstapelCSV(
            $this->createRechnungBelegData(
                '2026-01-09',
                109.00,
                109.00,
                '2026-400010',
                'Marion Kripfgans',
                '',
                'FR'
            ),
            '1234',
            '5678',
            'JK',
            new DateTime('2026-01-01'),
            4,
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            format: 'UTF-8',
            ursprung_ustid: 'DE123456789'
        );

        $buchungszeilen = $this->getBuchungszeilen($csv);

        self::assertSame('FR', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Bestimmung)']);
        self::assertSame('', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Ursprung)']);
        self::assertSame('', $buchungszeilen[0]['Land']);
    }

    public function testCreateBuchungsstapelCsvUsesOriginUstIdForEuInvoicesWithoutDestinationUstId(): void
    {
        $csv = DatevExport::createBuchungsstapelCSV(
            $this->createRechnungBelegData(
                '2026-01-09',
                109.00,
                109.00,
                '2026-400011',
                'Marion Kripfgans',
                '',
                'FR',
                true
            ),
            '1234',
            '5678',
            'JK',
            new DateTime('2026-01-01'),
            4,
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            format: 'UTF-8',
            ursprung_ustid: 'DE123456789'
        );

        $buchungszeilen = $this->getBuchungszeilen($csv);

        self::assertSame('FR', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Bestimmung)']);
        self::assertSame('DE123456789', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Ursprung)']);
        self::assertSame('', $buchungszeilen[0]['Land']);
    }

    public function testCreateBuchungsstapelCsvUsesUstIdInEuDestinationFieldForCreditNotes(): void
    {
        $csv = DatevExport::createBuchungsstapelCSV(
            $this->createGutschriftBelegData(
                '2026-01-09',
                109.00,
                109.00,
                '2026-900010',
                'Marion Kripfgans',
                'FR12345678901',
                'FR'
            ),
            '1234',
            '5678',
            'JK',
            new DateTime('2026-01-01'),
            4,
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            format: 'UTF-8',
            ursprung_ustid: 'DE123456789'
        );

        $buchungszeilen = $this->getBuchungszeilen($csv);

        self::assertSame('FR12345678901', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Bestimmung)']);
        self::assertSame('DE123456789', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Ursprung)']);
        self::assertSame('', $buchungszeilen[0]['Land']);
    }

    public function testCreateBuchungsstapelCsvUsesOriginUstIdForEuCreditNotesWithoutDestinationUstId(): void
    {
        $csv = DatevExport::createBuchungsstapelCSV(
            $this->createGutschriftBelegData(
                '2026-01-09',
                109.00,
                109.00,
                '2026-900011',
                'Marion Kripfgans',
                '',
                'AT',
                true
            ),
            '1234',
            '5678',
            'JK',
            new DateTime('2026-01-01'),
            4,
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            format: 'UTF-8',
            ursprung_ustid: 'DE204161186'
        );

        $buchungszeilen = $this->getBuchungszeilen($csv);

        self::assertSame('AT', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Bestimmung)']);
        self::assertSame('DE204161186', $buchungszeilen[0]['EU-Mitgliedstaat u. UStID (Ursprung)']);
        self::assertSame('', $buchungszeilen[0]['Land']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function createGutschriftBelegData(
        string $datum,
        float $betragGesamt,
        float $positionsbetrag,
        string $belegnr,
        string $name,
        string $ustid = '',
        string $land = 'DE',
        bool $isEuDestination = false
    ): array {
        return array(
            'gutschrift' => array(
                'typ' => 'gutschrift',
                'kennzeichen' => 'H',
                'kennzeichen_negativ' => 'S',
                'field_gegenkonto' => null,
                'Buchungstyp' => '',
                'belege' => array(
                    36 => array(
                        'id' => 36,
                        'belegnr' => $belegnr,
                        'auftrag' => 0,
                        'kundennummer' => '10025',
                        'name' => $name,
                        'ustid' => $ustid,
                        'datum' => $datum,
                        'betrag_gesamt' => $betragGesamt,
                        'waehrung' => 'EUR',
                        'land' => $land,
                        'is_eu_destination' => $isEuDestination,
                        'positionen' => array(
                            array(
                                'pos_id' => 1,
                                'betrag' => $positionsbetrag,
                                'erloes' => '8400',
                                'steuersatz' => 19.0,
                                'pos_waehrung' => 'EUR',
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function createRechnungBelegData(
        string $datum,
        float $betragGesamt,
        float $positionsbetrag,
        string $belegnr,
        string $name,
        string $ustid = '',
        string $land = 'DE',
        bool $isEuDestination = false
    ): array {
        return array(
            'rechnung' => array(
                'typ' => 'rechnung',
                'kennzeichen' => 'S',
                'kennzeichen_negativ' => 'H',
                'field_gegenkonto' => null,
                'Buchungstyp' => 'SR',
                'belege' => array(
                    35 => array(
                        'id' => 35,
                        'belegnr' => $belegnr,
                        'auftrag' => 0,
                        'kundennummer' => '10025',
                        'name' => $name,
                        'ustid' => $ustid,
                        'datum' => $datum,
                        'betrag_gesamt' => $betragGesamt,
                        'waehrung' => 'EUR',
                        'land' => $land,
                        'is_eu_destination' => $isEuDestination,
                        'positionen' => array(
                            array(
                                'pos_id' => 1,
                                'betrag' => $positionsbetrag,
                                'erloes' => '8400',
                                'steuersatz' => 19.0,
                                'pos_waehrung' => 'EUR',
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getBuchungszeilen(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        self::assertIsArray($lines);
        self::assertGreaterThanOrEqual(3, count($lines));

        $header = str_getcsv($lines[1], ';', '"');
        self::assertIsArray($header);

        $rows = array();
        foreach (array_slice($lines, 2) as $line) {
            if ($line === '') {
                continue;
            }

            $values = str_getcsv($line, ';', '"');
            self::assertIsArray($values);
            $row = array_combine($header, $values);
            self::assertIsArray($row);
            $rows[] = $row;
        }

        return $rows;
    }
}
