<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use Xentral\Components\Database\Database;

final class ApiAccountRepository
{
    /** @var Database */
    private $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT id, bezeichnung, aktiv, remotedomain, permissions, projekt FROM api_account WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return empty($row) ? null : $row;
    }
}
