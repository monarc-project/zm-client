<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Analysis-local evidence for evaluating a template's available events. */
final class AddScenarioTemplateEventEvaluations extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE scenario_template_event_evaluations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    snapshot_id BIGINT UNSIGNED NOT NULL,
    event_record_uuid CHAR(36) NOT NULL,
    state VARCHAR(32) NOT NULL DEFAULT 'not_evaluated',
    comment TEXT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    evaluated_by INT UNSIGNED NULL,
    evaluated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_template_event_evaluations_uuid (uuid),
    UNIQUE KEY scenario_template_event_evaluations_event (snapshot_id, event_record_uuid),
    KEY scenario_template_event_evaluations_scope (anr_id, snapshot_id),
    CONSTRAINT scenario_template_event_evaluations_snapshot_fk
        FOREIGN KEY (snapshot_id) REFERENCES scenario_template_snapshots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS scenario_template_event_evaluations');
    }
}
