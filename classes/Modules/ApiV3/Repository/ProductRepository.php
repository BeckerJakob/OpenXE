<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use Xentral\Components\Database\Database;

final class ProductRepository
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
    public function searchProducts(array $filters, array $pagination): array
    {
        $where = ['geloescht = 0'];
        $params = [];

        if (!empty($filters['sku'])) {
            $where[] = 'nummer = :sku';
            $params['sku'] = (string)$filters['sku'];
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int)$this->database->fetchValue(
            "SELECT COUNT(id) FROM artikel WHERE {$sqlWhere}",
            $params
        );

        $params['limit'] = $pagination['per_page'];
        $params['offset'] = $pagination['offset'];

        $items = $this->database->fetchAll(
            "SELECT *
             FROM artikel
             WHERE {$sqlWhere}
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProductById(int $id): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT * FROM artikel WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return empty($row) ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProductBySku(string $sku): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT * FROM artikel WHERE nummer = :sku AND geloescht = 0 LIMIT 1',
            ['sku' => $sku]
        );

        return empty($row) ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertProduct(array $data): int
    {
        $this->insertRecord('artikel', $data);

        return $this->database->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateProduct(int $id, array $data): void
    {
        $this->updateRecord('artikel', $id, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertSupplierPrice(array $data): int
    {
        $this->insertRecord('einkaufspreise', $data);

        return $this->database->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findInventoryLevel(int $locationId, int $articleId): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT * FROM lager_platz_inhalt WHERE lager_platz = :lager_platz AND artikel = :artikel LIMIT 1',
            [
                'lager_platz' => $locationId,
                'artikel'     => $articleId,
            ]
        );

        return empty($row) ? null : $row;
    }

    public function updateInventoryLevel(int $rowId, float $quantity): void
    {
        $this->database->perform(
            'UPDATE lager_platz_inhalt
             SET menge = :menge, logdatei = NOW()
             WHERE id = :id
             LIMIT 1',
            [
                'menge' => $quantity,
                'id'    => $rowId,
            ]
        );
    }

    public function insertInventoryLevel(int $locationId, int $articleId, float $quantity, int $projectId = 2): int
    {
        $this->database->perform(
            "INSERT INTO lager_platz_inhalt
                (lager_platz, artikel, menge, vpe, bearbeiter, bestellung, projekt, firma, logdatei, inventur, lager_platz_vpe)
             VALUES
                (:lager_platz, :artikel, :menge, 'einzeln', '', 0, :projekt, 0, NOW(), NULL, 0)",
            [
                'lager_platz' => $locationId,
                'artikel'     => $articleId,
                'menge'       => $quantity,
                'projekt'     => $projectId,
            ]
        );

        return $this->database->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertRecord(string $table, array $data): void
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
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $quotedColumns),
                implode(', ', $placeholders)
            ),
            $data
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateRecord(string $table, int $id, array $data): void
    {
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = sprintf('`%s` = :%s', $column, $column);
        }
        $data['id'] = $id;

        $this->database->perform(
            sprintf('UPDATE %s SET %s WHERE id = :id LIMIT 1', $table, implode(', ', $assignments)),
            $data
        );
    }
}
