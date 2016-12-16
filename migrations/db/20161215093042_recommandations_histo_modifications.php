<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class RecommandationsHistoModifications extends AbstractMigration
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
            ->addColumn('instance_risk_id', 'integer', array('null' => true, 'signed' => false, 'after' => 'anr_id'))
            ->addColumn('final', 'integer', array('null' => true, 'default' => 1, 'limit' => 11, 'after' => 'instance_risk_id'))
            ->addColumn('cache_comment_after', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->update();

        $table = $this->table('recommandations_risks');
        $table
            ->addColumn('comment_after', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG, 'after' => 'vulnerability_id'))
            ->update();
    }
}
