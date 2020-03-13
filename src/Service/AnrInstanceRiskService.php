<?php

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\InstanceRiskService;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Service\Traits\InstanceRiskRecommendationUpdateTrait;

class AnrInstanceRiskService extends InstanceRiskService
{
    use InstanceRiskRecommendationUpdateTrait;

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
