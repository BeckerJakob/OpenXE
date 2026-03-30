<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use Xentral\Components\Database\Database;

final class PartnerRepository
{
    /** @var Database */
    private $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array{page:int,per_page:int,offset:int} $pagination
     *
     * @return array{items:array<int, array<string, mixed>>,total:int}
     */
    public function searchCustomers(array $filters, array $pagination): array
    {
        $where = ['geloescht = 0', '(lieferantennummer = "" OR lieferantennummer IS NULL)'];
        $params = [];

        if (!empty($filters['email'])) {
            $where[] = 'email = :email';
            $params['email'] = (string)$filters['email'];
        }
        if (!empty($filters['kundennummer'])) {
            $where[] = 'kundennummer = :kundennummer';
            $params['kundennummer'] = (string)$filters['kundennummer'];
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int)$this->database->fetchValue(
            "SELECT COUNT(id) FROM adresse WHERE {$sqlWhere}",
            $params
        );

        $params['limit'] = $pagination['per_page'];
        $params['offset'] = $pagination['offset'];

        $items = $this->database->fetchAll(
            "SELECT
                id,
                kundennummer,
                kundennummer_buchhaltung,
                projekt,
                name,
                vorname,
                nachname,
                typ,
                firma,
                email,
                telefon,
                land,
                plz,
                ort,
                strasse,
                adresszusatz,
                lieferantennummer
             FROM adresse
             WHERE {$sqlWhere}
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param array{page:int,per_page:int,offset:int} $pagination
     *
     * @return array{items:array<int, array<string, mixed>>,total:int}
     */
    public function searchSuppliers(string $supplierNumber, array $pagination): array
    {
        $where = ['lieferantennummer <> ""'];
        $params = [];

        if ($supplierNumber !== '') {
            $where[] = 'lieferantennummer = :lieferantennummer';
            $params['lieferantennummer'] = $supplierNumber;
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int)$this->database->fetchValue(
            "SELECT COUNT(id) FROM adresse WHERE {$sqlWhere}",
            $params
        );

        $params['limit'] = $pagination['per_page'];
        $params['offset'] = $pagination['offset'];

        $items = $this->database->fetchAll(
            "SELECT id, lieferantennummer, name, email, telefon, land, plz, ort, strasse
             FROM adresse
             WHERE {$sqlWhere}
             ORDER BY name ASC, id ASC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCustomerById(int $id): ?array
    {
        return $this->findAddressById($id, false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSupplierById(int $id): ?array
    {
        return $this->findAddressById($id, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCustomerByEmail(string $email): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT * FROM adresse WHERE email = :email AND geloescht = 0 LIMIT 1',
            ['email' => $email]
        );

        return empty($row) ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCustomerByNumber(string $customerNumber): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT * FROM adresse WHERE kundennummer = :kundennummer AND geloescht = 0 LIMIT 1',
            ['kundennummer' => $customerNumber]
        );

        return empty($row) ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSupplierByNumber(string $supplierNumber): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT * FROM adresse WHERE lieferantennummer = :lieferantennummer LIMIT 1',
            ['lieferantennummer' => $supplierNumber]
        );

        return empty($row) ? null : $row;
    }

    public function lockNextCustomerAccountingNumber(): int
    {
        $value = $this->database->fetchValue(
            "SELECT wert FROM firmendaten_werte WHERE name = 'next_kundennummer' FOR UPDATE"
        );

        if ($value === false) {
            return 0;
        }

        return (int)$value;
    }

    public function incrementNextCustomerAccountingNumber(): void
    {
        $this->database->perform(
            "UPDATE firmendaten_werte SET wert = wert + 1 WHERE name = 'next_kundennummer'"
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertAddress(array $data): int
    {
        $columns = array_keys($data);
        $quotedColumns = array_map(static function (string $column): string {
            return sprintf('`%s`', $column);
        }, $columns);
        $placeholders = array_map(static function (string $column): string {
            return sprintf(':%s', $column);
        }, $columns);

        $this->database->perform(
            sprintf(
                'INSERT INTO adresse (%s) VALUES (%s)',
                implode(', ', $quotedColumns),
                implode(', ', $placeholders)
            ),
            $data
        );

        return $this->database->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateAddress(int $id, array $data): void
    {
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = sprintf('`%s` = :%s', $column, $column);
        }

        $data['id'] = $id;
        $this->database->perform(
            sprintf('UPDATE adresse SET %s WHERE id = :id LIMIT 1', implode(', ', $assignments)),
            $data
        );
    }

    public function createAddressRole(int $addressId, int $projectId, string $fromDate): int
    {
        $this->database->perform(
            "INSERT INTO adresse_rolle (adresse, projekt, subjekt, praedikat, objekt, parameter, von, bis)
             VALUES (:adresse, :projekt, 'Kunde', 'von', 'Projekt', :parameter, :von, '0000-00-00')",
            [
                'adresse'   => $addressId,
                'projekt'   => $projectId,
                'parameter' => $projectId,
                'von'       => $fromDate,
            ]
        );

        return $this->database->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAddressById(int $id, bool $mustBeSupplier): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT
                id,
                kundennummer,
                kundennummer_buchhaltung,
                lieferantennummer,
                projekt,
                name,
                vorname,
                nachname,
                typ,
                firma,
                email,
                telefon,
                land,
                plz,
                ort,
                strasse,
                adresszusatz
             FROM adresse
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if (empty($row)) {
            return null;
        }

        if ($mustBeSupplier && empty($row['lieferantennummer'])) {
            return null;
        }

        return $row;
    }
}
