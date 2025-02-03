<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2025 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class CreateActionsHistoryTable extends AbstractMigration
{
    public function change()
    {
        $this->execute(
            'CREATE TABLE IF NOT EXISTS `actions_history` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned DEFAULT NULL,
                `action` varchar(100) NOT NULL,
                `data` TEXT,
                `status` smallint(3) unsigned NOT NULL DEFAULT 0,
                `creator` varchar(255) NOT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                CONSTRAINT `actions_history_user_id_id_fk1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL);'
        );
    }
}
