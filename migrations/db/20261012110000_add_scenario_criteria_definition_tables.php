<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Stores editable Scenario-only criteria drafts and their immutable operational-scale evidence. */
final class AddScenarioCriteriaDefinitionTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE scenario_analyses
            ADD criteria_locked_at DATETIME NULL AFTER management_organisation,
            ADD criteria_locked_by INT UNSIGNED NULL AFTER criteria_locked_at,
            ADD KEY scenario_analyses_criteria_locked (criteria_locked_at)");

        $this->execute("CREATE TABLE scenario_criteria_drafts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL,
            scenario_analysis_id BIGINT UNSIGNED NOT NULL,
            source_anr_id INT UNSIGNED NOT NULL,
            source_language_code VARCHAR(8) NOT NULL,
            source_read_at DATETIME NOT NULL,
            identifier VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            aggregation_policy VARCHAR(64) NOT NULL,
            bands JSON NOT NULL,
            acceptance_rules JSON NOT NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1,
            created_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY scenario_criteria_drafts_uuid (uuid),
            UNIQUE KEY scenario_criteria_drafts_analysis (scenario_analysis_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->execute("CREATE TABLE scenario_criteria_draft_dimensions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL,
            draft_id BIGINT UNSIGNED NOT NULL,
            dimension_key VARCHAR(100) NOT NULL,
            source_scale_id INT UNSIGNED NOT NULL,
            source_scale_type_id INT UNSIGNED NOT NULL,
            source_label VARCHAR(255) NOT NULL,
            label VARCHAR(255) NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            is_override TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY scenario_criteria_draft_dimensions_uuid (uuid),
            UNIQUE KEY scenario_criteria_draft_dimensions_key (draft_id, dimension_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->execute("CREATE TABLE scenario_criteria_draft_levels (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL,
            draft_id BIGINT UNSIGNED NOT NULL,
            dimension_id BIGINT UNSIGNED NULL,
            source_scale_id INT UNSIGNED NOT NULL,
            source_scale_type_id INT UNSIGNED NULL,
            source_comment_id INT UNSIGNED NOT NULL,
            source_scale_index INT UNSIGNED NOT NULL,
            source_scale_value INT UNSIGNED NOT NULL,
            source_min INT UNSIGNED NOT NULL,
            source_max INT UNSIGNED NOT NULL,
            source_label TEXT NOT NULL,
            source_comment TEXT NULL,
            level_value TINYINT UNSIGNED NOT NULL,
            label TEXT NOT NULL,
            description TEXT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            is_override TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY scenario_criteria_draft_levels_uuid (uuid),
            UNIQUE KEY scenario_criteria_draft_levels_source (draft_id, source_comment_id),
            KEY scenario_criteria_draft_levels_scope (draft_id, dimension_id, level_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->execute("CREATE TABLE scenario_criteria_locks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL,
            scenario_analysis_id BIGINT UNSIGNED NOT NULL,
            profile_version_id BIGINT UNSIGNED NOT NULL,
            source_anr_id INT UNSIGNED NOT NULL,
            source_language_code VARCHAR(8) NOT NULL,
            source_read_at DATETIME NOT NULL,
            locked_by INT UNSIGNED NOT NULL,
            locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY scenario_criteria_locks_uuid (uuid),
            UNIQUE KEY scenario_criteria_locks_analysis (scenario_analysis_id),
            UNIQUE KEY scenario_criteria_locks_profile_version (profile_version_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->execute("CREATE TABLE scenario_criteria_lock_sources (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lock_id BIGINT UNSIGNED NOT NULL,
            kind VARCHAR(16) NOT NULL,
            dimension_key VARCHAR(100) NULL,
            source_scale_id INT UNSIGNED NOT NULL,
            source_scale_type_id INT UNSIGNED NULL,
            source_comment_id INT UNSIGNED NOT NULL,
            source_scale_index INT UNSIGNED NOT NULL,
            source_scale_value INT UNSIGNED NOT NULL,
            source_min INT UNSIGNED NOT NULL,
            source_max INT UNSIGNED NOT NULL,
            source_label TEXT NOT NULL,
            source_comment TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY scenario_criteria_lock_sources_source (lock_id, source_comment_id),
            KEY scenario_criteria_lock_sources_lock (lock_id, kind, dimension_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS scenario_criteria_lock_sources');
        $this->execute('DROP TABLE IF EXISTS scenario_criteria_locks');
        $this->execute('DROP TABLE IF EXISTS scenario_criteria_draft_levels');
        $this->execute('DROP TABLE IF EXISTS scenario_criteria_draft_dimensions');
        $this->execute('DROP TABLE IF EXISTS scenario_criteria_drafts');
        $this->execute('ALTER TABLE scenario_analyses DROP KEY scenario_analyses_criteria_locked, '
            . 'DROP COLUMN criteria_locked_by, DROP COLUMN criteria_locked_at');
    }
}
