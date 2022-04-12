<?php

use Phinx\Migration\AbstractMigration;

class AddTwoFaRelatedKeys extends AbstractMigration
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
        $table = $this->table('users');
        $table->addColumn('two_factor_enabled', 'boolean', array('default' => false, 'after' => 'password'));
        $table->addColumn('secret_key', 'string', array('null' => true, 'default' => '', 'after' => 'two_factor_enabled'));
        $table->addColumn('recovery_codes', 'string', array('null' => true, 'default' => '', 'after' => 'secret_key'));
        $table->update();

    }
}
