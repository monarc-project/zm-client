<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrHistory;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
use Monarc\FrontOffice\Entity\InstanceRisk;
use Monarc\FrontOffice\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Table\InstanceRiskTable;

class ResidualRiskAcceptanceService
{
    private User $connectedUser;

    public function __construct(
        private InstanceRiskTable $instanceRiskTable,
        private InstanceRiskOpTable $instanceRiskOpTable,
        private AnrSupervisorService $anrSupervisorService,
        private AnrHistoryService $anrHistoryService,
        ConnectedUserService $connectedUserService
    ) {
        /** @var User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
    }

    public function decideInformationRisk(Anr $anr, int $riskId, array $data): InstanceRisk
    {
        /** @var InstanceRisk $instanceRisk */
        $instanceRisk = $this->instanceRiskTable->findByIdAndAnr($riskId, $anr);
        $before = $this->captureHistoryState($instanceRisk);
        $supervisor = $this->requireApproverSupervisorForRisk($anr, $instanceRisk);

        $instanceRisk
            ->setResidualRiskDecision($this->normalizeDecision($data['decision'] ?? null))
            ->setResidualAcceptanceApproverSupervisor($supervisor)
            ->setResidualRiskDecidedBySupervisor($supervisor)
            ->setResidualRiskDecidedByUser($this->connectedUser)
            ->setResidualRiskDecidedAt(new DateTime())
            ->setResidualRiskJustification($this->normalizeNullableText($data['justification'] ?? null))
            ->setResidualAcceptancePerformedByName($this->getCurrentUserSnapshotName())
            ->setResidualAcceptancePerformedByEmail($this->connectedUser->getEmail())
            ->setResidualAcceptancePerformedOnBehalf(false)
            ->setUpdater($this->connectedUser->getEmail());

        $this->instanceRiskTable->save($instanceRisk);
        $this->recordHistoryChanges($anr, AnrHistory::INFORMATION_RISK, $instanceRisk->getId(), $before, $this->captureHistoryState($instanceRisk));

        return $instanceRisk;
    }

    public function decideOperationalRisk(Anr $anr, int $riskId, array $data): InstanceRiskOp
    {
        /** @var InstanceRiskOp $instanceRiskOp */
        $instanceRiskOp = $this->instanceRiskOpTable->findByIdAndAnr($riskId, $anr);
        $before = $this->captureHistoryState($instanceRiskOp);
        $supervisor = $this->requireApproverSupervisorForRisk($anr, $instanceRiskOp);

        $instanceRiskOp
            ->setResidualRiskDecision($this->normalizeDecision($data['decision'] ?? null))
            ->setResidualAcceptanceApproverSupervisor($supervisor)
            ->setResidualRiskDecidedBySupervisor($supervisor)
            ->setResidualRiskDecidedByUser($this->connectedUser)
            ->setResidualRiskDecidedAt(new DateTime())
            ->setResidualRiskJustification($this->normalizeNullableText($data['justification'] ?? null))
            ->setResidualAcceptancePerformedByName($this->getCurrentUserSnapshotName())
            ->setResidualAcceptancePerformedByEmail($this->connectedUser->getEmail())
            ->setResidualAcceptancePerformedOnBehalf(false)
            ->setUpdater($this->connectedUser->getEmail());

        $this->instanceRiskOpTable->save($instanceRiskOp);
        $this->recordHistoryChanges($anr, AnrHistory::OPERATIONAL_RISK, $instanceRiskOp->getId(), $before, $this->captureHistoryState($instanceRiskOp));

        return $instanceRiskOp;
    }

    private function requireApproverSupervisorForRisk(Anr $anr, InstanceRisk|InstanceRiskOp $risk)
    {
        $supervisor = $this->anrSupervisorService->findLinkedSupervisor($anr, $this->connectedUser);
        if ($supervisor === null
            || !$supervisor->hasRole(AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER)
            || !$supervisor->isActive()
        ) {
            throw new Exception('Only linked supervisors with residual risk approver role may perform this action.', 403);
        }

        $configuredApprover = $risk->isResidualAcceptanceUseRiskOwner()
            ? $risk->getRiskOwnerSupervisor()
            : $risk->getResidualAcceptanceApproverSupervisor();
        if ($configuredApprover === null || $configuredApprover->getId() !== $supervisor->getId()) {
            throw new Exception('Residual risk acceptance decision is not assigned to the current supervisor.', 403);
        }

        return $supervisor;
    }

    private function normalizeDecision(mixed $decision): string
    {
        $decision = trim((string)$decision);
        if (!in_array($decision, ['accepted', 'not_accepted'], true)) {
            throw new Exception('Residual risk decision must be "accepted" or "not_accepted".', 412);
        }

        return $decision;
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function getCurrentUserSnapshotName(): string
    {
        $fullName = trim(sprintf(
            '%s %s',
            (string)$this->connectedUser->getFirstname(),
            (string)$this->connectedUser->getLastname()
        ));

        return $fullName !== '' ? $fullName : (string)$this->connectedUser->getEmail();
    }

    private function captureHistoryState(InstanceRisk|InstanceRiskOp $risk): array
    {
        return [
            'approver' => $risk->getResidualAcceptanceApproverSupervisor()?->getName(),
            'decision' => $risk->getResidualRiskDecision(),
            'date' => $risk->getResidualRiskDecidedAt()?->format('Y-m-d'),
            'justification' => $risk->getResidualRiskJustification(),
        ];
    }

    private function recordHistoryChanges(Anr $anr, int $targetType, int $targetId, array $before, array $after): void
    {
        $fieldMap = [
            AnrHistory::RESIDUAL_ACCEPTANCE_APPROVER => 'approver',
            AnrHistory::RESIDUAL_ACCEPTANCE_DECISION => 'decision',
            AnrHistory::RESIDUAL_ACCEPTANCE_DATE => 'date',
            AnrHistory::RESIDUAL_ACCEPTANCE_JUSTIFICATION => 'justification',
        ];
        $entries = [];

        foreach ($fieldMap as $fieldCode => $stateKey) {
            if ($before[$stateKey] !== $after[$stateKey]) {
                $entries[] = [
                    'targetType' => $targetType,
                    'targetId' => $targetId,
                    'changeType' => AnrHistory::RESIDUAL_ACCEPTANCE_UPDATED,
                    'fieldCode' => $fieldCode,
                    'oldValue' => $before[$stateKey],
                    'newValue' => $after[$stateKey],
                ];
            }
        }

        $this->anrHistoryService->createEntries($anr, $entries);
    }
}
