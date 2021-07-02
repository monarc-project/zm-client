<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;

class ApiAnrInstancesRisksOpController extends AbstractRestfulController
{
    private AnrInstanceRiskOpService $anrInstanceRiskOpService;

    public function __construct(AnrInstanceRiskOpService $anrInstanceRiskOpService)
    {
        $this->anrInstanceRiskOpService = $anrInstanceRiskOpService;
    }

    public function update($id, $data)
    {
        $risk = $this->anrInstanceRiskOpService->update($id, $data);
        unset($risk['anr'], $risk['instance'], $risk['object'], $risk['rolfRisk']);

        return new JsonModel($risk);
    }

    public function patch($id, $data)
    {
        $this->anrInstanceRiskOpService->updateScaleValue($id, $data);

        return new JsonModel(['status' => 'ok']);
    }
}
