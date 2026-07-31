<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrSupervisor;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
use Monarc\FrontOffice\Entity\User;

class AnrRisksManagementService
{
    private User $connectedUser;

    public function __construct(
        private AnrSupervisorService $anrSupervisorService,
        private AnrInstanceRiskService $anrInstanceRiskService,
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        private ReassessmentTriggerService $reassessmentTriggerService,
        ConnectedUserService $connectedUserService
    ) {
        /** @var User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
    }

    public function getViewData(Anr $anr): array
    {
        $supervisor = $this->requireEligibleSupervisor($anr);
        $roles = $this->buildRoles($supervisor);
        $risks = $this->getRiskRows($anr, $supervisor, $roles);

        return [
            'supervisor' => $this->anrSupervisorService->prepareSupervisorReference($supervisor),
            'roles' => $roles,
            'count' => count($risks),
            'risks' => $risks,
            'reassessmentTriggers' => array_values(array_filter(
                array_map(static fn ($trigger): array => [
                    'id' => $trigger->getId(),
                    'triggerType' => $trigger->getTriggerType(),
                    'description' => $trigger->getDescription(),
                    'isActive' => $trigger->isActive(),
                ], $this->reassessmentTriggerService->getListForAnr($anr)),
                static fn (array $trigger): bool => $trigger['isActive']
            )),
        ];
    }

    /** @return array{owned:int, approval:int} */
    public function getAssignmentCounts(Anr $anr, AnrSupervisor $supervisor): array
    {
        $roles = $this->buildRoles($supervisor);
        $counts = ['owned' => 0, 'approval' => 0];

        foreach ($this->getRiskRows($anr, $supervisor, $roles) as $risk) {
            if ($risk['canUpdateReview']) {
                ++$counts['owned'];
            }
            if ($risk['canUpdateResidual']) {
                ++$counts['approval'];
            }
        }

        return $counts;
    }

    private function getRiskRows(Anr $anr, AnrSupervisor $supervisor, array $roles): array
    {
        $language = $anr->getLanguage();
        $risks = [];

        foreach ($this->anrInstanceRiskService->getInstanceRisks($anr, null, [
            'order' => 'maxRisk',
            'order_direction' => 'desc',
            'thresholds' => -1,
            'limit' => 0,
        ]) as $risk) {
            $row = $this->mapInformationRisk($risk, $language, $supervisor, $roles);
            if ($row !== null) {
                $risks[] = $row;
            }
        }

        foreach ($this->anrInstanceRiskOpService->getOperationalRisks($anr, null, [
            'order' => 'cacheNetRisk',
            'order_direction' => 'desc',
            'thresholds' => -1,
            'limit' => 0,
        ]) as $risk) {
            $row = $this->mapOperationalRisk($risk, $language, $supervisor, $roles);
            if ($row !== null) {
                $risks[] = $row;
            }
        }

        usort(
            $risks,
            static function (array $left, array $right): int {
                return [$left['assetLabel'], $left['type'], $left['primaryLabel'], $left['id']]
                    <=> [$right['assetLabel'], $right['type'], $right['primaryLabel'], $right['id']];
            }
        );

        return $risks;
    }

    public function batchUpdate(Anr $anr, array $payload): array
    {
        $viewData = $this->getViewData($anr);
        $selectedRisks = $this->normalizeSelectedRisks($payload['risks'] ?? null);
        $updates = $this->normalizeUpdates($payload['updates'] ?? null);
        $riskRowsByKey = [];
        $skippedItems = [];
        $updatedCount = 0;

        foreach ($viewData['risks'] as $riskRow) {
            $riskRowsByKey[$this->buildRiskKey($riskRow['type'], (int)$riskRow['id'])] = $riskRow;
        }

        foreach ($selectedRisks as $selectedRisk) {
            $riskKey = $this->buildRiskKey($selectedRisk['type'], $selectedRisk['id']);
            $riskRow = $riskRowsByKey[$riskKey] ?? null;
            if ($riskRow === null) {
                $skippedItems[] = [
                    'type' => $selectedRisk['type'],
                    'id' => $selectedRisk['id'],
                    'reason' => 'current supervisor is not assigned to this risk',
                ];

                continue;
            }

            $riskUpdates = [];
            if ($updates['review'] !== [] && $riskRow['canUpdateReview']) {
                $riskUpdates = array_merge($riskUpdates, $updates['review']);
            }
            if ($updates['residual'] !== [] && $riskRow['canUpdateResidual']) {
                $riskUpdates = array_merge($riskUpdates, $updates['residual']);
            }

            if ($riskUpdates === []) {
                $skippedItems[] = [
                    'type' => $selectedRisk['type'],
                    'id' => $selectedRisk['id'],
                    'reason' => $this->buildSkippedReason($updates, $riskRow),
                ];

                continue;
            }

            try {
                if ($selectedRisk['type'] === 'information') {
                    $this->anrInstanceRiskService->update($anr, $selectedRisk['id'], $riskUpdates);
                } else {
                    $this->anrInstanceRiskOpService->update($anr, $selectedRisk['id'], $riskUpdates);
                }
                ++$updatedCount;
            } catch (\Throwable $exception) {
                $skippedItems[] = [
                    'type' => $selectedRisk['type'],
                    'id' => $selectedRisk['id'],
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return [
            'selected' => count($selectedRisks),
            'updated' => $updatedCount,
            'skipped' => count($skippedItems),
            'skipped_items' => $skippedItems,
        ];
    }

    private function requireEligibleSupervisor(Anr $anr): AnrSupervisor
    {
        $supervisor = $this->anrSupervisorService->findLinkedSupervisor($anr, $this->connectedUser);
        if ($supervisor === null || !$supervisor->isActive()) {
            throw new Exception('Current user is not linked to an active supervisor for this analysis.', 403);
        }

        if (!$supervisor->hasRole(AnrSupervisorRole::ROLE_RISK_OWNER)
            && !$supervisor->hasRole(AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER)
        ) {
            throw new Exception('Current linked supervisor has no risk management roles for this analysis.', 403);
        }

        return $supervisor;
    }

    private function buildRoles(AnrSupervisor $supervisor): array
    {
        return [
            'riskOwner' => $supervisor->hasRole(AnrSupervisorRole::ROLE_RISK_OWNER),
            'residualRiskApprover' => $supervisor->hasRole(AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER),
        ];
    }

    private function mapInformationRisk(
        array $risk,
        int $language,
        AnrSupervisor $supervisor,
        array $roles
    ): ?array {
        $canUpdateReview = $roles['riskOwner']
            && (int)($risk['riskOwnerSupervisorId'] ?? 0) === $supervisor->getId();
        $canUpdateResidual = $this->canUpdateResidualAcceptance($risk, $supervisor, $roles);
        if (!$canUpdateReview && !$canUpdateResidual) {
            return null;
        }

        return [
            'type' => 'information',
            'id' => (int)$risk['id'],
            'assetLabel' => (string)($risk['instanceName' . $language] ?? ''),
            'riskSourceLabel' => (string)($risk['riskSourceLabel'] ?? ''),
            'primaryLabel' => (string)($risk['threatLabel' . $language] ?? ''),
            'secondaryLabel' => (string)($risk['vulnLabel' . $language] ?? ''),
            'currentRiskValue' => $risk['max_risk'] ?? null,
            'residualRiskValue' => $risk['target_risk'] ?? null,
            'kindOfMeasure' => $risk['kindOfMeasure'] ?? null,
            'riskOwnerName' => (string)($risk['owner'] ?? ''),
            'residualApproverName' => $this->getResidualApproverName($risk),
            'residualRiskDecision' => $risk['residualRiskDecision'] ?? null,
            'residualRiskDecidedAt' => $risk['residualRiskDecidedAt'] ?? null,
            'residualRiskJustification' => $risk['residualRiskJustification'] ?? null,
            'lastReviewDate' => $risk['lastReviewDate'] ?? null,
            'nextReassessmentDate' => $risk['nextReassessmentDate'] ?? null,
            'reassessmentTriggers' => $risk['reassessmentTriggers'] ?? [],
            'reviewFrequency' => $risk['reviewFrequencyLabel'] ?? $risk['reviewFrequency'] ?? null,
            'canUpdateReview' => $canUpdateReview,
            'canUpdateResidual' => $canUpdateResidual,
        ];
    }

    private function mapOperationalRisk(
        array $risk,
        int $language,
        AnrSupervisor $supervisor,
        array $roles
    ): ?array {
        $canUpdateReview = $roles['riskOwner']
            && (int)($risk['riskOwnerSupervisorId'] ?? 0) === $supervisor->getId();
        $canUpdateResidual = $this->canUpdateResidualAcceptance($risk, $supervisor, $roles);
        if (!$canUpdateReview && !$canUpdateResidual) {
            return null;
        }

        $residualRiskValue = $risk['cacheTargetedRisk'] ?? null;
        if ((int)$residualRiskValue === -1) {
            $residualRiskValue = $risk['cacheNetRisk'] ?? null;
        }

        return [
            'type' => 'operational',
            'id' => (int)$risk['id'],
            'assetLabel' => (string)($risk['instanceInfos']['name' . $language] ?? ''),
            'riskSourceLabel' => (string)($risk['riskSourceLabel'] ?? ''),
            'primaryLabel' => (string)($risk['label' . $language] ?? ''),
            'secondaryLabel' => (string)($risk['description' . $language] ?? ''),
            'currentRiskValue' => $risk['cacheNetRisk'] ?? null,
            'residualRiskValue' => $residualRiskValue,
            'kindOfMeasure' => $risk['kindOfMeasure'] ?? null,
            'riskOwnerName' => (string)($risk['owner'] ?? ''),
            'residualApproverName' => $this->getResidualApproverName($risk),
            'residualRiskDecision' => $risk['residualRiskDecision'] ?? null,
            'residualRiskDecidedAt' => $risk['residualRiskDecidedAt'] ?? null,
            'residualRiskJustification' => $risk['residualRiskJustification'] ?? null,
            'lastReviewDate' => $risk['lastReviewDate'] ?? null,
            'nextReassessmentDate' => $risk['nextReassessmentDate'] ?? null,
            'reassessmentTriggers' => $risk['reassessmentTriggers'] ?? [],
            'reviewFrequency' => $risk['reviewFrequencyLabel'] ?? $risk['reviewFrequency'] ?? null,
            'canUpdateReview' => $canUpdateReview,
            'canUpdateResidual' => $canUpdateResidual,
        ];
    }

    private function canUpdateResidualAcceptance(array $risk, AnrSupervisor $supervisor, array $roles): bool
    {
        if (!$roles['residualRiskApprover']) {
            return false;
        }

        if ((int)($risk['residualAcceptanceApproverSupervisorId'] ?? 0) === $supervisor->getId()) {
            return true;
        }

        return !empty($risk['residualAcceptanceUseRiskOwner'])
            && (int)($risk['riskOwnerSupervisorId'] ?? 0) === $supervisor->getId();
    }

    private function getResidualApproverName(array $risk): string
    {
        if (!empty($risk['residualAcceptanceUseRiskOwner'])) {
            return (string)($risk['riskOwnerSupervisorName'] ?? $risk['owner'] ?? '');
        }

        return (string)($risk['residualAcceptanceApproverSupervisor']['name'] ?? '');
    }

    private function normalizeSelectedRisks(mixed $selectedRisks): array
    {
        if (!is_array($selectedRisks) || $selectedRisks === []) {
            throw new Exception('At least one risk must be selected for batch update.', 412);
        }

        $result = [];
        foreach ($selectedRisks as $selectedRisk) {
            if (!is_array($selectedRisk)) {
                throw new Exception('Invalid selected risk payload.', 412);
            }

            $type = trim((string)($selectedRisk['type'] ?? ''));
            $id = (int)($selectedRisk['id'] ?? 0);
            if (!in_array($type, ['information', 'operational'], true) || $id <= 0) {
                throw new Exception('Each selected risk must include a valid type and id.', 412);
            }

            $result[$this->buildRiskKey($type, $id)] = [
                'type' => $type,
                'id' => $id,
            ];
        }

        return array_values($result);
    }

    private function normalizeUpdates(mixed $updates): array
    {
        if (!is_array($updates)) {
            throw new Exception('Batch update payload must include an updates object.', 412);
        }

        $reviewUpdates = [];
        $residualUpdates = [];

        if (array_key_exists('last_review_date', $updates)) {
            $reviewUpdates['lastReviewDate'] = $this->normalizeNullableDate($updates['last_review_date']);
        }
        if (array_key_exists('next_reassessment_date', $updates)) {
            $reviewUpdates['nextReassessmentDate'] = $this->normalizeNullableDate($updates['next_reassessment_date']);
        }
        if (array_key_exists('reassessment_trigger_ids', $updates)) {
            $reviewUpdates['reassessmentTriggerIds'] = $updates['reassessment_trigger_ids'];
        }
        if (array_key_exists('review_frequency', $updates)) {
            $reviewUpdates['reviewFrequency'] = $this->normalizeNullableText($updates['review_frequency']);
        }
        if (array_key_exists('residual_risk_decision', $updates)) {
            $residualUpdates['residualRiskDecision'] = $this->normalizeNullableDecision(
                $updates['residual_risk_decision']
            );
        }
        if (array_key_exists('residual_risk_decided_at', $updates)) {
            $residualUpdates['residualRiskDecidedAt'] = $this->normalizeNullableDate(
                $updates['residual_risk_decided_at']
            );
        }
        if (array_key_exists('residual_risk_justification', $updates)) {
            $residualUpdates['residualRiskJustification'] = $this->normalizeNullableText(
                $updates['residual_risk_justification']
            );
        }

        if ($reviewUpdates === [] && $residualUpdates === []) {
            throw new Exception('At least one update field must be provided.', 412);
        }

        return [
            'review' => $reviewUpdates,
            'residual' => $residualUpdates,
        ];
    }

    private function normalizeNullableDecision(mixed $value): ?string
    {
        $normalizedValue = $this->normalizeNullableText($value);
        if ($normalizedValue === null) {
            return null;
        }

        $normalizedValue = mb_strtolower($normalizedValue);
        if (!in_array($normalizedValue, ['accepted', 'not_accepted'], true)) {
            throw new Exception('Residual risk decision must be "accepted" or "not_accepted".', 412);
        }

        return $normalizedValue;
    }

    private function normalizeNullableDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $normalizedDate = \DateTime::createFromFormat('Y-m-d', $value);
        if ($normalizedDate === false || $normalizedDate->format('Y-m-d') !== $value) {
            throw new Exception('Invalid date format. Expected YYYY-MM-DD.', 412);
        }

        return $value;
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function buildSkippedReason(array $updates, array $riskRow): string
    {
        $reviewRequested = $updates['review'] !== [];
        $residualRequested = $updates['residual'] !== [];

        if ($reviewRequested && !$riskRow['canUpdateReview'] && !$residualRequested) {
            return 'current supervisor is not the risk owner for this risk';
        }

        if ($residualRequested && !$riskRow['canUpdateResidual'] && !$reviewRequested) {
            return 'current supervisor is not the residual risk approver for this risk';
        }

        return 'current supervisor cannot update the requested fields for this risk';
    }

    private function buildRiskKey(string $type, int $id): string
    {
        return $type . ':' . $id;
    }
}
