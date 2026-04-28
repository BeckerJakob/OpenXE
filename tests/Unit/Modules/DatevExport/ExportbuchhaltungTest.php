<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\DatevExport;

use DateTime;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4).'/www/pages/exportbuchhaltung.php';

final class ExportbuchhaltungTest extends TestCase
{
    public function testCollectDatevZahlungsverkehrBuchungenUsesExportDateSourcesForQueryAndBelegdatum(): void
    {
        $database = new ExportbuchhaltungDatabaseStub(array(
            array(
                'id' => 36,
                'fibu_datum' => '2026-01-09',
                'export_datum' => '2026-01-03',
                'betrag' => '109.00',
                'waehrung' => 'EUR',
                'von_typ' => 'rechnung',
                'von_id' => 35,
                'nach_typ' => 'kontoauszuege',
                'nach_id' => 291,
                'belegnr' => '2026-400010',
                'debitor' => '',
                'debitor_beleg' => '10025',
                'debitor_fallback' => '20025',
                'kreditor' => '',
                'kreditor_fallback' => '',
                'sachkonto' => '',
                'bank_datev' => '1230',
                'kasse_datev' => '',
                'intern' => 'Zahlung',
                'kontoauszug_buchungstext' => 'PayPal',
                'buchungsschluessel' => '',
            ),
        ));

        $exportbuchhaltung = $this->createExportbuchhaltung($database);

        $buchungen = $this->invokePrivateMethod(
            $exportbuchhaltung,
            'collectDatevZahlungsverkehrBuchungen',
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            0
        );

        self::assertCount(1, $buchungen);
        self::assertSame('0301', $buchungen[0]['Belegdatum']);
        self::assertSame('1230', $buchungen[0]['Konto']);
        self::assertSame('10025', $buchungen[0]['Gegenkonto (ohne BU-Schlüssel)']);
        self::assertSame('Zahlung 2026-400010', $buchungen[0]['Buchungstext']);
        self::assertSame('2026-01-03', $buchungen[0]['export_date']);
        self::assertSame(30, $buchungen[0]['row_group']);
        self::assertSame(36, $buchungen[0]['source_id']);
        self::assertSame('zahlungsverkehr', $buchungen[0]['source_type']);
        self::assertStringContainsString("NULLIF(kz.buchung, '0000-00-00')", $database->lastQuery);
        self::assertStringContainsString("NULLIF(ka.belegdatum, '0000-00-00')", $database->lastQuery);
        self::assertStringContainsString("NULLIF(ka.datum, '0000-00-00')", $database->lastQuery);
        self::assertStringContainsString('ORDER BY export_datum, fb.id', $database->lastQuery);
        self::assertStringNotContainsString('fb.datum BETWEEN', $database->lastQuery);
    }

    public function testCollectDatevZahlungsverkehrBuchungenUsesKasseDatevAccountAndExportDate(): void
    {
        $database = new ExportbuchhaltungDatabaseStub(array(
            array(
                'id' => 77,
                'fibu_datum' => '2026-02-20',
                'export_datum' => '2026-02-15',
                'betrag' => '55.50',
                'waehrung' => 'EUR',
                'von_typ' => 'rechnung',
                'von_id' => 22,
                'nach_typ' => 'kasse',
                'nach_id' => 5,
                'belegnr' => 'R-22',
                'debitor' => '',
                'debitor_beleg' => '10025',
                'debitor_fallback' => '',
                'kreditor' => '',
                'kreditor_fallback' => '',
                'sachkonto' => '',
                'bank_datev' => '',
                'kasse_datev' => '1000',
                'intern' => '',
                'kontoauszug_buchungstext' => '',
                'buchungsschluessel' => '',
            ),
        ));

        $exportbuchhaltung = $this->createExportbuchhaltung($database);

        $buchungen = $this->invokePrivateMethod(
            $exportbuchhaltung,
            'collectDatevZahlungsverkehrBuchungen',
            new DateTime('2026-02-01'),
            new DateTime('2026-02-28'),
            0
        );

        self::assertCount(1, $buchungen);
        self::assertSame('1000', $buchungen[0]['Konto']);
        self::assertSame('10025', $buchungen[0]['Gegenkonto (ohne BU-Schlüssel)']);
        self::assertSame('1502', $buchungen[0]['Belegdatum']);
        self::assertSame('S', $buchungen[0]['Soll-/Haben-Kennzeichen']);
    }

