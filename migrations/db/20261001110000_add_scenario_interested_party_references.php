<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScenarioInterestedPartyReferences extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE scenario_analysis_interested_party_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    scenario_analysis_id BIGINT UNSIGNED NOT NULL,
    interested_party_id BIGINT UNSIGNED NOT NULL,
    stakeholder_snapshot VARCHAR(255) NOT NULL,
    requirement_snapshot TEXT NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_analysis_interested_party_links_uuid (uuid),
    UNIQUE KEY scenario_analysis_interested_party_links_source (anr_id, interested_party_id),
    KEY scenario_analysis_interested_party_links_scope (anr_id, scenario_analysis_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS scenario_analysis_interested_party_links');
    }
}
