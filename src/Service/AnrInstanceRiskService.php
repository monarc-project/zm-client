<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskOwnerSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Service\InstanceRiskService;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskService extends InstanceRiskService
{
    use RecommendationsPositionsUpdateTrait;

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

    /**
     * @param int $id
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete($id)
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        /** @var InstanceRisk $instanceRisk */
        $instanceRisk = $instanceRiskTable->findById($id);

        $instanceRiskTable->deleteEntity($instanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($instanceRisk);

        return true;
    }

    public function updateRisks(InstanceRiskSuperClass $instanceRisk, bool $last = true): void
    {
        parent::updateRisks($instanceRisk, $last);

        $this->updateInstanceRiskRecommendationsPositions($instanceRisk);
    }

    protected function duplicateRecommendationRisk(
        InstanceRiskSuperClass $instanceRisk,
        InstanceRiskSuperClass $newInstanceRisk
    ): void {
        /** @var RecommandationRiskTable $recommandationRiskTable */
        $recommandationRiskTable = $this->get('recommandationRiskTable');
        $recommendationRisks = $recommandationRiskTable->findByAnrAndInstanceRisk(
            $newInstanceRisk->getAnr(),
            $instanceRisk
        );
        foreach ($recommendationRisks as $recommandationRisk) {
            $newRecommendationRisk = (clone $recommandationRisk)
                ->setId(null)
                ->setInstance($newInstanceRisk->getInstance())
                ->setInstanceRisk($newInstanceRisk);

            $recommandationRiskTable->saveEntity($newRecommendationRisk, false);
        }
    }

    protected function createInstanceRiskOwnerObject(AnrSuperClass $anr, string $ownerName): InstanceRiskOwnerSuperClass
    {
        return (new InstanceRiskOwner())
            ->setAnr($anr)
            ->setName($ownerName)
            ->setCreator($this->getConnectedUser()->getEmail());
    }
}
