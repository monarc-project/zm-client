<?php

use Phinx\Migration\AbstractMigration;

class AddClientFirstUserInfos extends AbstractMigration
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
        $table = $this->table('clients');
        $table
            ->addColumn('first_user_firstname', 'string', array('after' => 'contact_phone', 'null' => true, 'limit' => 255))
            ->addColumn('first_user_lastname', 'string', array('after' => 'first_user_firstname', 'null' => true, 'limit' => 255))
            ->addColumn('first_user_email', 'string', array('after' => 'first_user_lastname', 'null' => true, 'limit' => 255))
            ->addColumn('first_user_phone', 'string', array('after' => 'first_user_email', 'null' => true, 'limit' => 20))
            ->update();
    }
}
