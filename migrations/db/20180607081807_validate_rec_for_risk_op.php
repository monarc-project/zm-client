<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class ValidateRecForRiskOp extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('recommandations_historics');
        $table
            ->addColumn('instance_risk_op_id', 'integer', array('null' => true, 'signed' => false))
            ->addForeignKey('instance_risk_op_id', 'instances_risks_op', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addColumn('risk_op_description', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('net_prob_before', 'integer', array('null' => true, 'default' => '-1', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('net_r_before', 'integer', array('null' => true, 'default' => '-1', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('net_o_before', 'integer', array('null' => true, 'default' => '-1', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('net_l_before', 'integer', array('null' => true, 'default' => '-1', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('net_f_before', 'integer', array('null' => true, 'default' => '-1', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('net_p_before', 'integer', array('null' => true, 'default' => '-1', 'limit' => MysqlAdapter::INT_TINY))
            ->update();

    }
}
