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
      // Migration for table referentials it appears that we can set a function as default value, so the uuid has to be managed via php or a trigger
      $table = $this->table('referentials');
      $table
          ->addColumn('anr_id', 'integer', array('null' => true, 'signed' => false))
          ->addColumn('uuid', 'uuid')
          ->addColumn('label1', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('label2', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('label3', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('label4', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('created_at', 'datetime', array('null' => true))
          ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
          ->addColumn('updated_at', 'datetime', array('null' => true))
          ->addIndex(array('uuid'))
          ->addIndex(['uuid','anr_id'], ['unique' => true])
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
              'anr_id' => $anr_id['id'],
              'uuid' => '98ca84fb-db87-11e8-ac77-0800279aaa2b',
              'label1' => 'ISO 27002',
              'label2' => 'ISO 27002',
              'label3' => 'ISO 27002',
              'label4' => 'ISO 27002',
              'creator' => 'Migration script',
              'created_at' => date('Y-m-d H:i:s')
          ];
      }
      if(count($referentials)>0)
        $this->insert("referentials", $referentials);
      //remove the id
      $table->removeColumn('id')
            ->dropForeignKey('anr_id')
            ->save();
      $this->execute("ALTER TABLE referentials ADD PRIMARY KEY uuid_anr_id (uuid, anr_id)");

      //add foreign key for measures
      $table = $this->table('measures');
      $table
          ->addColumn('referential_uuid', 'uuid', ['after' => 'soacategory_id'])
          ->addIndex(['referential_uuid', 'code', 'anr_id'], ['unique' => true]) // we can't have 2 times the same code for same referential
          ->dropForeignKey('anr_id')
          ->save();
      $this->execute('UPDATE measures m SET m.referential_uuid=(SELECT uuid FROM referentials LIMIT 1) ;');
      $table
          ->addForeignKey(['referential_uuid', 'anr_id'], 'referentials', ['uuid', 'anr_id'], array('delete' => 'CASCADE','update' => 'RESTRICT'))
          ->update();

      //add foreign key for the category
      $table = $this->table('soacategory');
      $table
          ->addColumn('referential_uuid', 'uuid')
          ->update();
      $this->execute('UPDATE soacategory s SET s.referential_uuid=(SELECT uuid FROM referentials LIMIT 1) ;');
      $table
      ->addForeignKey(['referential_uuid', 'anr_id'], 'referentials', ['uuid', 'anr_id'], array('delete' => 'CASCADE','update' => 'RESTRICT'))
          ->update();
    }
}
