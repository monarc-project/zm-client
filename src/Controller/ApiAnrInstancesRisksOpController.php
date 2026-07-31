<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\InstanceRiskOp\PatchInstanceRiskOpDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRiskOp\PatchDelegatedInstanceRiskOpDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRiskOp\PostSpecificInstanceRiskOpDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\InstanceRiskOp\UpdateInstanceRiskOpDataInputValidator;

class ApiAnrInstancesRisksOpController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        private AnrSupervisorService $anrSupervisorService,
        private PostSpecificInstanceRiskOpDataInputValidator $postSpecificInstanceRiskOpDataInputValidator,
        private UpdateInstanceRiskOpDataInputValidator $updateInstanceRiskOpDataInputValidator,
        private PatchInstanceRiskOpDataInputValidator $patchInstanceRiskOpDataInputValidator,
        private PatchDelegatedInstanceRiskOpDataInputValidator $patchDelegatedInstanceRiskOpDataInputValidator
    ) {
    }

    public function create($data)
    {
        $this->validatePostParams($this->postSpecificInstanceRiskOpDataInputValidator, $data);

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $operationalInstanceRisk = $this->anrInstanceRiskOpService->createSpecificOperationalInstanceRisk(
            $anr,
            $this->postSpecificInstanceRiskOpDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse(['id' => $operationalInstanceRisk->getId()]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        $this->validatePostParams($this->updateInstanceRiskOpDataInputValidator, $data);
        $validatedData = $this->filterValidatedData($data, $this->updateInstanceRiskOpDataInputValidator->getValidData());

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $instanceRiskOp = $this->anrInstanceRiskOpService->update(
            $anr,
            (int)$id,
            $validatedData
        );

        return $this->getPreparedJsonResponse($this->prepareInstanceRiskOpResponse($instanceRiskOp));
    }

    /**
     * @param array $data
     */
    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        if (array_key_exists('instanceRiskScaleId', $data)) {
            $this->validatePostParams($this->patchInstanceRiskOpDataInputValidator, $data);
            $instanceRiskOp = $this->anrInstanceRiskOpService->updateScaleValue(
                $anr,
                (int)$id,
                $this->patchInstanceRiskOpDataInputValidator->getValidData()
            );

            return $this->getPreparedJsonResponse([
                'cacheBrutRisk' => $instanceRiskOp->getCacheBrutRisk(),
                'cacheNetRisk' => $instanceRiskOp->getCacheNetRisk(),
                'cacheTargetedRisk' => $instanceRiskOp->getCacheTargetedRisk(),
            ]);
        }

        $this->validatePostParams($this->patchDelegatedInstanceRiskOpDataInputValidator, $data);
        $validatedData = $this->filterValidatedData($data, $this->patchDelegatedInstanceRiskOpDataInputValidator->getValidData());

        $instanceRiskOp = $this->anrInstanceRiskOpService->update(
            $anr,
            (int)$id,
            $validatedData
        );

        return $this->getPreparedJsonResponse($this->prepareInstanceRiskOpResponse($instanceRiskOp));
    }

    private function prepareInstanceRiskOpResponse($instanceRiskOp): array
    {
        return [
            'cacheBrutRisk' => $instanceRiskOp->getCacheBrutRisk(),
            'cacheNetRisk' => $instanceRiskOp->getCacheNetRisk(),
            'cacheTargetedRisk' => $instanceRiskOp->getCacheTargetedRisk(),
            'riskSourceId' => $instanceRiskOp->getRiskSource()?->getId(),
            'riskSourceLabel' => $instanceRiskOp->getRiskSource()?->getLabel() ?? '',
            'owner' => $instanceRiskOp->getRiskOwnerSupervisor()?->getName() ?? '',
            'riskOwnerSupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                $instanceRiskOp->getRiskOwnerSupervisor()
            ),
            'riskOwnerSupervisorId' => $instanceRiskOp->getRiskOwnerSupervisor()?->getId(),
            'riskOwnerSupervisorName' => $instanceRiskOp->getRiskOwnerSupervisor()?->getName(),
            'lastReviewDate' => $instanceRiskOp->getLastReviewDate()?->format('Y-m-d'),
            'nextReassessmentDate' => $instanceRiskOp->getNextReassessmentDate()?->format('Y-m-d'),
            'reassessmentTriggers' => array_map(static fn ($trigger): array => [
                'id' => $trigger->getId(),
                'triggerType' => $trigger->getTriggerType(),
                'description' => $trigger->getDescription(),
            ], $instanceRiskOp->getReassessmentTriggers()->toArray()),
            'reviewFrequency' => $instanceRiskOp->getReviewFrequency(),
            'residualRiskDecision' => $instanceRiskOp->getResidualRiskDecision(),
            'residualAcceptanceUseRiskOwner' => $instanceRiskOp->isResidualAcceptanceUseRiskOwner(),
            'residualAcceptanceApproverSupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                $instanceRiskOp->getResidualAcceptanceApproverSupervisor()
            ),
            'residualAcceptanceApproverSupervisorId' => $instanceRiskOp->getResidualAcceptanceApproverSupervisor()?->getId(),
            'residualAcceptancePerformedByName' => $instanceRiskOp->getResidualAcceptancePerformedByName(),
            'residualAcceptancePerformedByEmail' => $instanceRiskOp->getResidualAcceptancePerformedByEmail(),
            'residualAcceptancePerformedOnBehalf' => $instanceRiskOp->isResidualAcceptancePerformedOnBehalf(),
            'residualRiskDecidedBySupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                $instanceRiskOp->getResidualRiskDecidedBySupervisor()
            ),
            'residualRiskDecidedBySupervisorId' => $instanceRiskOp->getResidualRiskDecidedBySupervisor()?->getId(),
            'residualRiskDecidedByUserId' => $instanceRiskOp->getResidualRiskDecidedByUser()?->getId(),
            'residualRiskDecidedByName' => $instanceRiskOp->getResidualRiskDecidedBySupervisor()?->getName(),
            'residualRiskDecidedAt' => $instanceRiskOp->getResidualRiskDecidedAt()?->format('Y-m-d'),
            'residualRiskJustification' => $instanceRiskOp->getResidualRiskJustification(),
        ];
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrInstanceRiskOpService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
    
    private function filterValidatedData(array $sourceData, array $validatedData): array
    {
        return array_intersect_key($validatedData, $sourceData);
    }
}
