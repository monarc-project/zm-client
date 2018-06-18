<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;


class UpdateSoa extends AbstractMigration
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
      $this->table('Soa')
      ->addColumn('EX', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
      ->addColumn('LR', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
      ->addColumn('CO', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
      ->addColumn('BR', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
      ->addColumn('BP', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
      ->addColumn('RRA', 'integer', array('null' => true, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY))
      ->save();
    }
}
