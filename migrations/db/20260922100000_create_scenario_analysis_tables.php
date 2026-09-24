<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateScenarioAnalysisTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE scenario_analyses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    lifecycle VARCHAR(16) NOT NULL DEFAULT 'draft',
    owner_id INT UNSIGNED NULL,
    language_code VARCHAR(8) NOT NULL,
    next_review_at DATETIME NULL,
    source_anr_id INT UNSIGNED NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_analyses_uuid (uuid),
    UNIQUE KEY scenario_analyses_anr (anr_id),
    KEY scenario_analyses_scope (anr_id, lifecycle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_risk_scenarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    scenario_analysis_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_risk_scenarios_uuid (uuid),
    KEY scenario_risk_scenarios_scope (anr_id, scenario_analysis_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    risk_scenario_id BIGINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_events_uuid (uuid),
    KEY scenario_events_scope (anr_id, risk_scenario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_event_edges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    risk_scenario_id BIGINT UNSIGNED NOT NULL,
    from_event_id BIGINT UNSIGNED NOT NULL,
    to_event_id BIGINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_event_edges_uuid (uuid),
    UNIQUE KEY scenario_event_edges_pair (risk_scenario_id, from_event_id, to_event_id),
    KEY scenario_event_edges_scope (anr_id, risk_scenario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_consequences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    risk_scenario_id BIGINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_consequences_uuid (uuid),
    KEY scenario_consequences_scope (anr_id, risk_scenario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_subject_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    risk_scenario_id BIGINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_subject_links_uuid (uuid),
    KEY scenario_subject_links_scope (anr_id, risk_scenario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_control_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    risk_scenario_id BIGINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_control_links_uuid (uuid),
    KEY scenario_control_links_scope (anr_id, risk_scenario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_local_overrides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    snapshot_reference VARCHAR(255) NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_local_overrides_uuid (uuid),
    KEY scenario_local_overrides_scope (anr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_snapshot_references (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    reference_type VARCHAR(64) NOT NULL,
    reference_uuid CHAR(36) NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_snapshot_references_uuid (uuid),
    KEY scenario_snapshot_references_scope (anr_id, reference_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NULL,
    correlation_id VARCHAR(128) NOT NULL,
    target_uuid CHAR(36) NOT NULL,
    action VARCHAR(64) NOT NULL,
    change_patch JSON NULL,
    rationale TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_audit_events_uuid (uuid),
    KEY scenario_audit_events_scope (anr_id, target_uuid),
    KEY scenario_audit_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);
    }

    public function down(): void
    {
        $tables = [
            'scenario_audit_events',
            'scenario_snapshot_references',
            'scenario_local_overrides',
            'scenario_control_links',
            'scenario_subject_links',
            'scenario_consequences',
            'scenario_event_edges',
            'scenario_events',
            'scenario_risk_scenarios',
            'scenario_analyses',
        ];

        foreach ($tables as $table) {
            $this->execute("DROP TABLE IF EXISTS {$table}");
        }
    }
}
