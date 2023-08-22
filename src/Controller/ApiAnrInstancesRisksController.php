<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Model\Entity\Anr;
use Monarc\Core\Service\InstanceRiskService;

class ApiAnrInstancesRisksController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private InstanceRiskService $instanceRiskService;

    public function __construct(InstanceRiskService $instanceRiskService)
    {
        $this->instanceRiskService = $instanceRiskService;
    }

    // TODO: implement create and delete.
    public function create($data)
    {
        //todo creation of a spec risk
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        // TODO: update the method to accept the anr param and remove IR.
        $instanceRisk = $this->instanceRiskService->updateFromRiskTable($anr, (int)$id, $data);

        return $this->getPreparedJsonResponse([
            'id' => $instanceRisk->getId(),
            'threatRate' => $instanceRisk->getThreatRate(),
            'vulnerabilityRate' => $instanceRisk->getVulnerabilityRate(),
            'reductionAmount' => $instanceRisk->getReductionAmount(),
            'riskConfidentiality' => $instanceRisk->getRiskConfidentiality(),
            'riskIntegrity' => $instanceRisk->getRiskIntegrity(),
            'riskAvailability' => $instanceRisk->getRiskAvailability(),
        ]);
    }

    public function delete($id)
    {
        // todo removal of spec risk.
    }
}
