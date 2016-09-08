<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddHistoricalTable extends AbstractMigration
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
        $table = $this->table('historicals');
        $table
            ->addColumn('type', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('source_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('action', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label1', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label2', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label3', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('label4', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('details', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_LONG))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addIndex(array('source_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
    }
}
