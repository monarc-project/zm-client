<?php

use Phinx\Migration\AbstractMigration;

class AddSeveralModelsPerClient extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        // Migration for table clients_models
        $table = $this->table('clients_models');
        $table
            ->addColumn('model_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('client_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('creator', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('created_at', 'datetime', array('null' => true))
            ->addColumn('updater', 'string', array('null' => true, 'limit' => 255))
            ->addColumn('updated_at', 'datetime', array('null' => true))
            ->addIndex(array('model_id'))
            ->addIndex(array('client_id'))
            ->create();
        $table->changeColumn('id', 'integer', array('identity'=>true, 'signed'=>false))->update();

        // adding foreignKey
        $table = $this->table('clients_models');
        $table
            ->addForeignKey('client_id', 'clients', 'id', array('delete' => 'CASCADE', 'update' => 'RESTRICT'))
            ->update();

        //migrate the datas
        $this->execute('INSERT INTO clients_models (client_id, model_id)
            SELECT id, model_id FROM clients;');

        // TO DO : uncomment the following line
        // $this->execute('ALTER TABLE clients DROP COLUMN model_id');
    }
}
