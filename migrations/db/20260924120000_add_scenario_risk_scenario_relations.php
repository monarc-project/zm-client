<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScenarioRiskScenarioRelations extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE scenario_risk_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    risk_scenario_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    narrative TEXT NULL,
    legacy_risk_source_uuid CHAR(36) NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_risk_sources_uuid (uuid),
    UNIQUE KEY scenario_risk_sources_scenario (risk_scenario_id),
    KEY scenario_risk_sources_scope (anr_id, risk_scenario_id),
    CONSTRAINT fk_scenario_risk_sources_scenario
        FOREIGN KEY (risk_scenario_id) REFERENCES scenario_risk_scenarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_causes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    risk_scenario_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    narrative TEXT NULL,
    threat_reference_uuid CHAR(36) NULL,
    vulnerability_reference_uuid CHAR(36) NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_causes_uuid (uuid),
    UNIQUE KEY scenario_causes_scenario (risk_scenario_id),
    KEY scenario_causes_scope (anr_id, risk_scenario_id),
    CONSTRAINT fk_scenario_causes_scenario
        FOREIGN KEY (risk_scenario_id) REFERENCES scenario_risk_scenarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE scenario_event_consequences
    ADD CONSTRAINT fk_scenario_event_consequences_event
        FOREIGN KEY (event_id) REFERENCES scenario_events (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_scenario_event_consequences_consequence
        FOREIGN KEY (consequence_id) REFERENCES scenario_consequences (id) ON DELETE CASCADE
SQL);
    }

    public function down(): void
    {
        $this->execute(
            'ALTER TABLE scenario_event_consequences '
            . 'DROP FOREIGN KEY fk_scenario_event_consequences_event, '
            . 'DROP FOREIGN KEY fk_scenario_event_consequences_consequence'
        );
        $this->execute('DROP TABLE IF EXISTS scenario_causes');
        $this->execute('DROP TABLE IF EXISTS scenario_risk_sources');
    }
}
