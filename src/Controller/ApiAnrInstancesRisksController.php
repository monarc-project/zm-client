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
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRisk\PatchDelegatedInstanceRiskDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRisk\PostSpecificInstanceRiskDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRisk\UpdateInstanceRiskDataInputValidator;

class ApiAnrInstancesRisksController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrInstanceRiskService $anrInstanceRiskService,
        private AnrSupervisorService $anrSupervisorService,
        private PostSpecificInstanceRiskDataInputValidator $postSpecificInstanceRiskDataInputValidator,
        private UpdateInstanceRiskDataInputValidator $updateInstanceRiskDataInputValidator,
        private PatchDelegatedInstanceRiskDataInputValidator $patchDelegatedInstanceRiskDataInputValidator
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
        $validatedData = $this->filterValidatedData($data, $this->updateInstanceRiskDataInputValidator->getValidData());

        /** @var array $data */
        $instanceRisk = $this->anrInstanceRiskService
            ->update($anr, (int)$id, $validatedData);

        return $this->getPreparedJsonResponse($this->prepareInstanceRiskResponse($instanceRisk));
    }

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        /** @var array $data */
        $this->validatePostParams($this->patchDelegatedInstanceRiskDataInputValidator, $data);
        $validatedData = $this->filterValidatedData($data, $this->patchDelegatedInstanceRiskDataInputValidator->getValidData());

        $instanceRisk = $this->anrInstanceRiskService->update($anr, (int)$id, $validatedData);

        return $this->getPreparedJsonResponse($this->prepareInstanceRiskResponse($instanceRisk));
    }

    private function prepareInstanceRiskResponse($instanceRisk): array
    {
        return [
            'id' => $instanceRisk->getId(),
            'riskSourceId' => $instanceRisk->getRiskSource()?->getId(),
            'riskSourceLabel' => $instanceRisk->getRiskSource()?->getLabel() ?? '',
            'owner' => $instanceRisk->getRiskOwnerSupervisor()?->getName() ?? '',
            'riskOwnerSupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                $instanceRisk->getRiskOwnerSupervisor()
            ),
            'riskOwnerSupervisorId' => $instanceRisk->getRiskOwnerSupervisor()?->getId(),
            'riskOwnerSupervisorName' => $instanceRisk->getRiskOwnerSupervisor()?->getName(),
            'lastReviewDate' => $instanceRisk->getLastReviewDate()?->format('Y-m-d'),
            'nextReassessmentDate' => $instanceRisk->getNextReassessmentDate()?->format('Y-m-d'),
            'reassessmentTriggers' => array_map(static fn ($trigger): array => [
                'id' => $trigger->getId(),
                'triggerType' => $trigger->getTriggerType(),
                'description' => $trigger->getDescription(),
            ], $instanceRisk->getReassessmentTriggers()->toArray()),
            'reviewFrequency' => $instanceRisk->getReviewFrequency(),
            'residualRiskDecision' => $instanceRisk->getResidualRiskDecision(),
            'residualAcceptanceUseRiskOwner' => $instanceRisk->isResidualAcceptanceUseRiskOwner(),
            'residualAcceptanceApproverSupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                $instanceRisk->getResidualAcceptanceApproverSupervisor()
            ),
            'residualAcceptanceApproverSupervisorId' => $instanceRisk->getResidualAcceptanceApproverSupervisor()?->getId(),
            'residualAcceptancePerformedByName' => $instanceRisk->getResidualAcceptancePerformedByName(),
            'residualAcceptancePerformedByEmail' => $instanceRisk->getResidualAcceptancePerformedByEmail(),
            'residualAcceptancePerformedOnBehalf' => $instanceRisk->isResidualAcceptancePerformedOnBehalf(),
            'residualRiskDecidedBySupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                $instanceRisk->getResidualRiskDecidedBySupervisor()
            ),
            'residualRiskDecidedBySupervisorId' => $instanceRisk->getResidualRiskDecidedBySupervisor()?->getId(),
            'residualRiskDecidedByUserId' => $instanceRisk->getResidualRiskDecidedByUser()?->getId(),
            'residualRiskDecidedByName' => $instanceRisk->getResidualRiskDecidedBySupervisor()?->getName(),
            'residualRiskDecidedAt' => $instanceRisk->getResidualRiskDecidedAt()?->format('Y-m-d'),
            'residualRiskJustification' => $instanceRisk->getResidualRiskJustification(),
            'threatRate' => $instanceRisk->getThreatRate(),
            'vulnerabilityRate' => $instanceRisk->getVulnerabilityRate(),
            'reductionAmount' => $instanceRisk->getReductionAmount(),
            'riskConfidentiality' => $instanceRisk->getRiskConfidentiality(),
            'riskIntegrity' => $instanceRisk->getRiskIntegrity(),
            'riskAvailability' => $instanceRisk->getRiskAvailability(),
            'cacheMaxRisk' => $instanceRisk->getCacheMaxRisk(),
            'cacheTargetedRisk' => $instanceRisk->getCacheTargetedRisk(),
        ];
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrInstanceRiskService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
    
    private function filterValidatedData(array $sourceData, array $validatedData): array
    {
        return array_intersect_key($validatedData, $sourceData);
    }
}
