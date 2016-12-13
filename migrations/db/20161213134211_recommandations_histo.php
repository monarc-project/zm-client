<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class RecommandationsHisto extends AbstractMigration
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
        // Migration for table recommandations historics
        $table = $this->table('recommandations_historics');
        $table
            ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('impl_comment', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('reco_code', 'char', array('null' => true, 'limit' => 100))
            ->addColumn('reco_description', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('reco_importance', 'integer', array('null' => true, 'default' => 0, 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('reco_comment', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('reco_responsable', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('reco_duedate', 'datetime', array('null' => true))
            ->addColumn('risk_instance', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('risk_instance_context', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('risk_asset', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('risk_threat', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('risk_threat_val', 'integer', array('null' => true, 'default' => '-1', 'limit' => 11))
            ->addColumn('risk_vul', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('risk_vul_val_before', 'integer', array('null' => true, 'default' => '-1', 'limit' => 11))
            ->addColumn('risk_vul_val_after', 'integer', array('null' => true, 'default' => '-1', 'limit' => 11))
            ->addColumn('risk_kind_of_measure', 'integer', array('null' => true, 'default' => '-1', 'limit' => 11))
            ->addColumn('risk_comment_before', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('risk_comment_after', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('risk_max_risk_before', 'integer', array('null' => true, 'default' => '-1', 'limit' => 11))
            ->addColumn('risk_color_before', 'char', array('null' => true, 'limit' => 100))
            ->addColumn('risk_max_risk_after', 'integer', array('null' => true, 'default' => '-1', 'limit' => 11))
            ->addColumn('risk_color_after', 'char', array('null' => true, 'limit' => 100))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

        // Migrations ForeignKey
        $table = $this->table('recommandations');
        $table
            ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();
    }
}
