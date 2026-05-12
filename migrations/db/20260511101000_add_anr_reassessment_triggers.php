<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class AddAnrReassessmentTriggers extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE IF NOT EXISTS `anr_reassessment_triggers` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `anr_id` int(11) unsigned NOT NULL,
                `trigger_type` varchar(255) DEFAULT "",
                `description` text NOT NULL,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `position` int(11) NOT NULL DEFAULT 0,
                `creator` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updater` varchar(255) DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `anr_reassessment_triggers_anr_id_indx` (`anr_id`),
                KEY `anr_reassessment_triggers_anr_id_trigger_type_indx` (`anr_id`, `trigger_type`),
                KEY `anr_reassessment_triggers_anr_id_position_indx` (`anr_id`, `position`),
                CONSTRAINT `fk_anr_reassessment_triggers_anr`
                    FOREIGN KEY (`anr_id`) REFERENCES `anrs` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function down(): void
    {
        $this->table('anr_reassessment_triggers')->drop()->save();
    }
}
