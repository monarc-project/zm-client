<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class AddAnrSupervisors extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE IF NOT EXISTS `anr_supervisors` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `anr_id` int(11) unsigned NOT NULL,
                `name` varchar(255) NOT NULL,
                `email` varchar(255) DEFAULT NULL,
                `linked_user_id` int(11) unsigned DEFAULT NULL,
                `role_position` varchar(255) DEFAULT NULL,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `creator` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updater` varchar(255) DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_anr_supervisors_anr_id` (`anr_id`),
                KEY `idx_anr_supervisors_linked_user_id` (`linked_user_id`),
                UNIQUE `anr_supervisors_anr_id_name_email_unq` (`anr_id`, `name`, `email`),
                CONSTRAINT `fk_anr_supervisors_anr`
                    FOREIGN KEY (`anr_id`) REFERENCES `anrs` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT `fk_anr_supervisors_linked_user`
                    FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $this->execute(
            'CREATE TABLE IF NOT EXISTS `anr_supervisor_roles` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `anr_supervisor_id` int(11) unsigned NOT NULL,
                `role` varchar(100) NOT NULL,
                `creator` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updater` varchar(255) DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_anr_supervisor_roles_unique` (`anr_supervisor_id`, `role`),
                KEY `idx_supervisor_role_supervisor_id` (`anr_supervisor_id`),
                KEY `idx_supervisor_role_role` (`role`),
                CONSTRAINT `fk_anr_supervisor_roles_supervisor`
                    FOREIGN KEY (`anr_supervisor_id`) REFERENCES `anr_supervisors` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $this->table('instances_risks')
            ->addColumn('risk_owner_supervisor_id', 'integer', ['null' => true, 'signed' => false, 'after' => 'risk_owner_id'])
            ->addColumn(
                'residual_acceptance_use_risk_owner',
                'boolean',
                ['default' => false, 'after' => 'residual_risk_decision']
            )
            ->addColumn(
                'residual_acceptance_approver_supervisor_id',
                'integer',
                ['null' => true, 'signed' => false, 'after' => 'residual_acceptance_use_risk_owner']
            )
            ->addColumn(
                'residual_acceptance_performed_by_name',
                'string',
                ['limit' => 255, 'null' => true, 'after' => 'residual_acceptance_approver_supervisor_id']
            )
            ->addColumn(
                'residual_acceptance_performed_by_email',
                'string',
                ['limit' => 255, 'null' => true, 'after' => 'residual_acceptance_performed_by_name']
            )
            ->addColumn(
                'residual_acceptance_performed_on_behalf',
                'boolean',
                ['default' => false, 'after' => 'residual_acceptance_performed_by_email']
            )
            ->addColumn(
                'residual_risk_decided_by_supervisor_id',
                'integer',
                ['null' => true, 'signed' => false, 'after' => 'residual_acceptance_performed_on_behalf']
            )
            ->addColumn(
                'residual_risk_decided_by_user_id',
                'integer',
                ['null' => true, 'signed' => false, 'after' => 'residual_risk_decided_by_supervisor_id']
            )
            ->addColumn(
                'residual_risk_decided_at',
                'datetime',
                ['null' => true, 'after' => 'residual_risk_decided_by_user_id']
            )
            ->addIndex(['risk_owner_supervisor_id'], ['name' => 'risk_owner_supervisor_id'])
            ->addIndex(
                ['residual_acceptance_approver_supervisor_id'],
                ['name' => 'residual_acceptance_approver_supervisor_id']
            )
            ->addIndex(
                ['residual_risk_decided_by_supervisor_id'],
                ['name' => 'residual_risk_decided_by_supervisor_id']
            )
            ->addIndex(['residual_risk_decided_by_user_id'], ['name' => 'residual_risk_decided_by_user_id'])
            ->addForeignKey(
                'risk_owner_supervisor_id',
                'anr_supervisors',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'RESTRICT']
            )
            ->addForeignKey(
                'residual_acceptance_approver_supervisor_id',
                'anr_supervisors',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'RESTRICT']
            )
            ->addForeignKey(
                'residual_risk_decided_by_supervisor_id',
                'anr_supervisors',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'RESTRICT']
            )
            ->addForeignKey(
                'residual_risk_decided_by_user_id',
                'users',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'RESTRICT']
            )
            ->removeColumn('residual_risk_approved_at')
            ->removeColumn('residual_risk_approved_by')
            ->update();

        $this->table('instances_risks_op')
            ->addColumn('risk_owner_supervisor_id', 'integer', ['null' => true, 'signed' => false, 'after' => 'risk_owner_id'])
            ->addColumn(
                'residual_acceptance_use_risk_owner',
                'boolean',
                ['default' => false, 'after' => 'residual_risk_decision']
            )
            ->addColumn(
                'residual_acceptance_approver_supervisor_id',
                'integer',
                ['null' => true, 'signed' => false, 'after' => 'residual_acceptance_use_risk_owner']
            )
            ->addColumn(
                'residual_acceptance_performed_by_name',
                'string',
                ['limit' => 255, 'null' => true, 'after' => 'residual_acceptance_approver_supervisor_id']
            )
            ->addColumn(
                'residual_acceptance_performed_by_email',
                'string',
                ['limit' => 255, 'null' => true, 'after' => 'residual_acceptance_performed_by_name']
            )
            ->addColumn(
                'residual_acceptance_performed_on_behalf',
                'boolean',
                ['default' => false, 'after' => 'residual_acceptance_performed_by_email']
            )
            ->addColumn(
                'residual_risk_decided_by_supervisor_id',
                'integer',
                ['null' => true, 'signed' => false, 'after' => 'residual_acceptance_performed_on_behalf']
            )
            ->addColumn(
                'residual_risk_decided_by_user_id',
                'integer',
                ['null' => true, 'signed' => false, 'after' => 'residual_risk_decided_by_supervisor_id']
            )
            ->addColumn(
                'residual_risk_decided_at',
                'datetime',
                ['null' => true, 'after' => 'residual_risk_decided_by_user_id']
            )
            ->addIndex(['risk_owner_supervisor_id'], ['name' => 'op_risk_owner_supervisor_id'])
            ->addIndex(
                ['residual_acceptance_approver_supervisor_id'],
                ['name' => 'op_residual_acceptance_approver_supervisor_id']
            )
            ->addIndex(
                ['residual_risk_decided_by_supervisor_id'],
                ['name' => 'op_residual_risk_decided_by_supervisor_id']
            )
            ->addIndex(
                ['residual_risk_decided_by_user_id'],
                ['name' => 'op_residual_risk_decided_by_user_id']
            )
            ->addForeignKey(
                'risk_owner_supervisor_id',
                'anr_supervisors',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'RESTRICT']
            )
            ->addForeignKey(
                'residual_acceptance_approver_supervisor_id',
                'anr_supervisors',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'RESTRICT']
            )
            ->addForeignKey(
                'residual_risk_decided_by_supervisor_id',
                'anr_supervisors',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'RESTRICT']
            )
            ->addForeignKey(
                'residual_risk_decided_by_user_id',
                'users',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'RESTRICT']
            )
            ->removeColumn('residual_risk_approved_at')
            ->removeColumn('residual_risk_approved_by')
            ->update();

        $this->execute(
            'INSERT INTO anr_supervisors (anr_id, name, email, linked_user_id, is_active, creator, created_at)
            SELECT iro.anr_id, iro.name, NULL, NULL, 1, iro.creator, COALESCE(iro.created_at, NOW())
            FROM instance_risk_owners iro
            LEFT JOIN anr_supervisors s
                ON s.anr_id = iro.anr_id
                AND LOWER(TRIM(s.name)) = LOWER(TRIM(iro.name))
            WHERE s.id IS NULL;'
        );

        $this->execute(
            "INSERT INTO anr_supervisor_roles (anr_supervisor_id, role, creator, created_at)
            SELECT s.id, 'risk_owner', COALESCE(s.creator, 'System'), COALESCE(s.created_at, NOW())
            FROM anr_supervisors s
            LEFT JOIN anr_supervisor_roles sr
                ON sr.anr_supervisor_id = s.id
                AND sr.role = 'risk_owner'
            WHERE sr.id IS NULL;"
        );

        $this->execute(
            'UPDATE instances_risks ir
            INNER JOIN instance_risk_owners iro ON iro.id = ir.risk_owner_id
            INNER JOIN anr_supervisors s
                ON s.anr_id = iro.anr_id
                AND LOWER(TRIM(s.name)) = LOWER(TRIM(iro.name))
            SET ir.risk_owner_supervisor_id = s.id
            WHERE ir.risk_owner_id IS NOT NULL;'
        );

        $this->execute(
            'UPDATE instances_risks_op iropr
            INNER JOIN instance_risk_owners iro ON iro.id = iropr.risk_owner_id
            INNER JOIN anr_supervisors s
                ON s.anr_id = iro.anr_id
                AND LOWER(TRIM(s.name)) = LOWER(TRIM(iro.name))
            SET iropr.risk_owner_supervisor_id = s.id
            WHERE iropr.risk_owner_id IS NOT NULL;'
        );
    }

    public function down(): void
    {
        $this->table('instances_risks')
            ->dropForeignKey('residual_risk_decided_by_user_id')
            ->dropForeignKey('residual_risk_decided_by_supervisor_id')
            ->dropForeignKey('residual_acceptance_approver_supervisor_id')
            ->dropForeignKey('risk_owner_supervisor_id')
            ->removeIndexByName('residual_risk_decided_by_user_id')
            ->removeIndexByName('residual_risk_decided_by_supervisor_id')
            ->removeIndexByName('residual_acceptance_approver_supervisor_id')
            ->removeIndexByName('risk_owner_supervisor_id')
            ->addColumn('residual_risk_approved_by', 'string', ['limit' => 255, 'null' => true, 'after' => 'residual_risk_decision'])
            ->addColumn('residual_risk_approved_at', 'date', ['null' => true, 'after' => 'residual_risk_approved_by'])
            ->removeColumn('residual_risk_decided_at')
            ->removeColumn('residual_risk_decided_by_user_id')
            ->removeColumn('residual_risk_decided_by_supervisor_id')
            ->removeColumn('residual_acceptance_performed_on_behalf')
            ->removeColumn('residual_acceptance_performed_by_email')
            ->removeColumn('residual_acceptance_performed_by_name')
            ->removeColumn('residual_acceptance_approver_supervisor_id')
            ->removeColumn('residual_acceptance_use_risk_owner')
            ->removeColumn('risk_owner_supervisor_id')
            ->update();

        $this->table('instances_risks_op')
            ->dropForeignKey('residual_risk_decided_by_user_id')
            ->dropForeignKey('residual_risk_decided_by_supervisor_id')
            ->dropForeignKey('residual_acceptance_approver_supervisor_id')
            ->dropForeignKey('risk_owner_supervisor_id')
            ->removeIndexByName('op_residual_risk_decided_by_user_id')
            ->removeIndexByName('op_residual_risk_decided_by_supervisor_id')
            ->removeIndexByName('op_residual_acceptance_approver_supervisor_id')
            ->removeIndexByName('op_risk_owner_supervisor_id')
            ->addColumn('residual_risk_approved_by', 'string', ['limit' => 255, 'null' => true, 'after' => 'residual_risk_decision'])
            ->addColumn('residual_risk_approved_at', 'date', ['null' => true, 'after' => 'residual_risk_approved_by'])
            ->removeColumn('residual_risk_decided_at')
            ->removeColumn('residual_risk_decided_by_user_id')
            ->removeColumn('residual_risk_decided_by_supervisor_id')
            ->removeColumn('residual_acceptance_performed_on_behalf')
            ->removeColumn('residual_acceptance_performed_by_email')
            ->removeColumn('residual_acceptance_performed_by_name')
            ->removeColumn('residual_acceptance_approver_supervisor_id')
            ->removeColumn('residual_acceptance_use_risk_owner')
            ->removeColumn('risk_owner_supervisor_id')
            ->update();

        $this->table('anr_supervisor_roles')->drop()->save();
        $this->table('anr_supervisors')->drop()->save();
    }
}
