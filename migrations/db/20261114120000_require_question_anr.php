<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- Phinx discovers global migration classes.
class RequireQuestionAnr extends AbstractMigration
{
    public function up(): void
    {
        foreach (['questions', 'questions_choices'] as $table) {
            $this->table($table)
                ->changeColumn('anr_id', 'integer', ['null' => false, 'signed' => false])
                ->update();
        }
    }

    public function down(): void
    {
        foreach (['questions', 'questions_choices'] as $table) {
            $this->table($table)
                ->changeColumn('anr_id', 'integer', ['null' => true, 'signed' => false])
                ->update();
        }
    }
}
