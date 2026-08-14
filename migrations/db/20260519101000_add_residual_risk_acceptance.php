<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class AddResidualRiskAcceptance extends AbstractMigration
{
    public function up(): void
    {
        $this->table('instances_risks')
            ->addColumn('residual_risk_decision', 'string', ['limit' => 20, 'null' => true, 'after' => 'review_frequency'])
            ->addColumn('residual_risk_approved_by', 'string', ['limit' => 255, 'null' => true, 'after' => 'residual_risk_decision'])
            ->addColumn('residual_risk_approved_at', 'date', ['null' => true, 'after' => 'residual_risk_approved_by'])
            ->addColumn('residual_risk_justification', 'text', ['null' => true, 'after' => 'residual_risk_approved_at'])
            ->update();

        $this->table('instances_risks_op')
            ->addColumn('last_review_date', 'date', ['null' => true, 'after' => 'mitigation'])
            ->addColumn('review_frequency', 'string', ['limit' => 50, 'null' => true, 'after' => 'last_review_date'])
            ->addColumn('residual_risk_decision', 'string', ['limit' => 20, 'null' => true, 'after' => 'review_frequency'])
            ->addColumn('residual_risk_approved_by', 'string', ['limit' => 255, 'null' => true, 'after' => 'residual_risk_decision'])
            ->addColumn('residual_risk_approved_at', 'date', ['null' => true, 'after' => 'residual_risk_approved_by'])
            ->addColumn('residual_risk_justification', 'text', ['null' => true, 'after' => 'residual_risk_approved_at'])
            ->addColumn('risk_source_id', 'integer', ['null' => true, 'signed' => false, 'after' => 'object_id'])
            ->addIndex(['risk_source_id'], ['name' => 'risk_source_id'])
            ->addForeignKey('risk_source_id', 'risk_sources', 'id', ['delete' => 'SET_NULL', 'update' => 'RESTRICT'])
            ->update();
    }

    public function down(): void
    {
        $this->table('instances_risks')
            ->removeColumn('residual_risk_justification')
            ->removeColumn('residual_risk_approved_at')
            ->removeColumn('residual_risk_approved_by')
            ->removeColumn('residual_risk_decision')
            ->update();

        $this->table('instances_risks_op')
            ->removeColumn('residual_risk_justification')
            ->removeColumn('residual_risk_approved_at')
            ->removeColumn('residual_risk_approved_by')
            ->removeColumn('residual_risk_decision')
            ->removeColumn('review_frequency')
            ->removeColumn('last_review_date')
            ->dropForeignKey('risk_source_id')
            ->removeIndexByName('risk_source_id')
            ->removeColumn('risk_source_id')
            ->update();
    }
}
