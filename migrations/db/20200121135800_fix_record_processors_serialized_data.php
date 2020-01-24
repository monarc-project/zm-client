<?php

use Phinx\Migration\AbstractMigration;

class FixRecordProcessorsSerializedData extends AbstractMigration
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
        $this->execute(
            'update record_processors set
                `activities` = SUBSTRING(IF(SUBSTRING_INDEX(`activities`, \':"\', -1)=\'a:0:{}\', \'\', SUBSTRING_INDEX(`activities`, \':"\', -1)), 1, CHARACTER_LENGTH(SUBSTRING_INDEX(`activities`, \':"\', -1)) - 3),
                `sec_measures` = SUBSTRING(IF(SUBSTRING_INDEX(`sec_measures`, \':"\', -1)=\'a:0:{}\', \'\', SUBSTRING_INDEX(`sec_measures`, \':"\', -1)), 1, CHARACTER_LENGTH(SUBSTRING_INDEX(`sec_measures`, \':"\', -1)) - 3)
            where `activities` LIKE \'%}\' OR `sec_measures` LIKE \'%}\';'
        );
    }
}
