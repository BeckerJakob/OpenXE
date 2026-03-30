<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use Xentral\Components\Database\Database;

final class ReferenceDataRepository
{
    /** @var Database */
    private $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProjects(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name, abkuerzung, aktiv
             FROM projekt
             ORDER BY id ASC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWarehouseLocations(): array
    {
        return $this->database->fetchAll(
            'SELECT id, lager, projekt, kurzbezeichnung, bemerkung, autolagersperre, verbrauchslager, sperrlager
             FROM lager_platz
             WHERE geloescht = 0
             ORDER BY kurzbezeichnung ASC, id ASC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPaymentMethods(): array
    {
        return $this->database->fetchAll(
            'SELECT id, type, bezeichnung, freitext, aktiv, automatischbezahlt, automatischbezahltverbindlichkeit, projekt, verhalten, modul
             FROM zahlungsweisen
             WHERE geloescht = 0
             ORDER BY bezeichnung ASC, id ASC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listShippingMethods(): array
    {
        return $this->database->fetchAll(
            'SELECT id, type, bezeichnung, aktiv, projekt, modul
             FROM versandarten
             WHERE geloescht = 0
             ORDER BY bezeichnung ASC, id ASC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTaxRates(): array
    {
        return $this->database->fetchAll(
            'SELECT id, bezeichnung, satz, aktiv, project_id, valid_from, valid_to
             FROM steuersaetze
             WHERE aktiv = 1
             ORDER BY project_id ASC, satz ASC, id ASC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBankAccounts(): array
    {
        return $this->database->fetchAll(
            'SELECT id, bezeichnung, kurzbezeichnung, type, konto, iban, swift, blz, datevkonto
             FROM konten
             ORDER BY bezeichnung ASC, id ASC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLedgerAccounts(): array
    {
        return $this->database->fetchAll(
            'SELECT id, sachkonto, beschriftung, projekt, art, ausblenden
             FROM kontorahmen
             ORDER BY sachkonto ASC, id ASC'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBankAccountById(int $id): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT id, bezeichnung, kurzbezeichnung, type, konto, iban, swift, blz, datevkonto
             FROM konten
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        return empty($row) ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLedgerAccountByNumber(string $accountNumber): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT id, sachkonto, beschriftung
             FROM kontorahmen
             WHERE sachkonto = :account_number
             LIMIT 1',
            ['account_number' => $accountNumber]
        );

        return empty($row) ? null : $row;
    }
}
