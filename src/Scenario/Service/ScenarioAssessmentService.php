<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioAssessmentTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioReferenceTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTreatmentTable;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table\ReassessmentTriggerTable;

/** Coordinates authorised, explainable Scenario assessment and decision evidence. */
final class ScenarioAssessmentService
{
    public function __construct(
        private ScenarioAssessmentTable $assessments,
        private ScenarioReferenceTable $references,
        private ScenarioTreatmentTable $treatments,
        private ScenarioQualitativeCalculationService $calculator,
        private ReassessmentTriggerTable $reassessmentTriggers,
        private ConnectedUserService $users,
        private \Closure $supervisorService
    ) {
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listProfiles(): array
    {
        return $this->assessments->listProfiles();
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createProfileVersion(Anr $anr, array $data, int $revision, string $correlationId): ?array
    {
        $this->calculator->validateProfile($data);

        return $this->assessments->createProfileVersion(
            (string) $data['identifier'],
            (string) $data['title'],
            $data,
            $revision,
            $this->actorId(),
            (int) $anr->getId(),
            $correlationId
        );
    }

    /** @return array<string, mixed>|null */
    public function assignProfile(
        Anr $anr,
        string $profileVersionUuid,
        int $analysisRevision,
        string $correlationId
    ): ?array {
        return $this->assessments->assignProfile(
            (int) $anr->getId(),
            $profileVersionUuid,
            $analysisRevision,
            $this->actorId(),
            $correlationId
        );
    }

    /** @return array<string, mixed>|null */
    public function assignedProfile(Anr $anr): ?array
    {
        return $this->assessments->assignedProfile((int) $anr->getId());
    }

    /** @param array<string, mixed> $input @return array<string, mixed>|null */
    public function assess(
        Anr $anr,
        string $riskScenarioUuid,
        array $input,
        int $riskScenarioRevision,
        string $correlationId
    ): ?array {
        $type = (string) ($input['assessmentType'] ?? '');
        if (!in_array($type, ['inherent', 'current', 'proposed_residual'], true)) {
            throw new \InvalidArgumentException(
                'Only inherent, current, and proposed residual assessments are supported for Scenario analysis.'
            );
        }
        $profile = $this->assignedProfile($anr);
        if ($profile === null || !($profile['isLocked'] ?? false)
            || ($profile['versionUuid'] ?? null) !== ($input['profileVersionUuid'] ?? null)) {
            throw new ActionForbiddenException(
                'Scenario assessment requires the locked criteria profile assigned to this Scenario analysis.'
            );
        }

        $calculation = $this->calculator->calculate((array) $profile['payload'], $input);
        $calculation['profileVersion'] = [
            'profileUuid' => $profile['uuid'],
            'versionUuid' => $profile['versionUuid'],
            'identifier' => $profile['identifier'],
            'version' => $profile['version'],
        ];

        return $this->assessments->saveAssessment(
            (int) $anr->getId(),
            $riskScenarioUuid,
            $type,
            (string) $input['profileVersionUuid'],
            $calculation,
            isset($input['legacyComparison']) && is_array($input['legacyComparison'])
                ? $input['legacyComparison']
                : null,
            $riskScenarioRevision,
            $this->actorId(),
            $correlationId
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listAssessments(Anr $anr, string $riskScenarioUuid): array
    {
        return $this->assessments->listAssessments((int) $anr->getId(), $riskScenarioUuid);
    }

    /**
     * Applies the latest proposed residual assessment as current evidence only after
     * treatment-plan implementation is complete. This is a deliberate user action;
     * it neither accepts the residual risk nor changes the criteria profile.
     *
     * @return array<string, mixed>|null
     */
    public function applyRiskReduction(
        Anr $anr,
        string $riskScenarioUuid,
        int $riskScenarioRevision,
        string $correlationId
    ): ?array {
        $anrId = (int) $anr->getId();
        if (!$this->treatments->isRiskReductionEligible($anrId, $riskScenarioUuid)) {
            throw new \InvalidArgumentException(
                'Complete or cancel every treatment and complete or mark every selected recommendation '
                . 'not applicable before applying risk reduction.'
            );
        }

        $proposed = null;
        foreach ($this->assessments->listAssessments($anrId, $riskScenarioUuid)['items'] as $assessment) {
            if (($assessment['assessmentType'] ?? null) === 'proposed_residual') {
                $proposed = $assessment;
                break;
            }
        }
        if ($proposed === null || !is_array($proposed['calculation'] ?? null)) {
            throw new \InvalidArgumentException(
                'Record a proposed residual assessment before applying risk reduction.'
            );
        }

        $calculation = $proposed['calculation'];
        $inputs = $calculation['inputs'] ?? null;
        $profileVersion = $calculation['profileVersion']['versionUuid'] ?? null;
        if (!is_array($inputs) || !is_string($profileVersion)
            || !isset($inputs['likelihood'], $inputs['dimensionSeverities'])
            || !is_array($inputs['dimensionSeverities'])) {
            throw new \InvalidArgumentException(
                'The proposed residual assessment cannot be applied. Record it again first.'
            );
        }

        $profile = $this->assignedProfile($anr);
        if ($profile === null || !($profile['isLocked'] ?? false) || $profile['versionUuid'] !== $profileVersion) {
            throw new ActionForbiddenException(
                'Applying risk reduction requires the locked criteria profile used by the proposed residual assessment.'
            );
        }

        $input = [
            'assessmentType' => 'current',
            'profileVersionUuid' => $profileVersion,
            'likelihood' => $inputs['likelihood'],
            'dimensionSeverities' => $inputs['dimensionSeverities'],
            'justification' => 'Applied the proposed residual assessment after all treatment-plan records '
                . 'reached a terminal implementation state.',
        ];
        $reducedCalculation = $this->calculator->calculate((array) $profile['payload'], $input);
        $reducedCalculation['profileVersion'] = [
            'profileUuid' => $profile['uuid'],
            'versionUuid' => $profile['versionUuid'],
            'identifier' => $profile['identifier'],
            'version' => $profile['version'],
        ];
        $reducedCalculation['riskReduction'] = [
            'sourceProposedResidualAssessmentUuid' => $proposed['uuid'],
            'appliedAfterTreatmentCompletion' => true,
        ];

        return $this->assessments->saveAssessment(
            $anrId,
            $riskScenarioUuid,
            'current',
            $profileVersion,
            $reducedCalculation,
            null,
            $riskScenarioRevision,
            $this->actorId(),
            $correlationId,
            'scenario_risk_reduction_applied',
            ['sourceProposedResidualAssessmentUuid' => $proposed['uuid']]
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createTreatment(
        Anr $anr,
        string $riskScenarioUuid,
        array $data,
        int $riskScenarioRevision,
        string $correlationId
    ): ?array {
        $recommendationReferences = is_array($data['recommendationReferences'] ?? null)
            ? $data['recommendationReferences']
            : [];
        $recommendations = $this->references->recommendationSnapshots($anr, $recommendationReferences);
        if (count($recommendations) !== count($recommendationReferences)) {
            throw new \InvalidArgumentException(
                'One or more selected recommendations are unavailable in this analysis.'
            );
        }

        return $this->assessments->createTreatment(
            (int) $anr->getId(),
            $riskScenarioUuid,
            [
                'treatment_type' => $data['treatmentType'],
                'action_text' => $data['actionText'],
                'owner_id' => $data['ownerId'] ?? null,
                'due_at' => $this->databaseDate($data['dueAt'] ?? null),
                'status' => $data['status'],
                'recommendations' => $recommendations,
            ],
            $riskScenarioRevision,
            $this->actorId(),
            $correlationId
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listTreatments(Anr $anr, string $riskScenarioUuid): array
    {
        return $this->assessments->listRiskScenarioRecords(
            (int) $anr->getId(),
            $riskScenarioUuid,
            'scenario_treatments'
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createMonitoring(
        Anr $anr,
        string $riskScenarioUuid,
        array $data,
        int $riskScenarioRevision,
        string $correlationId
    ): ?array {
        $trigger = $this->reassessmentTriggers->findByIdAndAnr(
            (int) $data['reassessmentTriggerId'],
            $anr,
            false
        );
        if (!$trigger instanceof \Monarc\FrontOffice\Entity\ReassessmentTrigger || !$trigger->isActive()) {
            throw new \InvalidArgumentException(
                'Select an active reassessment trigger from Context Establishment before recording monitoring.'
            );
        }

        return $this->assessments->createMonitoring(
            (int) $anr->getId(),
            $riskScenarioUuid,
            [
                'indicator' => $data['indicator'],
                'reassessment_trigger_id' => $trigger->getId(),
                'reassessment_trigger' => $this->triggerSnapshot($trigger),
                'observation' => $data['observation'] ?? null,
            ],
            $riskScenarioRevision,
            $this->actorId(),
            $correlationId
        );
    }

    private function triggerSnapshot(\Monarc\FrontOffice\Entity\ReassessmentTrigger $trigger): string
    {
        $type = trim((string) $trigger->getTriggerType());
        $description = trim((string) $trigger->getDescription());

        return $type === '' ? $description : $type . ' — ' . $description;
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listMonitoring(Anr $anr, string $riskScenarioUuid): array
    {
        return $this->assessments->listRiskScenarioRecords(
            (int) $anr->getId(),
            $riskScenarioUuid,
            'scenario_monitoring_observations'
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function acceptResidualRisk(
        Anr $anr,
        string $riskScenarioUuid,
        array $data,
        int $riskScenarioRevision,
        string $correlationId
    ): ?array {
        $actor = $this->connectedUser();
        $supervisors = ($this->supervisorService)();
        $assigned = $supervisors->getResidualRiskApproverSupervisor($anr, $data['assignedSupervisorId'] ?? null);
        $linked = $supervisors->findLinkedSupervisor($anr, $actor);
        if ($linked === null
            || !$linked->isActive()
            || !$linked->hasRole(AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER)
            || $linked->getId() !== $assigned?->getId()) {
            throw new ActionForbiddenException('Scenario residual-risk acceptance is forbidden.');
        }

        return $this->assessments->createAcceptance(
            (int) $anr->getId(),
            $riskScenarioUuid,
            (string) $data['assessmentUuid'],
            (int) $assigned->getId(),
            [
                'decision' => $data['decision'],
                'reason' => $data['reason'],
                'actorId' => (int) $actor->getId(),
                'approverSupervisorId' => (int) $assigned->getId(),
                'decidedAt' => gmdate('Y-m-d\\TH:i:s\\Z'),
            ],
            $riskScenarioRevision,
            (int) $actor->getId(),
            $correlationId
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listAcceptances(Anr $anr, string $riskScenarioUuid): array
    {
        return $this->assessments->listAcceptances((int) $anr->getId(), $riskScenarioUuid);
    }

    private function actorId(): int
    {
        return (int) $this->connectedUser()->getId();
    }

    private function connectedUser(): User
    {
        $user = $this->users->getConnectedUser();
        if (!$user instanceof User) {
            throw new ActionForbiddenException('Scenario assessment access is forbidden.');
        }

        return $user;
    }

    private function databaseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new \DateTimeImmutable((string) $value))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }
}
