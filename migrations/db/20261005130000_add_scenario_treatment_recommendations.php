<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Links Scenario treatment actions to immutable recommendation evidence. */
final class AddScenarioTreatmentRecommendations extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE scenario_treatment_recommendations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    anr_id INT UNSIGNED NOT NULL,
    treatment_id BIGINT UNSIGNED NOT NULL,
    recommendation_uuid CHAR(36) NOT NULL,
    recommendation_snapshot JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY scenario_treatment_recommendations_uuid (uuid),
    UNIQUE KEY scenario_treatment_recommendations_treatment_recommendation (treatment_id, recommendation_uuid),
    KEY scenario_treatment_recommendations_scope (anr_id, treatment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS scenario_treatment_recommendations');
    }
}
