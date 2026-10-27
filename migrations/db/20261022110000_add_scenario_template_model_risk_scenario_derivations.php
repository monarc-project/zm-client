<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Adds SRA-25 derivation evidence for databases migrated before the clean schema update. */
final class AddScenarioTemplateModelRiskScenarioDerivations extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS scenario_template_model_creation_requests (
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
CREATE TABLE IF NOT EXISTS scenario_template_model_risk_scenario_derivations (
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
CREATE TABLE IF NOT EXISTS scenario_template_model_event_derivations (
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
        $this->execute('DROP TABLE IF EXISTS scenario_template_model_event_derivations');
        $this->execute('DROP TABLE IF EXISTS scenario_template_model_risk_scenario_derivations');
        $this->execute('DROP TABLE IF EXISTS scenario_template_model_creation_requests');
    }
}
