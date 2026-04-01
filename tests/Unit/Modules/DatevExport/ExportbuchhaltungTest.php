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

    public function testSortDatevZusatzbuchungenOrdersByDateGroupAndId(): void
    {
        $exportbuchhaltung = $this->createExportbuchhaltung(new ExportbuchhaltungDatabaseStub(array()));

        $sorted = $this->invokePrivateMethod(
            $exportbuchhaltung,
            'sortDatevZusatzbuchungen',
            array(
                array(
                    'Belegfeld 2' => 'FB4',
                    '_sort_date' => '2026-01-10',
                    '_sort_group' => 20,
                    '_sort_id' => 4,
                ),
                array(
                    'Belegfeld 2' => 'FB2',
                    '_sort_date' => '2026-01-03',
                    '_sort_group' => 10,
                    '_sort_id' => 2,
                ),
                array(
                    'Belegfeld 2' => 'FB3',
                    '_sort_date' => '2026-01-10',
                    '_sort_group' => 10,
                    '_sort_id' => 3,
                ),
            )
        );

        self::assertSame(array('FB2', 'FB3', 'FB4'), array_column($sorted, 'Belegfeld 2'));
        self::assertArrayNotHasKey('_sort_date', $sorted[0]);
        self::assertArrayNotHasKey('_sort_group', $sorted[0]);
        self::assertArrayNotHasKey('_sort_id', $sorted[0]);
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
