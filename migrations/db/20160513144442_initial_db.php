<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class InitialDb extends AbstractMigration
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


        // Migration for table passwords_tokens
        $table = $this->table('passwords_tokens');
        $table
            ->addColumn('user_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('token', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('date_end', 'datetime', array('null' => true))
            ->addIndex(array('user_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();


        // Migration for table servers
        $table = $this->table('servers');
        $table
            ->addColumn('label', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('ip_address', 'string', array('null' => true, 'limit' => 64))
            ->addColumn('fqdn', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('status', 'integer', array('null' => true, 'default' => '1', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('creator', 'string', array('null' => true, 'default' => '', 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();


        // Migration for table user_tokens
        $table = $this->table('user_tokens');
        $table
            ->addColumn('user_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('token', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('date_end', 'datetime', array('null' => true))
            ->addIndex(array('user_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();


        // Migration for table users
        $table = $this->table('users');
        $table
            ->addColumn('date_start', 'date', array('null' => true))
            ->addColumn('date_end', 'date', array('null' => true))
            ->addColumn('status', 'integer', array('null' => true, 'default' => '1', 'limit' => MysqlAdapter::INT_TINY))
            ->addColumn('firstname', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('lastname', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('email', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('phone', 'string', array('null' => true, 'limit' => 20))
            ->addColumn('password', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('email'), array('unique' => true))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();


        // Migration for table users_roles
        $table = $this->table('users_roles');
        $table
            ->addColumn('user_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('role', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addIndex(array('user_id'))
            ->create();
        $table->changeColumn('id', 'integer',array('identity'=>true,'signed'=>false))->update();

        // Migration for table clients
        $table = $this->table('clients');
        $table
            ->addForeignKey('server_id', 'servers', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->addForeignKey('logo_id', 'clients', 'id', array('delete' => 'SET_NULL','update' => 'RESTRICT'))
            ->update();
        $table = $this->table('passwords_tokens');
        $table
            ->addForeignKey('user_id', 'users', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->update();
        $table = $this->table('user_tokens');
        $table
            ->addForeignKey('user_id', 'users', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->update();
        $table = $this->table('users_roles');
        $table
            ->addForeignKey('user_id', 'users', 'id', array('delete' => 'RESTRICT','update' => 'RESTRICT'))
            ->update();
    }
}
