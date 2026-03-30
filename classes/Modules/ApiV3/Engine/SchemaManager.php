<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Engine;

use Xentral\Components\Database\Database;

final class SchemaManager
{
    /** @var bool */
    private static $schemaEnsured = false;

    /** @var Database */
    private $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS `api_v3_token` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `api_account_id` int(11) NOT NULL,
                `label` varchar(191) NOT NULL,
                `token_prefix` varchar(32) NOT NULL,
                `token_hash` varchar(64) NOT NULL,
                `expires_at` datetime DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                `last_used_at` datetime DEFAULT NULL,
                `revoked_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_api_v3_token_account` (`api_account_id`),
                KEY `idx_api_v3_token_hash` (`token_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci'
        );

        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS `api_v3_token_scope` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `token_id` int(11) NOT NULL,
                `scope` varchar(191) NOT NULL,
                `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_api_v3_token_scope_token` (`token_id`),
                KEY `idx_api_v3_token_scope_scope` (`scope`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci'
        );

        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS `api_v3_idempotency_key` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `api_account_id` int(11) NOT NULL,
                `request_method` varchar(16) NOT NULL,
                `request_path` varchar(255) NOT NULL,
                `idempotency_key` varchar(191) NOT NULL,
                `request_hash` varchar(64) NOT NULL,
                `response_status` int(11) NOT NULL,
                `response_body` mediumtext NOT NULL,
                `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_api_v3_idempotency_lookup` (`api_account_id`, `request_method`, `request_path`),
                KEY `idx_api_v3_idempotency_key` (`idempotency_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci'
        );

        self::$schemaEnsured = true;
    }
}
