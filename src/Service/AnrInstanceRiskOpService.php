<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\DBAL\Connection;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Service\InstanceRiskOpService;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Table\RecommandationTable;

class AnrInstanceRiskOpService extends InstanceRiskOpService
{
    /**
     * Updates recommendation operational risks positions.
     */
    public function updateRecoRisksOp(InstanceRiskOpSuperClass $instanceRiskOp): void
    {
        if ($instanceRiskOp->isTreated()) {
            $ids = [];
            /** @var InstanceRiskOp $instanceRiskOp */
            foreach ($instanceRiskOp->getRecommendationRisks() as $recommendationRisk) {
                $ids[$recommendationRisk->getId()] = true;
            }

            /** @var RecommandationTable $recommendationTable */
            $recommendationTable = $this->get('recommandationTable');
            /** @var Recommandation[] $recommendations */
            $recommendations = $recommendationTable->getEntityByFields(
                ['anr' => $instanceRiskOp->getAnr()->getId()],
                ['position' => 'ASC', 'importance' => 'DESC', 'code' => 'ASC']
            );

            $i = 0;
            $hasSave = false;
            foreach ($recommendations as $recommendation) {
                if ($recommendation->getPosition() <= 0 && isset($ids[(string)$recommendation->getUuid()])) {
                    $recommendation->setPosition(++$i);
                    $recommendationTable->saveEntity($recommendation, false);
                    $hasSave = true;
                } elseif ($i > 0 && $recommendation->getPosition() > 0) {
                    $recommendation->setPosition($recommendation->getPosition() + $i);
                    $recommendationTable->saveEntity($recommendation, false);
                    $hasSave = true;
                }
            }
            if ($hasSave) {
                $recommendationTable->getDb()->flush();
            }
        }
    }
}
