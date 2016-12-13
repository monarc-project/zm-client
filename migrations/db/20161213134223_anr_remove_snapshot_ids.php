<?php

use Phinx\Migration\AbstractMigration;

class AnrRemoveSnapshotIds extends AbstractMigration
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
        $table = $this->table('anrs');
        $exists = $table->hasForeignKey('snapshot_id');
        if ($exists) {
            $table->dropForeignKey('snapshot_id')->update();
        }
        $exists = $table->hasForeignKey('snapshot_ref_id');
        if ($exists) {
            $table->dropForeignKey('snapshot_ref_id')->update();
        }
        $table
            ->removeColumn('snapshot_id')
            ->removeColumn('snapshot_ref_id')
            ->update();

        $this->execute('
            DELETE ua
            FROM users_anrs AS ua
            INNER JOIN snapshots as s
            ON ua.anr_id = s.anr_id
        ');
    }

    public function down(){
        $table = $this->table('anrs');
        $table
            ->addColumn('snapshot_id', 'integer', array('null' => true, 'signed' => false))
            ->addColumn('snapshot_ref_id', 'integer', array('null' => true, 'signed' => false))
            ->addIndex(array('snapshot_id'))
            ->addIndex(array('snapshot_ref_id'))
            ->addForeignKey('snapshot_id', 'models', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->addForeignKey('snapshot_ref_id', 'models', 'id', array('delete' => 'CASCADE','update' => 'RESTRICT'))
            ->update();
    }
}
