<?php

use Phinx\Migration\AbstractMigration;

class FixIssueWithSpecificModel extends AbstractMigration
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
        //force the mode to 0, to only have generic items in FO
        $this->execute('UPDATE assets a SET a.mode = 0');
        $this->execute('UPDATE threats t SET t.mode = 0');
        $this->execute('UPDATE vulnerabilities v SET v.mode = 0');
        $this->execute('UPDATE objects o SET o.mode = 0');
    }
}
