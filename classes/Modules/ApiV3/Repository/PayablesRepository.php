<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use Xentral\Components\Database\Database;

final class PayablesRepository
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
    public function searchPayables(array $filters, array $pagination): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['invoice_number'])) {
            $where[] = 'v.rechnung = :rechnung';
            $params['rechnung'] = (string)$filters['invoice_number'];
        }
        if (!empty($filters['supplier_id'])) {
            $where[] = 'v.adresse = :adresse';
            $params['adresse'] = (int)$filters['supplier_id'];
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int)$this->database->fetchValue(
            "SELECT COUNT(v.id)
             FROM verbindlichkeit AS v
             WHERE {$sqlWhere}",
            $params
        );

        $params['limit'] = $pagination['per_page'];
        $params['offset'] = $pagination['offset'];

        $items = $this->database->fetchAll(
            "SELECT
                v.*,
                a.lieferantennummer,
                a.name AS lieferant_name
             FROM verbindlichkeit AS v
             LEFT JOIN adresse AS a ON a.id = v.adresse
             WHERE {$sqlWhere}
             ORDER BY v.id DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPayableById(int $id): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT
                v.*,
                a.lieferantennummer,
                a.name AS lieferant_name
             FROM verbindlichkeit AS v
             LEFT JOIN adresse AS a ON a.id = v.adresse
             WHERE v.id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if (empty($row)) {
            return null;
        }

        $row['attachments'] = $this->database->fetchAll(
            "SELECT ds.id, ds.datei, ds.sort, d.titel, d.beschreibung
             FROM datei_stichwoerter AS ds
             INNER JOIN datei AS d ON d.id = ds.datei
             WHERE ds.objekt = 'verbindlichkeit' AND ds.parameter = :parameter AND d.geloescht <> 1
             ORDER BY ds.sort ASC, ds.id ASC",
            ['parameter' => $id]
        );

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findPayablesByInvoiceNumber(string $invoiceNumber, ?int $supplierId = null, int $limit = 10): array
    {
        return $this->database->fetchAll(
            'SELECT
                v.id,
                v.rechnung,
                v.rechnungsdatum,
                v.betrag,
                v.adresse,
                a.lieferantennummer,
                a.name AS lieferant_name
             FROM verbindlichkeit AS v
             LEFT JOIN adresse AS a ON a.id = v.adresse
             WHERE v.rechnung = :rechnung
               AND (:adresse IS NULL OR v.adresse = :adresse)
             ORDER BY v.id DESC
             LIMIT :result_limit',
            [
                'rechnung'     => $invoiceNumber,
                'adresse'      => $supplierId,
                'result_limit' => max(1, min(100, $limit)),
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertPayable(array $data): int
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
                'INSERT INTO verbindlichkeit (%s) VALUES (%s)',
                implode(', ', $quotedColumns),
                implode(', ', $placeholders)
            ),
            $data
        );

        return $this->database->lastInsertId();
    }
}
