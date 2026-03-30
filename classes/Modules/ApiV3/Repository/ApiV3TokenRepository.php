<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Repository;

use DateTimeImmutable;
use Xentral\Components\Database\Database;

final class ApiV3TokenRepository
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
    public function findActiveTokenByHash(string $tokenHash): ?array
    {
        $row = $this->database->fetchRow(
            'SELECT
                t.id,
                t.api_account_id,
                t.label,
                t.token_prefix,
                t.token_hash,
                t.expires_at,
                t.last_used_at,
                t.revoked_at,
                t.created_at,
                a.bezeichnung AS account_label,
                a.aktiv AS account_active,
                GROUP_CONCAT(ts.scope ORDER BY ts.scope SEPARATOR \',\') AS scopes
             FROM api_v3_token AS t
             INNER JOIN api_account AS a ON a.id = t.api_account_id
             LEFT JOIN api_v3_token_scope AS ts ON ts.token_id = t.id
             WHERE t.token_hash = :token_hash
             GROUP BY t.id
             LIMIT 1',
            ['token_hash' => $tokenHash]
        );

        if (empty($row)) {
            return null;
        }

        $row['scopes'] = $row['scopes'] !== null && $row['scopes'] !== ''
            ? explode(',', (string)$row['scopes'])
            : [];

        return $row;
    }

    public function touchToken(int $tokenId): void
    {
        $this->database->perform(
            'UPDATE api_v3_token SET last_used_at = NOW() WHERE id = :id',
            ['id' => $tokenId]
        );
    }

    /**
     * @param string[] $scopes
     *
     * @return array{id:int,label:string,token_prefix:string,scopes:string[],expires_at:?string}
     */
    public function createToken(int $apiAccountId, string $label, string $tokenPrefix, string $tokenHash, array $scopes, ?string $expiresAt = null): array
    {
        $this->database->beginTransaction();
        try {
            $this->database->perform(
                'INSERT INTO api_v3_token (api_account_id, label, token_prefix, token_hash, expires_at, created_at, last_used_at, revoked_at)
                 VALUES (:api_account_id, :label, :token_prefix, :token_hash, :expires_at, NOW(), NULL, NULL)',
                [
                    'api_account_id' => $apiAccountId,
                    'label'          => $label,
                    'token_prefix'   => $tokenPrefix,
                    'token_hash'     => $tokenHash,
                    'expires_at'     => $expiresAt,
                ]
            );
            $tokenId = $this->database->lastInsertId();

            foreach ($scopes as $scope) {
                $this->database->perform(
                    'INSERT INTO api_v3_token_scope (token_id, scope, created_at) VALUES (:token_id, :scope, NOW())',
                    [
                        'token_id' => $tokenId,
                        'scope'    => $scope,
                    ]
                );
            }

            $this->database->commit();

            return [
                'id'           => $tokenId,
                'label'        => $label,
                'token_prefix' => $tokenPrefix,
                'scopes'       => $scopes,
                'expires_at'   => $expiresAt,
            ];
        } catch (\Throwable $throwable) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $throwable;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTokensByAccountId(int $apiAccountId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT
                t.id,
                t.label,
                t.token_prefix,
                t.created_at,
                t.last_used_at,
                t.expires_at,
                t.revoked_at,
                GROUP_CONCAT(ts.scope ORDER BY ts.scope SEPARATOR \',\') AS scopes
             FROM api_v3_token AS t
             LEFT JOIN api_v3_token_scope AS ts ON ts.token_id = t.id
             WHERE t.api_account_id = :api_account_id
             GROUP BY t.id
             ORDER BY t.id DESC',
            ['api_account_id' => $apiAccountId]
        );

        foreach ($rows as &$row) {
            $row['scopes'] = $row['scopes'] !== null && $row['scopes'] !== ''
                ? explode(',', (string)$row['scopes'])
                : [];
        }
        unset($row);

        return $rows;
    }

    public function revokeToken(int $tokenId): void
    {
        $this->database->perform(
            'UPDATE api_v3_token SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL',
            ['id' => $tokenId]
        );
    }

    public function purgeExpiredIdempotencyKeys(int $days = 14): void
    {
        $cutoff = (new DateTimeImmutable(sprintf('-%d days', max(1, $days))))->format('Y-m-d H:i:s');
        $this->database->perform(
            'DELETE FROM api_v3_idempotency_key WHERE created_at < :cutoff',
            ['cutoff' => $cutoff]
        );
    }
}
