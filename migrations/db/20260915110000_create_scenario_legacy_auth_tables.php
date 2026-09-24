<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateScenarioLegacyAuthTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('CREATE TABLE scenario_legacy_handoffs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code_digest CHAR(64) NOT NULL,
            nonce_digest CHAR(64) NOT NULL, legacy_token_digest CHAR(64) NOT NULL,
            user_id INT UNSIGNED NOT NULL, anr_id INT UNSIGNED NOT NULL,
            office VARCHAR(16) NOT NULL, audience VARCHAR(64) NOT NULL, return_path VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL, consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_legacy_handoffs_code (code_digest),
            KEY scenario_legacy_handoffs_expiry (expires_at), KEY scenario_legacy_handoffs_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
        $this->execute('CREATE TABLE scenario_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_digest CHAR(64) NOT NULL,
            legacy_token_digest CHAR(64) NOT NULL,
            user_id INT UNSIGNED NOT NULL, anr_id INT UNSIGNED NOT NULL, office VARCHAR(16) NOT NULL,
            permission_version INT UNSIGNED NOT NULL DEFAULT 1, expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_sessions_digest (session_digest),
            KEY scenario_sessions_user (user_id), KEY scenario_sessions_expiry (expires_at),
            KEY scenario_sessions_legacy_token (legacy_token_digest)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS scenario_sessions');
        $this->execute('DROP TABLE IF EXISTS scenario_legacy_handoffs');
    }
}