    public function testCollectDatevZahlungsverkehrBuchungenUsesKreditorFallbackAndNegativeAmounts(): void
    {
        $database = new ExportbuchhaltungDatabaseStub(array(
            array(
                'id' => 91,
                'fibu_datum' => '2026-03-12',
                'export_datum' => '2026-03-12',
                'betrag' => '-12.34',
                'waehrung' => 'EUR',
                'von_typ' => 'verbindlichkeit',
                'von_id' => 55,
                'nach_typ' => 'kontoauszuege',
                'nach_id' => 12,
                'belegnr' => 'V-55',
                'debitor' => '',
                'debitor_beleg' => '',
                'debitor_fallback' => '',
                'kreditor' => '70001',
                'kreditor_beleg' => '',
                'kreditor_fallback' => '80001',
                'sachkonto' => '',
                'bank_datev' => '1200',
                'kasse_datev' => '',
                'intern' => '',
                'kontoauszug_buchungstext' => '',
                'buchungsschluessel' => '',
            ),
        ));

        $exportbuchhaltung = $this->createExportbuchhaltung($database);

        $buchungen = $this->invokePrivateMethod(
            $exportbuchhaltung,
            'collectDatevZahlungsverkehrBuchungen',
            new DateTime('2026-03-01'),
            new DateTime('2026-03-31'),
            0
        );

        self::assertCount(1, $buchungen);
        self::assertSame('70001', $buchungen[0]['Gegenkonto (ohne BU-Schlüssel)']);
        self::assertSame('H', $buchungen[0]['Soll-/Haben-Kennzeichen']);
        self::assertSame('Zahlung V-55', $buchungen[0]['Buchungstext']);
    }

    public function testCollectDatevZahlungsverkehrBuchungenSkipsRowsWithoutDatevMoneyAccount(): void
    {
        $database = new ExportbuchhaltungDatabaseStub(array(
            array(
                'id' => 92,
                'fibu_datum' => '2026-03-15',
                'export_datum' => '2026-03-15',
                'betrag' => '15.00',
                'waehrung' => 'EUR',
                'von_typ' => 'rechnung',
                'von_id' => 18,
                'nach_typ' => 'kontoauszuege',
                'nach_id' => 13,
                'belegnr' => 'R-18',
                'debitor' => '10018',
                'debitor_beleg' => '',
                'debitor_fallback' => '',
                'kreditor' => '',
                'kreditor_beleg' => '',
                'kreditor_fallback' => '',
                'sachkonto' => '',
                'bank_datev' => '',
                'kasse_datev' => '',
                'intern' => '',
                'kontoauszug_buchungstext' => '',
                'buchungsschluessel' => '',
            ),
        ));

        $exportbuchhaltung = $this->createExportbuchhaltung($database);

        $buchungen = $this->invokePrivateMethod(
            $exportbuchhaltung,
            'collectDatevZahlungsverkehrBuchungen',
            new DateTime('2026-03-01'),
            new DateTime('2026-03-31'),
            0
        );

        self::assertSame(array(), $buchungen);
    }

    public function testCollectDatevZahlungsverkehrBuchungenNormalizesLegacyRegularInputTaxKeyOnCostAccount(): void
    {
        $database = new ExportbuchhaltungDatabaseStub(array(
            $this->createZahlungsverkehrRow(array(
                'id' => 787,
                'betrag' => '-351.00',
                'nach_typ' => 'kontoauszuege',
                'sachkonto' => '4240',
                'bank_datev' => '1210',
                'intern' => 'Zahlung VATTENFALL EUROPE SALES',
                'buchungsschluessel' => '90',
            )),
        ));

        $exportbuchhaltung = $this->createExportbuchhaltung($database);

        $buchungen = $this->invokePrivateMethod(
            $exportbuchhaltung,
            'collectDatevZahlungsverkehrBuchungen',
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            0
        );

        self::assertCount(1, $buchungen);
        self::assertSame('4240', $buchungen[0]['Gegenkonto (ohne BU-Schlüssel)']);
        self::assertSame('9', $buchungen[0]['BU-Schlüssel']);
    }

    public function testCollectDatevZahlungsverkehrBuchungenSuppressesInputTaxKeyOnRevenueAccount(): void
    {
        $database = new ExportbuchhaltungDatabaseStub(array(
            $this->createZahlungsverkehrRow(array(
                'id' => 840,
                'betrag' => '-79.00',
                'nach_typ' => 'kontoauszuege',
                'sachkonto' => '8400',
                'bank_datev' => '1230',
                'intern' => 'Zahlung 647114 - GS.2026-04956',
                'buchungsschluessel' => '90',
            )),
        ));

        $exportbuchhaltung = $this->createExportbuchhaltung($database);

        $buchungen = $this->invokePrivateMethod(
            $exportbuchhaltung,
            'collectDatevZahlungsverkehrBuchungen',
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            0
        );

        self::assertCount(1, $buchungen);
        self::assertSame('8400', $buchungen[0]['Gegenkonto (ohne BU-Schlüssel)']);
        self::assertSame('', $buchungen[0]['BU-Schlüssel']);
    }

