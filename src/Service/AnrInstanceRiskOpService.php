<?php

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\InstanceRiskOpService;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskOpService extends InstanceRiskOpService
{
    use RecommendationsPositionsUpdateTrait;

    /**
     * @throws EntityNotFoundException
     */
    public function delete($id)
    {
        $this->updateRecommendationsPositions($id);

        return parent::delete($id);
    }

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     */
    public function patch($id, $data)
    {
        // TODO: Pass the object instead of id.
        $result = parent::patch($id, $data);

        $this->updateRecommendationsPositions($id);

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

        $this->updateRecommendationsPositions($id);

        return $result;
    }

    /**
     * @throws EntityNotFoundException
     */
    private function updateRecommendationsPositions($id): void
    {
        /** @var InstanceRiskOpTable $instanceRiskOpTable */
        $instanceRiskOpTable = $this->get('table');
        /** @var InstanceRiskOp $instanceRiskOp */
        $instanceRiskOp = $instanceRiskOpTable->findById($id);

        $this->updateInstanceRiskRecommendationsPositions($instanceRiskOp);
    }
}
