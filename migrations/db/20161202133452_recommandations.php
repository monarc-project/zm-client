<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class Recommandations extends AbstractMigration
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
        // Migration for table recommandations
        $table = $this->table('recommandations');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('code', 'char', array('null' => true, 'limit' => 100))
            ->addColumn('description', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('importance', 'integer', array('null' => true, 'default' => 0, 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('position', 'integer', array('null' => true, 'default' => '0', 'limit' => 11))
            ->addColumn('comment', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('responsable', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('duedate', 'datetime', array('null' => true))
            ->addColumn('counter_treated', 'integer', array('null' => true, 'default' => '0', 'limit' => 11))
            ->addColumn('original_code', 'char', array('null' => true, 'limit' => 100))
            ->addColumn('token_import', 'char', array('null' => true, 'limit' => 13))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

        // Migration for table recommandations_measures
        $table = $this->table('recommandations_measures');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('recommandation_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('measure_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->addIndex(array('recommandation_id'))
            ->addIndex(array('measure_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

        // Migration for table recommandations_risks
        $table = $this->table('recommandations_risks');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('recommandation_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('instance_risk_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('instance_risk_op_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('instance_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('object_global_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('asset_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('threat_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('vulnerability_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->addIndex(array('recommandation_id'))
            ->addIndex(array('instance_risk_id'))
            ->addIndex(array('instance_risk_op_id'))
            ->addIndex(array('instance_id'))
            ->addIndex(array('object_global_id'))
            ->addIndex(array('asset_id'))
            ->addIndex(array('threat_id'))
            ->addIndex(array('vulnerability_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

        // Migrations ForeignKey
        $table = $this->table('recommandations');
        $table
            ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();
        $table = $this->table('recommandations_measures');
        $table
            ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('recommandation_id', 'recommandations', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('measure_id', 'measures', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();
        $table = $this->table('recommandations_risks');
        $table
            ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('recommandation_id', 'recommandations', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('instance_risk_id', 'instances_risks', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('instance_risk_op_id', 'instances_risks_op', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('instance_id', 'instances', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('object_global_id', 'objects', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('asset_id', 'assets', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('threat_id', 'threats', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('vulnerability_id', 'vulnerabilities', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();
    }
}