    public function testCollectDatevZahlungsverkehrBuchungenKeepsRegularInputTaxKeyOnTankReceipt(): void
    {
        $database = new ExportbuchhaltungDatabaseStub(array(
            $this->createZahlungsverkehrRow(array(
                'id' => 858,
                'betrag' => '111.87',
                'von_typ' => 'kontoauszuege',
                'nach_typ' => 'kontorahmen',
                'sachkonto' => '4530',
                'bank_datev' => '1890',
                'intern' => 'Beleg 375/033/00002 | Aral Tankstelle',
                'buchungsschluessel' => '9',
            )),
        ));

        $exportbuchhaltung = $this->createExportbuchhaltung($database);

        $buchungen = $this->invokePrivateMethod(
            $exportbuchhaltung,
            'collectDatevZahlungsverkehrBuchungen',
            new DateTime('2026-01-01'),
            new DateTime('2026-01-31'),
            0
        );

        self::assertCount(1, $buchungen);
        self::assertSame('4530', $buchungen[0]['Gegenkonto (ohne BU-Schlüssel)']);
        self::assertSame('9', $buchungen[0]['BU-Schlüssel']);
    }

    public function testSortDatevZusatzbuchungenOrdersByDateGroupAndId(): void
    {
        $exportbuchhaltung = $this->createExportbuchhaltung(new ExportbuchhaltungDatabaseStub(array()));

        $sorted = $this->invokePrivateMethod(
            $exportbuchhaltung,
            'sortDatevZusatzbuchungen',
            array(
                array(
                    'Belegfeld 2' => 'FB4',
                    'export_date' => '2026-01-10',
                    'row_group' => 40,
                    'source_id' => 4,
                    'source_type' => 'belegsachkonto',
                ),
                array(
                    'Belegfeld 2' => 'FB2',
                    'export_date' => '2026-01-03',
                    'row_group' => 30,
                    'source_id' => 2,
                    'source_type' => 'zahlungsverkehr',
                ),
                array(
                    'Belegfeld 2' => 'FB3',
                    'export_date' => '2026-01-10',
                    'row_group' => 30,
                    'source_id' => 3,
                    'source_type' => 'zahlungsverkehr',
                ),
            )
        );

        self::assertSame(array('FB2', 'FB3', 'FB4'), array_column($sorted, 'Belegfeld 2'));
        self::assertSame('2026-01-03', $sorted[0]['export_date']);
        self::assertSame(30, $sorted[0]['row_group']);
        self::assertSame(2, $sorted[0]['source_id']);
        self::assertSame('zahlungsverkehr', $sorted[0]['source_type']);
    }

    private function createExportbuchhaltung(ExportbuchhaltungDatabaseStub $database): \Exportbuchhaltung
    {
        $reflectionClass = new \ReflectionClass(\Exportbuchhaltung::class);
        /** @var \Exportbuchhaltung $exportbuchhaltung */
        $exportbuchhaltung = $reflectionClass->newInstanceWithoutConstructor();
        $exportbuchhaltung->app = new ExportbuchhaltungAppStub($database);

        return $exportbuchhaltung;
    }

    /**
     * @return mixed
     */
    private function invokePrivateMethod(object $object, string $methodName, ...$arguments)
    {
        $method = new \ReflectionMethod($object, $methodName);
        $method->setAccessible(true);

        return $method->invoke($object, ...$arguments);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function createZahlungsverkehrRow(array $overrides = array()): array
    {
        return array_merge(
            array(
                'id' => 1,
                'fibu_datum' => '2026-01-08',
                'export_datum' => '2026-01-08',
                'betrag' => '-10.00',
                'waehrung' => 'EUR',
                'von_typ' => 'kontorahmen',
                'von_id' => 1,
                'nach_typ' => 'kontoauszuege',
                'nach_id' => 2,
                'belegnr' => '',
                'debitor' => '',
                'debitor_beleg' => '',
                'debitor_fallback' => '',
                'kreditor' => '',
                'kreditor_beleg' => '',
                'kreditor_fallback' => '',
                'sachkonto' => '4970',
                'bank_datev' => '1210',
                'kasse_datev' => '',
                'intern' => '',
                'kontoauszug_buchungstext' => '',
                'buchungsschluessel' => '',
            ),
            $overrides
        );
    }
}

final class ExportbuchhaltungAppStub
{
    /** @var ExportbuchhaltungDatabaseStub */
    public $DB;

    public function __construct(ExportbuchhaltungDatabaseStub $database)
    {
        $this->DB = $database;
    }
}

final class ExportbuchhaltungDatabaseStub
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;

    public string $lastQuery = '';

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function SelectArr(string $query): array
    {
        $this->lastQuery = $query;

        return $this->rows;
    }
}
