<?php

use Phinx\Migration\AbstractMigration;

class FixRecommendationsPosition extends AbstractMigration
{
    public function change()
    {
        $anrs = $this->query('SELECT `id` from `anrs`')->fetchAll();
        if (empty($anrs)) {
            return;
        }
        foreach ($anrs as $anr) {
            // Add the relation to linked recommendation.
            $stmtMaxPosition = $this->query(
                'SELECT MAX(`position`) AS max_position from `recommandations` where `anr_id` = ' . $anr['id'] . ' AND `position` IS NOT NULL'
            );
            $maxPositionResult = $stmtMaxPosition->fetchAll();
            $maxPosition = 0;
            if (!empty($maxPositionResult) && !empty($maxPositionResult[0]['max_position'])) {
                $maxPosition = $maxPositionResult[0]['max_position'];
            }

            // Update positions of linked recommendations which have 0 or nullable positions' values.
            $recommendationsToUpdate = $this->query(
                'SELECT r.`uuid` from `recommandations` r
                INNER JOIN `recommandations_risks` rr ON r.`uuid` = rr.`recommandation_id` AND r.`anr_id` = rr.`anr_id`
                WHERE r.`anr_id` = ' . $anr['id'] . ' AND (r.`position` IS NULL OR r.`position` < 1)
                GROUP BY r.`uuid`
                ORDER BY r.`created_at`'
            );
            $recommendationsToUpdateResult = $recommendationsToUpdate->fetchAll();
            foreach ($recommendationsToUpdateResult as $recommendation) {
                $this->execute(
                    'UPDATE `recommandations` SET `position` = ' . ++$maxPosition .
                    ' WHERE `uuid` = "' . $recommendation['uuid'] . '" AND `anr_id` = ' . $anr['id']
                );
            }

            // Set position to 0 for all not linked recommendations.
            $recommendationsToUpdate = $this->query(
                'SELECT r.`uuid` from `recommandations` r
                WHERE r.`anr_id` = ' . $anr['id'] . '
                  AND r.uuid NOT IN (SELECT rr.`recommandation_id` FROM `recommandations_risks` rr WHERE rr.`anr_id` = ' . $anr['id'] . ')'
            );
            $recommendationsToUpdateResult = $recommendationsToUpdate->fetchAll();
            foreach ($recommendationsToUpdateResult as $recommendation) {
                $this->execute(
                    'UPDATE `recommandations` SET `position` = 0
                    WHERE `uuid` = "' . $recommendation['uuid'] .  '" AND `anr_id` = ' . $anr['id']
                );
            }
        }

        $this->execute(
            'ALTER TABLE `recommandations` CHANGE `position` `position` INT(11) DEFAULT 0 NOT NULL,
                CHANGE `importance` `importance` TINYINT(4) DEFAULT 0 NOT NULL;
            '
        );
    }
}