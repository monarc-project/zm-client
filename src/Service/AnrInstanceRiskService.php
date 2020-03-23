<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Service\InstanceRiskService;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
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
     * @param $id
     *
     * @return bool
     * @throws EntityNotFoundException
     */
    public function delete($id)
    {
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        /** @var InstanceRisk $instanceRisk */
        $instanceRisk = $instanceRiskTable->findById($id);

        $this->updateInstanceRiskRecommendationsPositions($instanceRisk);

        return parent::delete($id);
    }

    /**
     * @param InstanceRisk|InstanceRiskSuperClass|int $instanceRisk
     * @param bool $last
     *
     * @throws EntityNotFoundException
     */
    public function updateRisks($instanceRisk, $last = true)
    {
        // TODO: Always pass the object. Will be improved in https://github.com/monarc-project/MonarcAppFO/issues/248
        if (!$instanceRisk instanceof InstanceRiskSuperClass) {
            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('table');
            $instanceRisk = $instanceRiskTable->findById($instanceRisk);
        }

        parent::updateRisks($instanceRisk, $last);

        $this->updateInstanceRiskRecommendationsPositions($instanceRisk);
    }
}
