<?php

use Phinx\Migration\AbstractMigration;

class ObjectRemoveModel extends AbstractMigration
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
        $table = $this->table('objects');
        $exists = $table->hasForeignKey('model_id');
        if ($exists) {
            $table->dropForeignKey('model_id');
        }
        $table
            ->removeColumn('model_id')
            ->update();
    }

    public function down(){
        $table = $this->table('objects');
        $table
            ->addColumn('model_id', 'integer', array('null' => true, 'signed' => false))
            ->addIndex(array('model_id'))
            ->addForeignKey('model_id', 'models', 'id', array('delete' => 'SET_NULL','update' => 'RESTRICT'))
            ->update();
    }
}
