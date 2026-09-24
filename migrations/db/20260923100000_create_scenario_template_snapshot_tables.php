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
    }

    public function down(): void
    {
        foreach ([
            'scenario_template_local_overrides',
            'scenario_template_instance_records',
            'scenario_template_snapshots',
        ] as $table) {
            $this->execute("DROP TABLE IF EXISTS {$table}");
        }
    }
}
