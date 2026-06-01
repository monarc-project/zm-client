<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class AddRiskSources extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `risk_sources` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `anr_id` int(11) unsigned NOT NULL,
                `label` varchar(255) NOT NULL,
                `is_default` tinyint(1) NOT NULL DEFAULT 0,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `creator` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updater` varchar(255) DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `risk_sources_anr_id_indx` (`anr_id`),
                KEY `risk_sources_anr_id_is_active_indx` (`anr_id`, `is_active`),
                UNIQUE `risk_sources_anr_id_label_unq` (`anr_id`, `label`),
                FOREIGN KEY (`anr_id`) REFERENCES `anrs` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $anrIds = $this->fetchAll('SELECT id FROM anrs');
        foreach ($anrIds as $anr) {
            $anrId = $anr['id'];
            $this->execute(
                "INSERT IGNORE INTO `risk_sources` (`anr_id`, `label`, `is_default`, `is_active`, `creator`, `created_at`) VALUES
                ({$anrId}, 'External attacker', 1, 1, 'System', NOW()),
                ({$anrId}, 'Internal malicious user', 1, 1, 'System', NOW()),
                ({$anrId}, 'Internal accidental user', 1, 1, 'System', NOW()),
                ({$anrId}, 'Supplier / third party', 1, 1, 'System', NOW()),
                ({$anrId}, 'System failure', 1, 1, 'System', NOW()),
                ({$anrId}, 'Software defect', 1, 1, 'System', NOW()),
                ({$anrId}, 'Natural event', 1, 1, 'System', NOW()),
                ({$anrId}, 'Organizational or process weakness', 1, 1, 'System', NOW()),
                ({$anrId}, 'Other', 1, 1, 'System', NOW());"
            );
        }

        $this->table('instances_risks')
            ->addColumn('risk_source_id', 'integer', ['null' => true, 'signed' => false, 'after' => 'asset_id'])
            ->addIndex(['risk_source_id'], ['name' => 'risk_source_id'])
            ->addForeignKey('risk_source_id', 'risk_sources', 'id', ['delete' => 'SET_NULL', 'update' => 'RESTRICT'])
            ->update();
    }

    public function down(): void
    {
        $this->table('instances_risks')
            ->dropForeignKey('risk_source_id')
            ->removeIndexByName('risk_source_id')
            ->removeColumn('risk_source_id')
            ->update();

        $this->table('risk_sources')->drop()->save();
    }
}
