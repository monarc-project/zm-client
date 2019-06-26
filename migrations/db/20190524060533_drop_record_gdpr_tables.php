<?php

use Phinx\Migration\AbstractMigration;

class DropRecordGdprTables extends AbstractMigration
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
        $table = $this->table('records');
        $table
            ->dropForeignKey('anr_id')
            ->dropForeignKey('controller')
            ->dropForeignKey('dpo')
            ->dropForeignKey('representative')
            ->save();

        $table = $this->table('record_actors');
        $table
            ->dropForeignKey('anr_id')
            ->save();

        $table = $this->table('record_data_categories');
        $table
            ->dropForeignKey('anr_id')
            ->save();

        $table = $this->table('record_data_subjects');
        $table
            ->dropForeignKey('anr_id')
            ->save();

        $table = $this->table('record_international_transfers');
        $table
            ->dropForeignKey('anr_id')
            ->dropForeignKey('record_id')
            ->save();

        $table = $this->table('record_personal_data');
        $table
            ->dropForeignKey('anr_id')
            ->dropForeignKey('record_id')
            ->save();

        $table = $this->table('record_processors');
        $table
            ->dropForeignKey('anr_id')
            ->save();


        $table = $this->table('record_recipients');
        $table
            ->dropForeignKey('anr_id')
            ->save();

        $table = $this->table('records_record_joint_controllers');
        $table
            ->dropForeignKey('record_id')
            ->dropForeignKey('controller_id')
            ->save();

        $table = $this->table('records_record_processors');
        $table
            ->dropForeignKey('record_id')
            ->dropForeignKey('processor_id')
            ->save();

        $table = $this->table('records_record_recipients');
        $table
            ->dropForeignKey('record_id')
            ->dropForeignKey('recipient_id')
            ->save();

        $table = $this->table('record_personal_data_record_data_subjects');
        $table
            ->dropForeignKey('personal_data_id')
            ->dropForeignKey('data_subject_id')
            ->save();

        $table = $this->table('record_personal_data_record_data_categories');
        $table
            ->dropForeignKey('personal_data_id')
            ->dropForeignKey('data_category_id')
            ->save();

        $table = $this->table('record_processors_record_actors');
        $table
                ->dropForeignKey('processor_id')
                ->dropForeignKey('actor_id')
                ->save();
        $this->table('records')->drop();
        $this->table('record_actors')->drop();
        $this->table('record_data_categories')->drop();
        $this->table('record_data_subjects')->drop();
        $this->table('record_international_transfers')->drop();
        $this->table('record_personal_data')->drop();
        $this->table('record_processors')->drop();
        $this->table('record_recipients')->drop();
        $this->table('records_record_joint_controllers')->drop();
        $this->table('records_record_processors')->drop();
        $this->table('records_record_recipients')->drop();
        $this->table('record_personal_data_record_data_subjects')->drop();
        $this->table('record_personal_data_record_data_categories')->drop();
        $this->table('record_processors_record_actors')->drop();
    }
}
