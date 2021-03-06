<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Util\Literal;

class AddMeasuresMeasures extends AbstractMigration
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
      // Migration for table measures_measures to link measures set primary key composed of id of measures to avoid duplicata
      $table = $this->table('measures_measures');
      $table
          ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
          ->addColumn('father_id', 'integer', array('null' => false, 'signed' => false))
          ->addColumn('child_id', 'integer', array('null' => false, 'signed' => false))
          ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('created_at', 'datetime', array('null' => true))
          ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('updated_at', 'datetime', array('null' => true))
          ->addIndex(array('father_id'))
          ->addIndex(array('child_id'))
          ->create();
      $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

      $table
          ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
          ->update();
    }
}
