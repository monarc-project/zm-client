<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRisk\PostSpecificInstanceRiskDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRisk\UpdateInstanceRiskDataInputValidator;

class ApiAnrInstancesRisksController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrInstanceRiskService $anrInstanceRiskService,
        private PostSpecificInstanceRiskDataInputValidator $postSpecificInstanceRiskDataInputValidator,
        private UpdateInstanceRiskDataInputValidator $updateInstanceRiskDataInputValidator
    ) {
    }

    /**
     * Creation of specific risks.
     *
     * @param array $data
     */
    public function create($data)
    {
        $this->validatePostParams($this->postSpecificInstanceRiskDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $instanceRisk = $this->anrInstanceRiskService->createSpecificInstanceRisk(
            $anr,
            $this->postSpecificInstanceRiskDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse(['id' => $instanceRisk->getId()]);
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        /** @var array $data */
        $this->validatePostParams($this->updateInstanceRiskDataInputValidator, $data);

        /** @var array $data */
        $instanceRisk = $this->anrInstanceRiskService
            ->update($anr, (int)$id, $this->updateInstanceRiskDataInputValidator->getValidData());

        return $this->getPreparedJsonResponse([
            'id' => $instanceRisk->getId(),
            'threatRate' => $instanceRisk->getThreatRate(),
            'vulnerabilityRate' => $instanceRisk->getVulnerabilityRate(),
            'reductionAmount' => $instanceRisk->getReductionAmount(),
            'riskConfidentiality' => $instanceRisk->getRiskConfidentiality(),
            'riskIntegrity' => $instanceRisk->getRiskIntegrity(),
            'riskAvailability' => $instanceRisk->getRiskAvailability(),
            'cacheMaxRisk' => $instanceRisk->getCacheMaxRisk(),
            'cacheTargetedRisk' => $instanceRisk->getCacheTargetedRisk(),
        ]);
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrInstanceRiskService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
