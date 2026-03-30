<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use Xentral\Components\Database\Database;

final class IdempotencyRepository
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
    public function find(int $apiAccountId, string $method, string $path, string $idempotencyKey): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT id, request_hash, response_status, response_body
             FROM api_v3_idempotency_key
             WHERE api_account_id = :api_account_id
               AND request_method = :request_method
               AND request_path = :request_path
               AND idempotency_key = :idempotency_key
             LIMIT 1',
            [
                'api_account_id'  => $apiAccountId,
                'request_method'  => $method,
                'request_path'    => $path,
                'idempotency_key' => $idempotencyKey,
            ]
        );

        return empty($row) ? null : $row;
    }

    public function store(
        int $apiAccountId,
        string $method,
        string $path,
        string $idempotencyKey,
        string $requestHash,
        int $responseStatus,
        string $responseBody
    ): void {
        $this->database->perform(
            'INSERT INTO api_v3_idempotency_key
                (api_account_id, request_method, request_path, idempotency_key, request_hash, response_status, response_body, created_at)
             VALUES
                (:api_account_id, :request_method, :request_path, :idempotency_key, :request_hash, :response_status, :response_body, NOW())',
            [
                'api_account_id'  => $apiAccountId,
                'request_method'  => $method,
                'request_path'    => $path,
                'idempotency_key' => $idempotencyKey,
                'request_hash'    => $requestHash,
                'response_status' => $responseStatus,
                'response_body'   => $responseBody,
            ]
        );
    }
}
