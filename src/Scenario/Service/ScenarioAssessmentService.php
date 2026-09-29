<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioAssessmentTable;
use Monarc\FrontOffice\Service\AnrSupervisorService;

/** Coordinates authorised, explainable Scenario assessment and decision evidence. */
final class ScenarioAssessmentService
{
    public function __construct(
        private ScenarioAssessmentTable $assessments,
        private ScenarioQualitativeCalculationService $calculator,
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
        string $storyUuid,
        array $input,
        int $storyRevision,
        string $correlationId
    ): ?array {
        $type = (string) ($input['assessmentType'] ?? '');
        if (!in_array($type, ['inherent', 'current', 'proposed_residual'], true)) {
            throw new \InvalidArgumentException('Assessment type is invalid.');
        }
        $profile = $this->assignedProfile($anr);
        if ($profile === null
            || ($profile['versionUuid'] ?? null) !== ($input['profileVersionUuid'] ?? null)) {
            throw new ActionForbiddenException(
                'The selected criteria profile is not assigned to this Scenario analysis.'
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
            $storyUuid,
            $type,
            (string) $input['profileVersionUuid'],
            $calculation,
            isset($input['legacyComparison']) && is_array($input['legacyComparison'])
                ? $input['legacyComparison']
                : null,
            $storyRevision,
            $this->actorId(),
            $correlationId
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listAssessments(Anr $anr, string $storyUuid): array
    {
        return $this->assessments->listAssessments((int) $anr->getId(), $storyUuid);
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createTreatment(
        Anr $anr,
        string $storyUuid,
        array $data,
        int $storyRevision,
        string $correlationId
    ): ?array {
        return $this->assessments->createTreatment(
            (int) $anr->getId(),
            $storyUuid,
            [
                'treatment_type' => $data['treatmentType'],
                'action_text' => $data['actionText'],
                'owner_id' => $data['ownerId'] ?? null,
                'due_at' => $this->databaseDate($data['dueAt'] ?? null),
                'status' => $data['status'],
            ],
            $storyRevision,
            $this->actorId(),
            $correlationId
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listTreatments(Anr $anr, string $storyUuid): array
    {
        return $this->assessments->listStoryRecords(
            (int) $anr->getId(),
            $storyUuid,
            'scenario_treatments'
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createMonitoring(
        Anr $anr,
        string $storyUuid,
        array $data,
        int $storyRevision,
        string $correlationId
    ): ?array {
        return $this->assessments->createMonitoring(
            (int) $anr->getId(),
            $storyUuid,
            [
                'indicator' => $data['indicator'],
                'reassessment_trigger' => $data['reassessmentTrigger'],
                'observation' => $data['observation'] ?? null,
            ],
            $storyRevision,
            $this->actorId(),
            $correlationId
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listMonitoring(Anr $anr, string $storyUuid): array
    {
        return $this->assessments->listStoryRecords(
            (int) $anr->getId(),
            $storyUuid,
            'scenario_monitoring_observations'
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function acceptResidualRisk(
        Anr $anr,
        string $storyUuid,
        array $data,
        int $storyRevision,
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
            $storyUuid,
            (string) $data['assessmentUuid'],
            (int) $assigned->getId(),
            [
                'decision' => $data['decision'],
                'reason' => $data['reason'],
                'actorId' => (int) $actor->getId(),
                'approverSupervisorId' => (int) $assigned->getId(),
                'decidedAt' => gmdate('Y-m-d\\TH:i:s\\Z'),
            ],
            $storyRevision,
            (int) $actor->getId(),
            $correlationId
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listAcceptances(Anr $anr, string $storyUuid): array
    {
        return $this->assessments->listAcceptances((int) $anr->getId(), $storyUuid);
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
