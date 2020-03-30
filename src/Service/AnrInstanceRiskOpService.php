<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\InstanceRiskOpService;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskOpService extends InstanceRiskOpService
{
    use RecommendationsPositionsUpdateTrait;

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
        /** @var InstanceRiskOpTable $operationalRiskTable */
        $operationalRiskTable = $this->get('table');
        /** @var InstanceRiskOp $operationalRisk */
        $operationalRisk = $operationalRiskTable->findById($id);

        $operationalRiskTable->deleteEntity($operationalRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($operationalRisk);

        return true;
    }

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     */
    public function patch($id, $data)
    {
        // TODO: Pass the object instead of id.
        $result = parent::patch($id, $data);

        /** @var InstanceRiskOpTable $instanceRiskOpTable */
        $instanceRiskOpTable = $this->get('table');
        /** @var InstanceRiskOp $instanceRiskOp */
        $instanceRiskOp = $instanceRiskOpTable->findById($id);

        $this->updateInstanceRiskRecommendationsPositions($instanceRiskOp);

        return $result;
    }

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     */
    public function update($id, $data)
    {
        // TODO: pass the object instead of id.
        $result = parent::update($id, $data);

        /** @var InstanceRiskOpTable $instanceRiskOpTable */
        $instanceRiskOpTable = $this->get('table');
        /** @var InstanceRiskOp $instanceRiskOp */
        $instanceRiskOp = $instanceRiskOpTable->findById($id);

        $this->updateInstanceRiskRecommendationsPositions($instanceRiskOp);

        return $result;
    }
}
