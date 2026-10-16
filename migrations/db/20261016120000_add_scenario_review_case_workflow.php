<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Adds the append-only, ANR-scoped Scenario reassessment review workflow. */
// Phinx discovers migration classes in the global namespace.
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
final class AddScenarioReviewCaseWorkflow extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE scenario_review_cases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    scenario_analysis_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(16) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'open',
    scope_type VARCHAR(24) NOT NULL,
    scheduled_for DATETIME NULL,
    occurrence_context_at DATETIME NULL,
    assigned_reviewer_id INT UNSIGNED NULL,
    initiated_by INT UNSIGNED NULL,
    start_snapshot JSON NOT NULL,
    satisfies_scheduled_review TINYINT(1) NULL,
    completion_rationale TEXT NULL,
    dismissal_rationale TEXT NULL,
    open_scheduled_marker VARCHAR(32) NULL,
    idempotency_key CHAR(36) NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_review_cases_uuid (uuid),
    UNIQUE KEY scenario_review_cases_idempotency (anr_id, idempotency_key),
    UNIQUE KEY scenario_review_cases_open_scheduled (anr_id, open_scheduled_marker),
    KEY scenario_review_cases_scope (anr_id, status, created_at),
    KEY scenario_review_cases_analysis (scenario_analysis_id),
    CONSTRAINT fk_scenario_review_cases_anr
        FOREIGN KEY (anr_id) REFERENCES anrs (id) ON DELETE CASCADE,
    CONSTRAINT fk_scenario_review_cases_analysis
        FOREIGN KEY (scenario_analysis_id) REFERENCES scenario_analyses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_review_case_scopes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    review_case_id BIGINT UNSIGNED NOT NULL,
    risk_scenario_id BIGINT UNSIGNED NOT NULL,
    risk_scenario_uuid CHAR(36) NOT NULL,
    start_revision INT UNSIGNED NOT NULL,
    disposition VARCHAR(32) NULL,
    rationale TEXT NULL,
    reassessed_assessment_uuid CHAR(36) NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_review_case_scopes_uuid (uuid),
    UNIQUE KEY scenario_review_case_scopes_case_scenario (review_case_id, risk_scenario_id),
    KEY scenario_review_case_scopes_scope (anr_id, review_case_id),
    CONSTRAINT fk_scenario_review_case_scopes_case
        FOREIGN KEY (review_case_id) REFERENCES scenario_review_cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_scenario_review_case_scopes_anr
        FOREIGN KEY (anr_id) REFERENCES anrs (id) ON DELETE CASCADE,
    CONSTRAINT fk_scenario_review_case_scopes_scenario
        FOREIGN KEY (risk_scenario_id) REFERENCES scenario_risk_scenarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_review_case_treatment_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    anr_id INT UNSIGNED NOT NULL,
    review_case_scope_id BIGINT UNSIGNED NOT NULL,
    treatment_id BIGINT UNSIGNED NOT NULL,
    treatment_uuid CHAR(36) NOT NULL,
    treatment_snapshot JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_review_case_treatment_links_once (review_case_scope_id, treatment_id),
    KEY scenario_review_case_treatment_links_scope (anr_id, review_case_scope_id),
    CONSTRAINT fk_scenario_review_case_treatment_links_scope
        FOREIGN KEY (review_case_scope_id) REFERENCES scenario_review_case_scopes (id) ON DELETE CASCADE,
    CONSTRAINT fk_scenario_review_case_treatment_links_anr
        FOREIGN KEY (anr_id) REFERENCES anrs (id) ON DELETE CASCADE,
    CONSTRAINT fk_scenario_review_case_treatment_links_treatment
        FOREIGN KEY (treatment_id) REFERENCES scenario_treatments (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_review_trigger_occurrences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    review_case_id BIGINT UNSIGNED NOT NULL,
    reassessment_trigger_id INT UNSIGNED NOT NULL,
    trigger_snapshot JSON NOT NULL,
    occurred_at DATETIME NOT NULL,
    reporter_id INT UNSIGNED NULL,
    source_observation_uuid CHAR(36) NULL,
    source_text TEXT NULL,
    triage_rationale TEXT NOT NULL,
    idempotency_key CHAR(36) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_review_trigger_occurrences_uuid (uuid),
    UNIQUE KEY scenario_review_trigger_occurrences_idempotency (anr_id, idempotency_key),
    KEY scenario_review_trigger_occurrences_scope (anr_id, review_case_id, occurred_at),
    KEY scenario_review_trigger_occurrences_trigger (reassessment_trigger_id),
    CONSTRAINT fk_scenario_review_trigger_occurrences_case
        FOREIGN KEY (review_case_id) REFERENCES scenario_review_cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_scenario_review_trigger_occurrences_anr
        FOREIGN KEY (anr_id) REFERENCES anrs (id) ON DELETE CASCADE,
    CONSTRAINT fk_scenario_review_trigger_occurrences_trigger
        FOREIGN KEY (reassessment_trigger_id) REFERENCES anr_reassessment_triggers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE scenario_review_case_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    review_case_id BIGINT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NULL,
    action VARCHAR(64) NOT NULL,
    prior_status VARCHAR(24) NULL,
    new_status VARCHAR(24) NULL,
    reason TEXT NULL,
    scope_snapshot JSON NULL,
    evidence_ids JSON NULL,
    idempotency_key CHAR(36) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_review_case_history_uuid (uuid),
    UNIQUE KEY scenario_review_case_history_idempotency (review_case_id, idempotency_key),
    KEY scenario_review_case_history_scope (anr_id, review_case_id, created_at),
    CONSTRAINT fk_scenario_review_case_history_case
        FOREIGN KEY (review_case_id) REFERENCES scenario_review_cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_scenario_review_case_history_anr
        FOREIGN KEY (anr_id) REFERENCES anrs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS scenario_review_case_history');
        $this->execute('DROP TABLE IF EXISTS scenario_review_trigger_occurrences');
        $this->execute('DROP TABLE IF EXISTS scenario_review_case_treatment_links');
        $this->execute('DROP TABLE IF EXISTS scenario_review_case_scopes');
        $this->execute('DROP TABLE IF EXISTS scenario_review_cases');
    }
}
