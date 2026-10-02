<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Adds client-scoped, immutable assessment evidence for Scenario risk stories. */
final class AddScenarioAssessmentTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("CREATE TABLE scenario_criteria_profiles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL, client_id INT UNSIGNED NOT NULL DEFAULT 1,
            identifier VARCHAR(100) NOT NULL, title VARCHAR(255) NOT NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_criteria_profiles_uuid (uuid),
            UNIQUE KEY scenario_criteria_profiles_client_identifier (client_id, identifier)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->execute("CREATE TABLE scenario_criteria_profile_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL, profile_id BIGINT UNSIGNED NOT NULL,
            version INT UNSIGNED NOT NULL, payload JSON NOT NULL, created_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_criteria_profile_versions_uuid (uuid),
            UNIQUE KEY scenario_criteria_profile_versions_number (profile_id, version),
            KEY scenario_criteria_profile_versions_profile (profile_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->execute("CREATE TABLE scenario_analysis_criteria_profiles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scenario_analysis_id BIGINT UNSIGNED NOT NULL, profile_version_id BIGINT UNSIGNED NOT NULL,
            profile_snapshot JSON NOT NULL, revision INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_analysis_criteria_profiles_analysis (scenario_analysis_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->execute("CREATE TABLE scenario_assessments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL, anr_id INT UNSIGNED NOT NULL, risk_scenario_id BIGINT UNSIGNED NOT NULL,
            assessment_type VARCHAR(32) NOT NULL, profile_version_id BIGINT UNSIGNED NOT NULL,
            calculation_snapshot JSON NOT NULL, legacy_comparison_snapshot JSON NULL,
            supersedes_uuid CHAR(36) NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1, created_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_assessments_uuid (uuid),
            KEY scenario_assessments_risk_scenario_type (risk_scenario_id, assessment_type),
            KEY scenario_assessments_scope (anr_id, risk_scenario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->execute("CREATE TABLE scenario_treatments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, uuid CHAR(36) NOT NULL, anr_id INT UNSIGNED NOT NULL,
            risk_scenario_id BIGINT UNSIGNED NOT NULL, treatment_type VARCHAR(16) NOT NULL, action_text TEXT NOT NULL,
            owner_id INT UNSIGNED NULL, due_at DATETIME NULL, status VARCHAR(32) NOT NULL, revision INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_treatments_uuid (uuid), KEY scenario_treatments_scope (anr_id, risk_scenario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->execute("CREATE TABLE scenario_monitoring_observations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, uuid CHAR(36) NOT NULL, anr_id INT UNSIGNED NOT NULL,
            risk_scenario_id BIGINT UNSIGNED NOT NULL, indicator VARCHAR(255) NOT NULL, reassessment_trigger TEXT NOT NULL,
            observation TEXT NULL, revision INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_monitoring_observations_uuid (uuid), KEY scenario_monitoring_observations_scope (anr_id, risk_scenario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->execute("CREATE TABLE scenario_acceptance_decisions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, uuid CHAR(36) NOT NULL, anr_id INT UNSIGNED NOT NULL,
            risk_scenario_id BIGINT UNSIGNED NOT NULL, assessment_id BIGINT UNSIGNED NOT NULL,
            assigned_supervisor_id INT UNSIGNED NOT NULL, decision_snapshot JSON NOT NULL, revision INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY scenario_acceptance_decisions_uuid (uuid), KEY scenario_acceptance_decisions_scope (anr_id, risk_scenario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function down(): void
    {
        foreach (['scenario_acceptance_decisions', 'scenario_monitoring_observations', 'scenario_treatments', 'scenario_assessments', 'scenario_analysis_criteria_profiles', 'scenario_criteria_profile_versions', 'scenario_criteria_profiles'] as $table) {
            $this->execute('DROP TABLE IF EXISTS ' . $table);
        }
    }
}
