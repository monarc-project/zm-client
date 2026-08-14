<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class CreateAnrHistoryTable extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE IF NOT EXISTS `anr_history` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `anr_id` INT UNSIGNED NOT NULL,
                `target_type` SMALLINT UNSIGNED NOT NULL,
                `target_id` INT UNSIGNED NOT NULL,
                `change_type` SMALLINT UNSIGNED NOT NULL,
                `field_code` VARCHAR(100) NULL DEFAULT NULL,
                `old_value` LONGTEXT NULL DEFAULT NULL,
                `new_value` LONGTEXT NULL DEFAULT NULL,
                `performed_by_firstname` VARCHAR(255) NULL DEFAULT NULL,
                `performed_by_lastname` VARCHAR(255) NULL DEFAULT NULL,
                `performed_by_email` VARCHAR(255) NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_anr_history_anr_id` (`anr_id`),
                INDEX `idx_anr_history_target` (`target_type`, `target_id`),
                INDEX `idx_anr_history_change_type` (`change_type`),
                INDEX `idx_anr_history_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;'
        );
    }

    public function down(): void
    {
        $this->table('anr_history')->drop()->save();
    }
}
