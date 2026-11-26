<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- Phinx discovers global migration classes.
class RequireInterviewAnr extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('DELETE FROM interviews WHERE anr_id IS NULL');
        $this->table('interviews')
            ->changeColumn('anr_id', 'integer', ['null' => false, 'signed' => false])
            ->update();
    }

    public function down(): void
    {
        $this->table('interviews')
            ->changeColumn('anr_id', 'integer', ['null' => true, 'signed' => false])
            ->update();
    }
}
