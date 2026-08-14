<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Service\ResidualRiskAcceptanceService;

class ApiAnrOperationalRiskResidualAcceptanceController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ResidualRiskAcceptanceService $residualRiskAcceptanceService,
        private AnrSupervisorService $anrSupervisorService
    ) {
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $riskId = (int)$this->params()->fromRoute('id');

        return $this->getSuccessfulJsonResponse($this->prepareRiskData(
            $this->residualRiskAcceptanceService->decideOperationalRisk($anr, $riskId, (array)$data)
        ));
    }

    private function prepareRiskData(InstanceRiskOp $risk): array
    {
        return [
            'id' => $risk->getId(),
            'residualRiskDecision' => $risk->getResidualRiskDecision(),
            'residualAcceptanceUseRiskOwner' => $risk->isResidualAcceptanceUseRiskOwner(),
            'residualAcceptanceApproverSupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                $risk->getResidualAcceptanceApproverSupervisor()
            ),
            'residualAcceptanceApproverSupervisorId' => $risk->getResidualAcceptanceApproverSupervisor()?->getId(),
            'residualAcceptancePerformedByName' => $risk->getResidualAcceptancePerformedByName(),
            'residualAcceptancePerformedByEmail' => $risk->getResidualAcceptancePerformedByEmail(),
            'residualAcceptancePerformedOnBehalf' => $risk->isResidualAcceptancePerformedOnBehalf(),
            'residualRiskDecidedBySupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                $risk->getResidualRiskDecidedBySupervisor()
            ),
            'residualRiskDecidedBySupervisorId' => $risk->getResidualRiskDecidedBySupervisor()?->getId(),
            'residualRiskDecidedByUserId' => $risk->getResidualRiskDecidedByUser()?->getId(),
            'residualRiskDecidedByName' => $risk->getResidualRiskDecidedBySupervisor()?->getName(),
            'residualRiskDecidedAt' => $risk->getResidualRiskDecidedAt()?->format('Y-m-d'),
            'residualRiskJustification' => $risk->getResidualRiskJustification(),
        ];
    }
}
