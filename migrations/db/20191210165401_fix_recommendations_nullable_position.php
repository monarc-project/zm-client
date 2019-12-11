<?php

use Phinx\Migration\AbstractMigration;

class FixRecommendationsNullablePosition extends AbstractMigration
{
    public function change()
    {
        $anrs = $this->query('SELECT `id` from `anrs`')->fetchAll();
        if (empty($anrs)) {
            return;
        }
        foreach ($anrs as $anr) {
            $stmtMaxPosition = $this->query(
                'SELECT MAX(`position`) AS max_position from `recommandations` where `anr_id` = ' . $anr['id'] . ' AND `position` IS NOT NULL'
            );
            $maxPositionResult = $stmtMaxPosition->fetchAll();
            $maxPosition = 0;
            if (!empty($maxPositionResult) && !empty($maxPositionResult[0]['max_position'])) {
                $maxPosition = $maxPositionResult[0]['max_position'];
            }
            $recommendationsToUpdate = $this->query(
                'SELECT `uuid` from `recommandations` where `anr_id` = ' . $anr['id'] . ' AND `position` IS NULL ORDER BY `created_at`'
            );
            $recommendationsToUpdateResult = $recommendationsToUpdate->fetchAll();
            if (empty($recommendationsToUpdateResult)) {
                continue;
            }
            foreach ($recommendationsToUpdateResult as $recommendation) {
                $this->execute(
                    'UPDATE `recommandations` SET `position` = ' . ++$maxPosition
                    . ' WHERE `uuid` = "' . $recommendation['uuid'] .  '" AND `anr_id` = ' . $anr['id']
                );
            }
        }
    }
}
