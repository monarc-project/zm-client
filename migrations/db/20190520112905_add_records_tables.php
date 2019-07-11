<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

class AddRecordsTables extends AbstractMigration
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
    	$table = $this->table('records');
            $table
                ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('label', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('purposes', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('sec_measures', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('controller_id', 'integer', array('null' => true, 'signed' => false))
                ->addColumn('representative_id', 'integer', array('null' => true, 'signed' => false))
                ->addColumn('dpo_id', 'integer', array('null' => true, 'signed' => false))
                ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('created_at', 'datetime', array('null' => true))
                ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('updated_at', 'datetime', array('null' => true))
                ->addIndex(array('anr_id'))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))
                ->update();
            $table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();

            $table = $this->table('record_actors');
            $table
                ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('label', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('contact', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('created_at', 'datetime', array('null' => true))
                ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('updated_at', 'datetime', array('null' => true))
                ->addIndex(array('anr_id'))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
            $table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();

            $table = $this->table('record_data_categories');
            $table
                ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('label', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('created_at', 'datetime', array('null' => true))
                ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('updated_at', 'datetime', array('null' => true))
                ->addIndex(array('anr_id'))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
            $table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();

            $table = $this->table('record_international_transfers');
            $table
                ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('record_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('organisation', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('description', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('country', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('documents', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('created_at', 'datetime', array('null' => true))
                ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('updated_at', 'datetime', array('null' => true))
                ->addIndex(array('anr_id'))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
            $table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->addForeignKey('record_id', 'records', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();

            $table = $this->table('record_personal_data');
            $table
                ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('record_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('data_subject', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('description', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('retention_period', 'integer', array('null' => true, 'limit' => 255))
                ->addColumn('retention_period_mode', 'integer', array('null' => true, 'limit' => MysqlAdapter::INT_TINY))
                ->addColumn('retention_period_description', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('created_at', 'datetime', array('null' => true))
                ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('updated_at', 'datetime', array('null' => true))
                ->addIndex(array('anr_id'))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
            $table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->addForeignKey('record_id', 'records', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();

    	$table = $this->table('record_processors');
            $table
                ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('label', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('contact', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('activities', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('sec_measures', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('representative', 'integer', array('null' => true, 'signed' => false))
                ->addColumn('dpo', 'integer', array('null' => true, 'signed' => false))
                ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('created_at', 'datetime', array('null' => true))
                ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('updated_at', 'datetime', array('null' => true))
                ->addIndex(array('anr_id'))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
    	    $table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();


    	$table = $this->table('record_recipients');
        $table
            ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
            ->addColumn('label', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('type', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('description', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('anr_id'))
            ->create();

        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
        $table
            ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

    	$table = $this->table('records');
        $table
	    ->addForeignKey('controller_id', 'record_actors', 'id', array('delete' => 'SET NULL','update' => 'RESTRICT'))
	    ->addForeignKey('dpo_id', 'record_actors', 'id', array('delete' => 'SET NULL','update' => 'RESTRICT'))
	    ->addForeignKey('representative_id', 'record_actors', 'id', array('delete' => 'SET NULL','update' => 'RESTRICT'))
            ->update();

        $table = $this->table('records_record_joint_controllers');
        $table
            ->addColumn('record_id', 'integer', array('null' => false, 'signed' => false))
            ->addColumn('controller_id', 'integer', array('null' => false, 'signed' => false))
            ->addIndex(array('record_id'))
            ->addIndex(array('controller_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
        $table
            ->addForeignKey('record_id', 'records', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('controller_id', 'record_actors', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

    	$table = $this->table('records_record_recipients');
        $table
            ->addColumn('record_id', 'integer', array('null' => false, 'signed' => false))
            ->addColumn('recipient_id', 'integer', array('null' => false, 'signed' => false))
            ->addIndex(array('record_id'))
            ->addIndex(array('recipient_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
     	$table
            ->addForeignKey('record_id', 'records', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('recipient_id', 'record_recipients', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

    	$table = $this->table('records_record_processors');
        $table
            ->addColumn('record_id', 'integer', array('null' => false, 'signed' => false))
            ->addColumn('processor_id', 'integer', array('null' => false, 'signed' => false))
            ->addIndex(array('record_id'))
            ->addIndex(array('processor_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
     	$table
            ->addForeignKey('record_id', 'records', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('processor_id', 'record_processors', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

        $table = $this->table('record_personal_data_record_data_categories');
        $table
            ->addColumn('personal_data_id', 'integer', array('null' => false, 'signed' => false))
            ->addColumn('data_category_id', 'integer', array('null' => false, 'signed' => false))
            ->addIndex(array('personal_data_id'))
            ->addIndex(array('data_category_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
     	$table
            ->addForeignKey('personal_data_id', 'record_personal_data', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('data_category_id', 'record_data_categories', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();
    }
}
