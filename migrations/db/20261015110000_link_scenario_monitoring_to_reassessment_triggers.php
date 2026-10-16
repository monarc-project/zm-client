<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LinkScenarioMonitoringToReassessmentTriggers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('scenario_monitoring_observations')
            ->addColumn('reassessment_trigger_id', 'integer', [
                'null' => true,
                'signed' => false,
                'after' => 'risk_scenario_id',
            ])
            ->addIndex(['reassessment_trigger_id'], ['name' => 'scenario_monitoring_trigger_idx'])
            ->addForeignKey(
                'reassessment_trigger_id',
                'anr_reassessment_triggers',
                'id',
                ['delete' => 'RESTRICT', 'update' => 'RESTRICT', 'constraint' => 'fk_scenario_monitoring_trigger']
            )
            ->update();
    }

    public function down(): void
    {
        $this->table('scenario_monitoring_observations')
            ->dropForeignKey('reassessment_trigger_id')
            ->removeIndex(['reassessment_trigger_id'])
            ->removeColumn('reassessment_trigger_id')
            ->update();
    }
}
