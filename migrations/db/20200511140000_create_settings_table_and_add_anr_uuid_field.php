<?php

use Phinx\Migration\AbstractMigration;
use Ramsey\Uuid\Uuid;

class CreateSettingsTableAndAddAnrUuidField extends AbstractMigration
{
    public function change()
    {
        $this->execute(
            'CREATE TABLE IF NOT EXISTS `settings` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `value` text,
                `creator` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updater` varchar(255) DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `settings_name_unq` (`name`)
            );'
        );

        $apiKey = Uuid::uuid4();
        $this->execute(sprintf(
            'INSERT INTO `settings` (`name`, `value`, `creator`)
             VALUES ("stats", "{\"is_sharing_enabled\": true, \"api_key\": \"%s\"}", "system");', $apiKey));

        $this->execute('ALTER TABLE `anrs` ADD `uuid` char(36) NULL AFTER `id`;');

        $anrs = $this->query('SELECT `id` from `anrs`')->fetchAll();
        foreach ($anrs as $anr) {
            $this->execute(
                'UPDATE `anrs` SET `uuid` = "' . Uuid::uuid4() . '"' .
                ' WHERE `id` = ' . $anr['id']
            );
        }

        $this->execute(
            'ALTER TABLE `anrs`
                MODIFY COLUMN `uuid` char(36) NOT NULL,
                ADD UNIQUE INDEX `anrs_uuid_unq` (`uuid`),
                ADD COLUMN `is_visible_on_dashboard` TINYINT(1) default 1;'
        );
    }
}
