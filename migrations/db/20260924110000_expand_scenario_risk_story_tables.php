<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Evolves the intentionally empty SRA-08 story placeholders without touching drafts. */
final class ExpandScenarioRiskStoryTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE scenario_risk_scenarios
            ADD risk_source_title VARCHAR(255) NULL,
            ADD risk_source_narrative TEXT NULL,
            ADD risk_source_reference_uuid CHAR(36) NULL,
            ADD cause_title VARCHAR(255) NULL,
            ADD cause_narrative TEXT NULL,
            ADD threat_reference_uuid CHAR(36) NULL,
            ADD vulnerability_reference_uuid CHAR(36) NULL,
            ADD source_snapshot_uuid CHAR(36) NULL,
            ADD source_instance_record_uuid CHAR(36) NULL,
            ADD provenance VARCHAR(255) NULL");
        $this->execute("ALTER TABLE scenario_events
            ADD title VARCHAR(255) NOT NULL DEFAULT '',
            ADD narrative TEXT NULL");
        $this->execute("ALTER TABLE scenario_consequences
            ADD title VARCHAR(255) NOT NULL DEFAULT '',
            ADD narrative TEXT NULL");
        $this->execute("CREATE TABLE scenario_event_consequences (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL,
            anr_id INT UNSIGNED NOT NULL,
            risk_scenario_id BIGINT UNSIGNED NOT NULL,
            event_id BIGINT UNSIGNED NOT NULL,
            consequence_id BIGINT UNSIGNED NOT NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_event_consequences_uuid (uuid),
            UNIQUE KEY scenario_event_consequences_pair (risk_scenario_id, event_id, consequence_id),
            KEY scenario_event_consequences_scope (anr_id, risk_scenario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->execute("ALTER TABLE scenario_subject_links
            ADD target_type VARCHAR(16) NOT NULL DEFAULT 'risk_scenario',
            ADD target_uuid CHAR(36) NULL,
            ADD subject_type VARCHAR(16) NOT NULL DEFAULT 'asset',
            ADD subject_reference_uuid CHAR(36) NOT NULL DEFAULT '',
            ADD relationship_intent VARCHAR(32) NULL,
            ADD narrative TEXT NULL,
            ADD UNIQUE KEY scenario_subject_links_unique (risk_scenario_id, target_type, target_uuid, subject_type, subject_reference_uuid)");
        $this->execute("ALTER TABLE scenario_control_links
            ADD target_type VARCHAR(16) NOT NULL DEFAULT 'risk_scenario',
            ADD target_uuid CHAR(36) NULL,
            ADD control_reference_uuid CHAR(36) NOT NULL DEFAULT '',
            ADD relationship_intent VARCHAR(16) NOT NULL DEFAULT 'existing',
            ADD narrative TEXT NULL,
            ADD UNIQUE KEY scenario_control_links_unique (risk_scenario_id, target_type, target_uuid, control_reference_uuid, relationship_intent)");
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS scenario_event_consequences');
    }
}
