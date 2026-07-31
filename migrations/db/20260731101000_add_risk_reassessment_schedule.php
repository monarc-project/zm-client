<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class AddRiskReassessmentSchedule extends AbstractMigration
{
    public function up(): void
    {
        $this->table('instances_risks')
            ->addColumn('next_reassessment_date', 'date', ['null' => true, 'after' => 'last_review_date'])
            ->update();
        $this->table('instances_risks_op')
            ->addColumn('next_reassessment_date', 'date', ['null' => true, 'after' => 'last_review_date'])
            ->update();

        $this->execute(
            'CREATE TABLE `instances_risks_reassessment_triggers` (
                `instance_risk_id` int(11) unsigned NOT NULL,
                `reassessment_trigger_id` int(11) unsigned NOT NULL,
                PRIMARY KEY (`instance_risk_id`, `reassessment_trigger_id`),
                KEY `instance_risk_reassessment_trigger_id_idx` (`reassessment_trigger_id`),
                CONSTRAINT `fk_instance_risk_reassessment_trigger_risk`
                    FOREIGN KEY (`instance_risk_id`) REFERENCES `instances_risks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_instance_risk_reassessment_trigger_trigger`
                    FOREIGN KEY (`reassessment_trigger_id`) REFERENCES `anr_reassessment_triggers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
        $this->execute(
            'CREATE TABLE `instances_risks_op_reassessment_triggers` (
                `instance_risk_op_id` int(11) unsigned NOT NULL,
                `reassessment_trigger_id` int(11) unsigned NOT NULL,
                PRIMARY KEY (`instance_risk_op_id`, `reassessment_trigger_id`),
                KEY `instance_risk_op_reassessment_trigger_id_idx` (`reassessment_trigger_id`),
                CONSTRAINT `fk_instance_risk_op_reassessment_trigger_risk`
                    FOREIGN KEY (`instance_risk_op_id`) REFERENCES `instances_risks_op` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_instance_risk_op_reassessment_trigger_trigger`
                    FOREIGN KEY (`reassessment_trigger_id`) REFERENCES `anr_reassessment_triggers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function down(): void
    {
        $this->table('instances_risks_op_reassessment_triggers')->drop()->save();
        $this->table('instances_risks_reassessment_triggers')->drop()->save();
        $this->table('instances_risks_op')->removeColumn('next_reassessment_date')->update();
        $this->table('instances_risks')->removeColumn('next_reassessment_date')->update();
    }
}
