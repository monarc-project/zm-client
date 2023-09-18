<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

class CreateCronTask extends AbstractMigration
{
    public function change()
    {
        $this->execute(
            'CREATE TABLE IF NOT EXISTS `cron_tasks` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `params` varchar(4096) NOT NULL default "a:0:{}",
                `priority` smallint(3) unsigned NOT NULL DEFAULT 1,
                `pid` int(11) DEFAULT NULL,
                `status` smallint(3) unsigned NOT NULL DEFAULT 0,
                `result_message` TEXT,
                `creator` varchar(255) NOT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updater` varchar(255) DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `cron_tasks_name` (`name`)
            );'
        );

        $this->table('anrs')
            ->addColumn(
                'status',
                'integer',
                ['null' => false, 'signed' => false, 'default' => 1, 'limit' => MysqlAdapter::INT_TINY]
            )
            ->update();
    }
}
