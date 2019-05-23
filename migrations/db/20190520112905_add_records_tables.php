<?php

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
                ->addColumn('label1', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('label2', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('label3', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('label4', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('controller', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('representative', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('dpo', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('purposes', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('description', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('id_third_country', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('dpo_third_country', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('erasure', 'string', array('null' => false))
                ->addColumn('sec_measures', 'string', array('null' => true, 'limit' => 255))
                ->create();

            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))
                ->addIndex(array('anr_id'))->update();
            $table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();

            $table = $this->table('record_controllers');
            $table
                ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('label', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('contact', 'string', array('null' => false, 'limit' => 255))
                ->addIndex(array('anr_id'))
                ->create();

            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
            $table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();

    	$table = $this->table('record_processors');
            $table
                ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('label', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('contact', 'string', array('null' => false, 'limit' => 255))
                ->addColumn('id_third_country', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('dpo_third_country', 'string', array('null' => true, 'limit' => 255))
                ->addColumn('sec_measures', 'string', array('null' => true, 'limit' => 255))
                ->addIndex(array('anr_id'))
                ->create();

            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
    	$table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();


    	$table = $this->table('record_recipient_categories');
            $table
                ->addColumn('anr_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('label', 'string', array('null' => true, 'limit' => 255))
                ->create();

            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

            $table
                ->addIndex(array('anr_id'))
                ->update();
            $table
                ->addForeignKey('anr_id', 'anrs', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();

    	$table = $this->table('records');
            $table
    	    ->addForeignKey('controller', 'record_controllers', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
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
                ->addForeignKey('controller_id', 'record_controllers', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
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

    	$table = $this->table('records_record_recipient_categories');
            $table
                ->addColumn('record_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('recipient_category_id', 'integer', array('null' => false, 'signed' => false))
                ->addIndex(array('record_id'))
                ->addIndex(array('recipient_category_id'))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
     	$table
                ->addForeignKey('record_id', 'records', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->addForeignKey('recipient_category_id', 'record_recipient_categories', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();

    	$table = $this->table('record_processors_record_behalf_controllers');
            $table
                ->addColumn('processor_id', 'integer', array('null' => false, 'signed' => false))
                ->addColumn('controller_id', 'integer', array('null' => false, 'signed' => false))
                ->addIndex(array('processor_id'))
                ->addIndex(array('controller_id'))
                ->create();
            $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();
     	$table
                ->addForeignKey('processor_id', 'record_processors', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->addForeignKey('controller_id', 'record_controllers', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
                ->update();
    }
}
