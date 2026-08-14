<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Migration\AbstractMigration;

class AddAnrReassessmentReviewMetadata extends AbstractMigration
{
    public function up(): void
    {
        $this->table('anrs')
            ->addColumn('reassessment_last_review_date', 'date', ['null' => true, 'after' => 'is_stats_collected'])
            ->addColumn('reassessment_review_frequency', 'string', [
                'limit' => 50,
                'null' => false,
                'default' => 'Annually',
                'after' => 'reassessment_last_review_date',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('anrs')
            ->removeColumn('reassessment_review_frequency')
            ->removeColumn('reassessment_last_review_date')
            ->update();
    }
}
