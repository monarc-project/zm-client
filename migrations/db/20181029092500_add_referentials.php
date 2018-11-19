<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Util\Literal;

class AddReferentials extends AbstractMigration
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
      // Migration for table referentials it appears that we can set a function as default value, so the uniqid has to be managed via php or a trigger
      $table = $this->table('referentials');
      $table
          ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
          ->addColumn('uniqid', 'uuid')
          ->addColumn('label1', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('label2', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('label3', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('label4', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('created_at', 'datetime', array('null' => true))
          ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('updated_at', 'datetime', array('null' => true))
          ->addIndex(array('uniqid'))
          ->create();

      $table->changeColumn('id', 'integer', array('identity'=>true,'signed'=>false))->update();

      $table
          ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
          ->update();

      // Create a defaut ISO 27002 referential for each analysis
      $referentials = [];
      $anr_ids = $this->fetchAll('SELECT id FROM anrs');
      foreach ($anr_ids as $anr_id) {
          $referentials[] = [
              'id' => '',
              'anr_id' => $anr_id['id'],
              'uniqid' => '98ca84fb-db87-11e8-ac77-0800279aaa2b',
              'label1' => 'ISO 27002',
              'label2' => 'ISO 27002',
              'label3' => 'ISO 27002',
              'label4' => 'ISO 27002'
          ];
      }
      $this->insert("referentials", $referentials);

      //add foreign key for measures
      $table = $this->table('measures');
      $table
          ->addColumn('referential_uniqid', 'uuid', ['after' => 'soacategory_id'])
          ->update();
      $this->execute('UPDATE measures m SET m.referential_uniqid=(SELECT uniqid FROM referentials LIMIT 1) ;');
      $table
          ->addForeignKey('referential_uniqid', 'referentials', 'uniqid', array('delete' => 'CASCADE','update' => 'RESTRICT'))
          ->update();

      //add foreign key for the category
      $table = $this->table('soacategory');
      $table
          ->addColumn('referential_uniqid', 'uuid', ['after' => 'code'])
          ->update();
      $this->execute('UPDATE soacategory s SET s.referential_uniqid=(SELECT uniqid FROM referentials LIMIT 1) ;');
      $table
          ->addForeignKey('referential_uniqid', 'referentials', 'uniqid', array('delete' => 'CASCADE','update' => 'RESTRICT'))
          ->update();
    }
}