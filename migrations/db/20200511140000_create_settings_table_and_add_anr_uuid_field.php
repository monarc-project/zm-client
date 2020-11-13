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

        $this->execute('INSERT INTO `settings` (`name`, `value`, `creator`) VALUES ("stats", "{\"is_sharing_enabled\": true}", "system");');

        $this->execute(
            'ALTER TABLE `anrs` ADD `uuid` char(36) NULL AFTER `id`,
             ADD `is_visible_on_dashboard` TINYINT(1) default 1;'
        );

        $snapshotsAnrsIds = $this->query('SELECT DISTINCT `anr_id` from `snapshots`')->fetchAll();
        $snapshotsAnrsIds = !empty($snapshotsAnrsIds) ? array_column($snapshotsAnrsIds, 'anr_id') : [];
        $anrs = $this->query('SELECT `id`, `uuid` from `anrs`')->fetchAll();
        foreach ($anrs as $anr) {
            $updateFieldsSql = '';
            if (!$anr['uuid']) {
                $updateFieldsSql = '`uuid` = "' . Uuid::uuid4();
            }
            if (in_array($anr['id'], $snapshotsAnrsIds)) {
                $updateFieldsSql .= ($updateFieldsSql ? ', ' : '') . '`is_visible_on_dashboard` = 0';
            }
            if ($updateFieldsSql) {
                $this->execute(
                    'UPDATE `anrs` SET ' . $updateFieldsSql .
                    ' WHERE `id` = ' . $anr['id']
                );
            }
        }

        $this->execute(
            'ALTER TABLE `anrs`
                MODIFY COLUMN `uuid` char(36) NOT NULL,
                ADD UNIQUE INDEX `anrs_uuid_unq` (`uuid`);'
        );
    }
}
