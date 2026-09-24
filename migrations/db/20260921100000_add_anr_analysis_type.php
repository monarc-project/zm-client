<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class AddAnrAnalysisType extends AbstractMigration
{
    public function up(): void
    {
        $this->table('anrs')
            ->addColumn('analysis_type', 'string', [
                'limit' => 40,
                'null' => false,
                'default' => 'asset',
                'after' => 'uuid',
            ])
            ->addIndex(['analysis_type'])
            ->update();
    }

    public function down(): void
    {
        $this->table('anrs')
            ->removeIndex(['analysis_type'])
            ->removeColumn('analysis_type')
            ->update();
    }
}
