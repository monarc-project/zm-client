<?php

use Phinx\Migration\AbstractMigration;

class FixTextFieldsConsistencyRecordPersonalData extends AbstractMigration
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
            'ALTER TABLE `record_personal_data` CHANGE `retention_period_description` `retention_period_description` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;'
        );
    }
}
