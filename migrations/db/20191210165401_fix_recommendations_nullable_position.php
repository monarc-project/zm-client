<?php

use Phinx\Migration\AbstractMigration;

class FixRecommendationsNullablePosition extends AbstractMigration
{
    public function change()
    {
        $anrs = $this->query('SELECT `id` from `anrs`')->fetchAll();
        foreach ($anrs as $anr) {
            $stmtMaxPosition = $this->query('SELECT MAX(`position`) AS max_position from `recommandations` where `anr_id` = :anrId AND `position` IS NOT NULL');
            $stmtMaxPosition->bindParam(':anrId', $anr['id'], PDO::PARAM_INT);
            $maxPositionResult = $stmtMaxPosition->execute();
            $maxPosition = 0;
            if (!empty($maxPositionResult) && !empty($maxPositionResult[0]['max_position'])) {
                $maxPosition = $maxPositionResult[0]['max_position'];
            }
            $recommendationsToUpdate = $this->query('SELECT `id` from `recommandations` where `anr_id` = :anrId AND `position` IS NULL ORDER BY `created_at`');
            $recommendationsToUpdate->bindParam(':anrId', $anr['id'], PDO::PARAM_INT);
            $recommendationsToUpdateResult = $recommendationsToUpdate->execute();
            foreach ($recommendationsToUpdateResult as $recommendation) {
                $updateStmt = $this->query('UPDATE `recommandations` SET position = :position WHERE uuid = :uuid AND anr_id = :anId');
                $updateStmt->bindParam(':uuid', $recommendation['uuid'], PDO::PARAM_INT);
                $updateStmt->bindParam(':anrId', $anr['id'], PDO::PARAM_INT);
                $updateStmt->bindParam(':position', ++$maxPosition, PDO::PARAM_INT);
                $updateStmt->execute();
            }
        }
    }
}
