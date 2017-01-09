<?php

use Phinx\Migration\AbstractMigration;

class DeleteUser extends AbstractMigration
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
        $table = $this->table('passwords_tokens');
        $table->dropForeignKey('user_id')
            ->update();

        $table = $this->table('users_anrs');
        $table->dropForeignKey('user_id')
            ->update();

        $table = $this->table('users_roles');
        $table->dropForeignKey('user_id')
            ->update();

        $table = $this->table('passwords_tokens');
        $table
            ->addForeignKey('user_id', 'users', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

        $table = $this->table('users_anrs');
        $table
            ->addForeignKey('user_id', 'users', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();
        $table = $this->table('users_roles');
        $table
            ->addForeignKey('user_id', 'users', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();

    }
}
