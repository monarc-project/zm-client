<?php

use Phinx\Migration\AbstractMigration;

class CreateStatsTables extends AbstractMigration
{
    public function change()
    {
        $this->execute(
            'CREATE TABLE IF NOT EXISTS `settings` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `value` text,
                `creator` varchar(255) DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updater` varchar(255) DEFAULT NULL,
                `updated_at` timestamp DEFAULT 0,
                PRIMARY KEY (`id`)
            );'
        );

        $this->execute('INSERT INTO `settings` (`name`, `value`, `creator`)
                        VALUES ("is_stats_enabled", "1", "system"),
                               ("is_stats_sharing_enabled", "1", "system");');

        $this->execute(
            'CREATE TABLE IF NOT EXISTS `stats_anrs` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `anr_id` int(11) unsigned NOT NULL,
                `day` tinyint(4) unsigned NOT NULL,
                `week` tinyint(4) unsigned NOT NULL,
                `month` tinyint(4) unsigned NOT NULL,
                `year` tinyint(4) unsigned NOT NULL,
                `type` varchar(20) NOT NULL,
                `stats_data` text,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `creator` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `stats_anrs_anr_id_type` (`anr_id`, `type`),
                CONSTRAINT `stats_anrs_anr_id_fk` FOREIGN KEY (`anr_id`) REFERENCES `anrs` (`id`) ON DELETE CASCADE
            );'
        );
    }
}
