<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Preserves readable evidence when a referenced MONARC record is later renamed. */
final class AddScenarioReferenceSnapshots extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'ALTER TABLE scenario_risk_sources ADD reference_snapshot TEXT NULL AFTER legacy_risk_source_uuid'
        );
        $this->execute(
            'ALTER TABLE scenario_causes ADD threat_reference_snapshot TEXT NULL AFTER threat_reference_uuid'
        );
        $this->execute(
            'ALTER TABLE scenario_causes ADD vulnerability_reference_snapshot TEXT NULL AFTER vulnerability_reference_uuid'
        );
        $this->execute(
            'ALTER TABLE scenario_subject_links ADD reference_snapshot TEXT NULL AFTER subject_reference_uuid'
        );
        $this->execute(
            'ALTER TABLE scenario_control_links ADD reference_snapshot TEXT NULL AFTER control_reference_uuid'
        );
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE scenario_risk_sources DROP COLUMN reference_snapshot');
        $this->execute('ALTER TABLE scenario_causes DROP COLUMN threat_reference_snapshot');
        $this->execute('ALTER TABLE scenario_causes DROP COLUMN vulnerability_reference_snapshot');
        $this->execute('ALTER TABLE scenario_subject_links DROP COLUMN reference_snapshot');
        $this->execute('ALTER TABLE scenario_control_links DROP COLUMN reference_snapshot');
    }
}
