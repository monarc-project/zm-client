<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class AddInterestedParties extends AbstractMigration
{
    public function up(): void
    {
        $this->table('anr_interested_parties')
            ->addColumn('anr_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('stakeholder', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('requirement', 'text', ['null' => false])
            ->addColumn('position', 'integer', ['default' => 0, 'signed' => false])
            ->addColumn('creator', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updater', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['anr_id'], ['name' => 'idx_anr_id'])
            ->addIndex(['anr_id', 'position'], ['name' => 'anr_interested_parties_anr_id_position_indx'])
            ->addForeignKey('anr_id', 'anrs', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->create();
    }

    public function down(): void
    {
        $this->table('anr_interested_parties')->drop()->save();
    }
}
