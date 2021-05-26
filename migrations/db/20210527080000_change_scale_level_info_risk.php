<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Util\Literal;
use Ramsey\Uuid\Uuid;

class ChangeScaleLevelInfoRisk extends AbstractMigration
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
        $conn = $this->getAdapter()->getConnection();

        // Migration for table scales_comments
        $table = $this->table('scales_comments');
        $table
            ->addColumn('scale_index', 'integer', array('null' => true, 'signed' => false, 'after' => 'val'))
            ->update();

        $this->execute('update scales_comments set scale_index = val where scale_index is null');

    }
}
