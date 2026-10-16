<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Adds mutable treatment-plan tracking while preserving selected recommendation snapshots. */
// Phinx discovers migration classes in the global namespace.
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
final class AddScenarioTreatmentImplementationTracking extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE scenario_treatments
    ADD COLUMN completion_evidence TEXT NULL AFTER status,
    ADD COLUMN cancellation_rationale TEXT NULL AFTER completion_evidence
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_treatment_recommendation_implementations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    treatment_id BIGINT UNSIGNED NOT NULL,
    treatment_recommendation_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'not_started',
    owner_id INT UNSIGNED NULL,
    target_at DATETIME NULL,
    working_note TEXT NULL,
    completion_evidence TEXT NULL,
    not_applicable_rationale TEXT NULL,
    implemented_by INT UNSIGNED NULL,
    implemented_at DATETIME NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_treatment_recommendation_implementations_uuid (uuid),
    UNIQUE KEY scenario_treatment_recommendation_implementations_item (treatment_recommendation_id),
    KEY scenario_treatment_recommendation_implementations_scope (anr_id, treatment_id),
    CONSTRAINT scenario_treatment_recommendation_implementations_treatment_fk
        FOREIGN KEY (treatment_id) REFERENCES scenario_treatments (id) ON DELETE CASCADE,
    CONSTRAINT scenario_treatment_recommendation_implementations_selection_fk
        FOREIGN KEY (treatment_recommendation_id) REFERENCES scenario_treatment_recommendations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_treatment_implementation_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    treatment_id BIGINT UNSIGNED NOT NULL,
    implementation_id BIGINT UNSIGNED NULL,
    actor_id INT UNSIGNED NOT NULL,
    owner_snapshot JSON NULL,
    action VARCHAR(64) NOT NULL,
    before_snapshot JSON NULL,
    after_snapshot JSON NOT NULL,
    idempotency_key CHAR(36) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_treatment_implementation_history_uuid (uuid),
    UNIQUE KEY scenario_treatment_implementation_history_idempotency (treatment_id, idempotency_key),
    KEY scenario_treatment_implementation_history_scope (anr_id, treatment_id, created_at),
    KEY scenario_treatment_implementation_history_implementation (implementation_id),
    CONSTRAINT scenario_treatment_implementation_history_treatment_fk
        FOREIGN KEY (treatment_id) REFERENCES scenario_treatments (id) ON DELETE CASCADE,
    CONSTRAINT scenario_treatment_implementation_history_item_fk
        FOREIGN KEY (implementation_id)
        REFERENCES scenario_treatment_recommendation_implementations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        // Existing selected guidance becomes visible as not-started work without rewriting its immutable snapshot.
        $this->execute(<<<'SQL'
INSERT INTO scenario_treatment_recommendation_implementations
    (uuid, anr_id, treatment_id, treatment_recommendation_id, status)
SELECT UUID(), r.anr_id, r.treatment_id, r.id, 'not_started'
FROM scenario_treatment_recommendations r
LEFT JOIN scenario_treatment_recommendation_implementations i ON i.treatment_recommendation_id = r.id
WHERE i.id IS NULL
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS scenario_treatment_implementation_history');
        $this->execute('DROP TABLE IF EXISTS scenario_treatment_recommendation_implementations');
        $this->execute(<<<'SQL'
ALTER TABLE scenario_treatments
    DROP COLUMN cancellation_rationale,
    DROP COLUMN completion_evidence
SQL);
    }
}
