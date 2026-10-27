<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Immutable catalog-release evidence and copy-on-write records for SRA-09. */
final class CreateScenarioTemplateSnapshotTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE scenario_template_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    scenario_analysis_id BIGINT UNSIGNED NOT NULL,
    catalog_template_uuid CHAR(36) NOT NULL,
    catalog_template_version INT UNSIGNED NOT NULL,
    catalog_release_uuid CHAR(36) NULL,
    catalog_release_checksum CHAR(64) NOT NULL,
    catalog_provenance TEXT NOT NULL,
    payload_json JSON NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_template_snapshots_uuid (uuid),
    UNIQUE KEY scenario_template_snapshots_idempotency (anr_id, catalog_template_uuid, catalog_template_version),
    KEY scenario_template_snapshots_scope (anr_id, scenario_analysis_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_template_instance_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    snapshot_id BIGINT UNSIGNED NOT NULL,
    record_type VARCHAR(32) NOT NULL,
    source_uuid CHAR(36) NULL,
    source_version INT UNSIGNED NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    content_json JSON NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_template_instance_records_uuid (uuid),
    KEY scenario_template_instance_records_scope (anr_id, snapshot_id, record_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_template_local_overrides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    snapshot_id BIGINT UNSIGNED NOT NULL,
    instance_record_uuid CHAR(36) NOT NULL,
    field_name VARCHAR(64) NOT NULL,
    value_json JSON NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_template_local_overrides_uuid (uuid),
    UNIQUE KEY scenario_template_local_overrides_field (snapshot_id, instance_record_uuid, field_name),
    KEY scenario_template_local_overrides_scope (anr_id, snapshot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_template_model_creation_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    snapshot_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    request_checksum CHAR(64) NOT NULL,
    source_revision INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    correlation_id VARCHAR(128) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_template_model_creation_requests_uuid (uuid),
    UNIQUE KEY scenario_template_model_creation_requests_idempotency (anr_id, idempotency_key),
    KEY scenario_template_model_creation_requests_scope (anr_id, snapshot_id),
    CONSTRAINT scenario_template_model_creation_requests_snapshot_fk
        FOREIGN KEY (snapshot_id) REFERENCES scenario_template_snapshots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_template_model_risk_scenario_derivations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    snapshot_id BIGINT UNSIGNED NOT NULL,
    creation_request_id BIGINT UNSIGNED NOT NULL,
    model_record_uuid CHAR(36) NOT NULL,
    risk_scenario_id BIGINT UNSIGNED NOT NULL,
    source_payload JSON NOT NULL,
    source_checksum CHAR(64) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_template_model_risk_scenario_derivations_uuid (uuid),
    UNIQUE KEY scenario_template_model_risk_scenario_derivations_request_model (creation_request_id, model_record_uuid),
    KEY scenario_template_model_risk_scenario_derivations_scope (anr_id, snapshot_id, model_record_uuid),
    KEY scenario_template_model_risk_scenario_derivations_risk_scenario (risk_scenario_id),
    CONSTRAINT scenario_template_model_derivation_snapshot_fk
        FOREIGN KEY (snapshot_id) REFERENCES scenario_template_snapshots(id) ON DELETE CASCADE,
    CONSTRAINT scenario_template_model_derivation_request_fk
        FOREIGN KEY (creation_request_id) REFERENCES scenario_template_model_creation_requests(id) ON DELETE CASCADE,
    CONSTRAINT scenario_template_model_derivation_risk_fk
        FOREIGN KEY (risk_scenario_id) REFERENCES scenario_risk_scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_template_model_event_derivations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    model_derivation_id BIGINT UNSIGNED NOT NULL,
    event_record_uuid CHAR(36) NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    source_payload JSON NOT NULL,
    source_checksum CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_template_model_event_derivations_uuid (uuid),
    UNIQUE KEY scenario_template_model_event_derivations_source (model_derivation_id, event_record_uuid),
    UNIQUE KEY scenario_template_model_event_derivations_event (event_id),
    KEY scenario_template_model_event_derivations_scope (anr_id, event_record_uuid),
    CONSTRAINT scenario_template_event_derivation_model_fk
        FOREIGN KEY (model_derivation_id) REFERENCES scenario_template_model_risk_scenario_derivations(id) ON DELETE CASCADE,
    CONSTRAINT scenario_template_event_derivation_event_fk
        FOREIGN KEY (event_id) REFERENCES scenario_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);
    }

    public function down(): void
    {
        foreach ([
            'scenario_template_model_event_derivations',
            'scenario_template_model_risk_scenario_derivations',
            'scenario_template_model_creation_requests',
            'scenario_template_local_overrides',
            'scenario_template_instance_records',
            'scenario_template_snapshots',
        ] as $table) {
            $this->execute("DROP TABLE IF EXISTS {$table}");
        }
    }
}
