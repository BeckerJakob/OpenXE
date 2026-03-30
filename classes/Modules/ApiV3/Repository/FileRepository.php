<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use Xentral\Components\Database\Database;

final class FileRepository
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
    public function findFileById(int $id): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT id, titel, beschreibung, nummer, geloescht
             FROM datei
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        return empty($row) ? null : $row;
    }
}
