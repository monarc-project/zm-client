<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddOwnerContextRisks extends AbstractMigration
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
            'CREATE TABLE IF NOT EXISTS `instance_risk_owners` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `anr_id` int(11) unsigned NOT NULL,
                `name` varchar(255) NOT NULL,
                `creator` varchar(255) NOT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updater` varchar(255) DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `instance_risk_owners_anr_id_indx` (`anr_id`),
                UNIQUE `instance_risk_owners_anr_id_name_unq` (`anr_id`, `name`),
                CONSTRAINT `instance_risk_owners_anr_id_fk` FOREIGN KEY(`anr_id`) REFERENCES anrs (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
            );'
        );

        // Migration for table instances_risks
        $table = $this->table('instances_risks');
        $table
            ->addColumn('owner_id', 'integer', array('null' => true, 'signed' => false, 'after' => 'instance_id'))
            ->addForeignKey('owner_id', 'instance_risk_owners', 'id', ['delete'=> 'CASCADE', 'update'=> 'RESTRICT'])
            ->addColumn('context', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR, 'after' => 'owner_id'))
            ->update();

        // Migration for table instances_risks_op
        $table = $this->table('instances_risks_op');
        $table
            ->addColumn('owner_id', 'integer', array('null' => true, 'signed' => false, 'after' => 'instance_id'))
            ->addForeignKey('owner_id', 'instance_risk_owners', 'id', ['delete'=> 'CASCADE', 'update'=> 'RESTRICT'])
            ->addColumn('context', 'text', array('null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR, 'after' => 'owner_id'))
            ->update();
    }
}
