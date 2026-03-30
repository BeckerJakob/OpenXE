<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use Xentral\Components\Database\Database;

final class SalesOrderRepository
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
    public function searchSalesOrders(array $filters, array $pagination): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['external_ref'])) {
            $where[] = '(belegnr = :external_ref OR internet = :external_ref)';
            $params['external_ref'] = (string)$filters['external_ref'];
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int)$this->database->fetchValue(
            "SELECT COUNT(id) FROM auftrag WHERE {$sqlWhere}",
            $params
        );

        $params['limit'] = $pagination['per_page'];
        $params['offset'] = $pagination['offset'];

        $items = $this->database->fetchAll(
            "SELECT * FROM auftrag
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
    public function findSalesOrderById(int $id): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT * FROM auftrag WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        if (empty($row)) {
            return null;
        }

        $row['positions'] = $this->database->fetchAll(
            'SELECT * FROM auftrag_position WHERE auftrag = :auftrag ORDER BY sort ASC, id ASC',
            ['auftrag' => $id]
        );

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSalesOrderByExternalRef(string $externalRef): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT * FROM auftrag WHERE belegnr = :external_ref OR internet = :external_ref LIMIT 1',
            ['external_ref' => $externalRef]
        );

        return empty($row) ? null : $row;
    }

    /**
     * @param array<string, mixed> $orderData
     * @param array<int, array<string, mixed>> $positions
     */
    public function createSalesOrder(array $orderData, array $positions): int
    {
        $this->database->beginTransaction();
        try {
            $orderId = $this->insertOrder($orderData);

            foreach ($positions as $position) {
                $position['auftrag'] = $orderId;
                $this->insertPosition($position);
            }

            $this->database->commit();

            return $orderId;
        } catch (\Throwable $throwable) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $throwable;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertOrder(array $data): int
    {
        $this->insertRecord('auftrag', $data);

        return $this->database->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertPosition(array $data): int
    {
        $this->insertRecord('auftrag_position', $data);

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
}
