<?php

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Service\InstanceRiskService;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;

class AnrInstanceRiskService extends InstanceRiskService
{
    /**
     * TODO: Check how we can call the method only once instead of multi calls during the instance risk update.
     *
     * Update recommendation risk positions.
     */
    public function updateRecoRisks(InstanceRiskSuperClass $instanceRisk): void
    {
        if ($instanceRisk->isTreated()) {
            $ids = [];
            /** @var InstanceRisk $instanceRisk */
            foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $ids[$recommendationRisk->getId()] = true;
            }

            /** @var RecommandationTable $recommendationTable */
            $recommendationTable = $this->get('recommandationTable');
            /** @var Recommandation[] $recommendations */
            $recommendations = $recommendationTable->getEntityByFields(
                ['anr' => $instanceRisk->getAnr()->getId()],
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

    public function updateFromRiskTable(int $instanceRiskId, array $data)
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        $instanceRisk = $instanceRiskTable->getEntity($instanceRiskId);

        //security
        $data['specific'] = $instanceRisk->get('specific');

        if ($instanceRisk->threatRate != $data['threatRate']) {
            $data['mh'] = 0;
        }

        return $this->update($instanceRiskId, $data);
    }
}
