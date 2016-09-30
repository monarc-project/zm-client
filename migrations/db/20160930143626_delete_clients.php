<?php

use Phinx\Migration\AbstractMigration;

class DeleteCLients extends AbstractMigration
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
    public function up()
    {
        $this->table('clients')
            ->dropForeignKey('server_id')
            ->dropForeignKey('logo_id')
            ->update();

        $this->dropTable('clients');
    }

    public function down()
    {
        // Migration for table clients
        $table = $this->table('clients');
        $table
            ->addColumn('model_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('server_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('logo_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('country_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('city_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('name', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('proxy_alias', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('address', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('postalcode', 'string', array('null' => true, 'limit' => 16))
            ->addColumn('phone', 'string', array('null' => true, 'limit' => 20))
            ->addColumn('fax', 'string', array('null' => true, 'limit' => 20))
            ->addColumn('email', 'string', array('null' => true, 'default' => '', 'limit' => 255))
            ->addColumn('employees_number', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('contact_fullname', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('contact_email', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('contact_phone', 'string', array('null' => true, 'limit' => 20))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('model_id'))
            ->addIndex(array('server_id'))
            ->addIndex(array('logo_id'))
            ->addIndex(array('country_id'))
            ->addIndex(array('city_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

        $table = $this->table('clients');
        $table
            ->addForeignKey('server_id', 'servers', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->addForeignKey('logo_id', 'clients', 'id', array('delete' => 'SET_NULL','update' => 'RESTRICT'))
            ->update();
    }
}
