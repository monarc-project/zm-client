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
            // 1. position to 0 for all not linked recommendations, or where importance is 0.
            $recommendationsToUpdate = $this->query(
                'SELECT r.`uuid` from `recommandations` r
                WHERE r.`anr_id` = ' . $anr['id'] . '
                  AND (
                    r.`uuid` NOT IN (
                        SELECT rr.`recommandation_id` FROM `recommandations_risks` rr WHERE rr.`anr_id` = ' . $anr['id'] . '
                    )
                    OR r.`importance` = 0
                  )
                  AND r.`position` > 0 OR r.`position` IS NULL'
            );
            foreach ($recommendationsToUpdate->fetchAll() as $recommendation) {
                $this->execute(
                    'UPDATE `recommandations` SET `position` = 0
                    WHERE `anr_id` = ' . $anr['id'] . ' AND `uuid` = "' . $recommendation['uuid'] .  '"'
                );
            }

            // Update positions of linked recommendations to be sure they are ordered well.
            $recommendationsToUpdate = $this->query(
                'SELECT r.`uuid` FROM `recommandations` r
                INNER JOIN `recommandations_risks` rr ON r.`uuid` = rr.`recommandation_id` AND r.`anr_id` = rr.`anr_id`
                WHERE r.`anr_id` = ' . $anr['id'] . '
                  AND r.`importance` > 0
                GROUP BY r.`uuid`
                ORDER BY r.`importance`, r.`code`'
            );
            $position = 1;
            foreach ($recommendationsToUpdate->fetchAll() as $itemNum => $recommendation) {
                if ($position !== $itemNum) {
                    $this->execute(
                        'UPDATE `recommandations` SET `position` = ' . $position++ .
                        ' WHERE `uuid` = "' . $recommendation['uuid'] . '" AND `anr_id` = ' . $anr['id']
                    );
                }
            }
        }

        $this->execute(
            'ALTER TABLE `recommandations` CHANGE `position` `position` INT(11) DEFAULT 0 NOT NULL,
                CHANGE `importance` `importance` TINYINT(4) DEFAULT 0 NOT NULL,
                ADD INDEX `recommendation_anr_position` (`anr_id`, `position`);
            '
        );
    }
}
