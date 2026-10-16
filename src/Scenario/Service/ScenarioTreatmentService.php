<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioAssessmentTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioReferenceTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTreatmentTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\UserTable;

/** Applies the Scenario treatment state machine without modifying assessment or legacy risk evidence. */
final class ScenarioTreatmentService
{
    public function __construct(
        private ScenarioAssessmentTable $assessmentTable,
        private ScenarioTreatmentTable $treatmentTable,
        private ScenarioReferenceTable $references,
        private UserTable $userTable,
        private UserAnrTable $userAnrTable,
        private ConnectedUserService $users
    ) {
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function create(
        Anr $anr,
        string $riskScenarioUuid,
        array $data,
        int $riskScenarioRevision,
        string $correlationId
    ): ?array {
        [$recommendationReferences, $treatmentImportance] = $this->recommendationSelection($data);
        $recommendations = $this->references->recommendationSnapshots($anr, $recommendationReferences);
        if (count($recommendations) !== count($recommendationReferences)) {
            throw new \InvalidArgumentException(
                'One or more selected recommendations are unavailable in this analysis.'
            );
        }
        foreach ($recommendations as &$recommendation) {
            $uuid = (string) $recommendation['recommendationUuid'];
            $recommendation['treatmentImportance'] = $treatmentImportance[$uuid]
                ?? (int) ($recommendation['importance'] ?? 0);
        }
        unset($recommendation);
        $this->assertInitialTerminalState($data, $recommendations);
        $owner = $this->ownerSnapshot($anr, $data['ownerId'] ?? null);

        $created = $this->assessmentTable->createTreatment(
            (int) $anr->getId(),
            $riskScenarioUuid,
            [
                'treatment_type' => $data['treatmentType'],
                'action_text' => $data['actionText'],
                'owner_id' => $owner['id'] ?? null,
                'due_at' => $this->databaseDate($data['dueAt'] ?? null),
                'status' => $data['status'],
                'completion_evidence' => $data['completionEvidence'] ?? null,
                'cancellation_rationale' => $data['cancellationRationale'] ?? null,
                'recommendations' => $recommendations,
            ],
            $riskScenarioRevision,
            $this->actorId(),
            $correlationId
        );
        if ($created === null) {
            return null;
        }

        $treatment = $this->treatmentTable->get(
            (int) $anr->getId(),
            $riskScenarioUuid,
            (string) $created['uuid']
        );
        if ($treatment === null) {
            throw new \RuntimeException('Scenario treatment implementation items were not saved.');
        }

        return $treatment + ['riskScenarioRevision' => $created['riskScenarioRevision']];
    }

    /** @param array<string, mixed> $data @return array{0: array<int, string>, 1: array<string, int>} */
    private function recommendationSelection(array $data): array
    {
        if (!is_array($data['recommendationSelections'] ?? null)) {
            $references = is_array($data['recommendationReferences'] ?? null)
                ? $data['recommendationReferences']
                : [];

            return [$references, []];
        }

        $references = [];
        $importance = [];
        foreach ($data['recommendationSelections'] as $selection) {
            $uuid = (string) $selection['recommendationUuid'];
            $references[] = $uuid;
            $importance[$uuid] = (int) $selection['treatmentImportance'];
        }

        return [$references, $importance];
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function list(Anr $anr, string $riskScenarioUuid): array
    {
        return $this->treatmentTable->list((int) $anr->getId(), $riskScenarioUuid);
    }

    /** @return array<string, mixed>|null */
    public function get(Anr $anr, string $riskScenarioUuid, string $treatmentUuid): ?array
    {
        return $this->treatmentTable->get((int) $anr->getId(), $riskScenarioUuid, $treatmentUuid);
    }

    /** @return array<string, mixed>|null */
    public function getImplementation(
        Anr $anr,
        string $riskScenarioUuid,
        string $treatmentUuid,
        string $implementationUuid
    ): ?array {
        return $this->treatmentTable->getImplementation(
            (int) $anr->getId(),
            $riskScenarioUuid,
            $treatmentUuid,
            $implementationUuid
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function history(Anr $anr, string $riskScenarioUuid, string $treatmentUuid): array
    {
        return $this->treatmentTable->history((int) $anr->getId(), $riskScenarioUuid, $treatmentUuid);
    }

    /** @return array{items: array<int, array{id:int,name:string,email:string}>, canEdit: bool} */
    public function owners(Anr $anr): array
    {
        $items = [];
        foreach ($this->userAnrTable->findLinksForAnr($anr) as $link) {
            $user = $link->getUser();
            $items[] = [
                'id' => $user->getId(),
                'name' => trim($user->getFirstname() . ' ' . $user->getLastname()) ?: $user->getEmail(),
                'email' => $user->getEmail(),
            ];
        }

        $actor = $this->users->getConnectedUser();
        $link = $actor instanceof User ? $this->userAnrTable->findByAnrAndUser($anr, $actor) : null;

        return ['items' => $items, 'canEdit' => $link !== null && $link->hasWriteAccess()];
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function update(
        Anr $anr,
        string $riskScenarioUuid,
        string $treatmentUuid,
        array $data,
        int $riskScenarioRevision,
        int $treatmentRevision,
        string $correlationId,
        string $idempotencyKey
    ): ?array {
        if (array_key_exists('ownerId', $data)) {
            $data['ownerSnapshot'] = $this->ownerSnapshot($anr, $data['ownerId']);
        }
        if (array_key_exists('dueAt', $data)) {
            $data['dueAt'] = $this->databaseDate($data['dueAt']);
        }

        return $this->treatmentTable->updateTreatment(
            (int) $anr->getId(),
            $riskScenarioUuid,
            $treatmentUuid,
            $data,
            $riskScenarioRevision,
            $treatmentRevision,
            $this->actorId(),
            $correlationId,
            $idempotencyKey
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function updateImplementation(
        Anr $anr,
        string $riskScenarioUuid,
        string $treatmentUuid,
        string $implementationUuid,
        array $data,
        int $riskScenarioRevision,
        int $treatmentRevision,
        string $correlationId,
        string $idempotencyKey
    ): ?array {
        if (array_key_exists('ownerId', $data)) {
            $data['ownerSnapshot'] = $this->ownerSnapshot($anr, $data['ownerId']);
        }
        if (array_key_exists('targetAt', $data)) {
            $data['targetAt'] = $this->databaseDate($data['targetAt']);
        }

        return $this->treatmentTable->updateImplementation(
            (int) $anr->getId(),
            $riskScenarioUuid,
            $treatmentUuid,
            $implementationUuid,
            $data,
            $riskScenarioRevision,
            $treatmentRevision,
            $this->actorId(),
            $correlationId,
            $idempotencyKey
        );
    }

    /** @param array<string, mixed> $data @param array<int, array<string, mixed>> $recommendations */
    private function assertInitialTerminalState(array $data, array $recommendations): void
    {
        if ($data['status'] === 'completed') {
            if ($recommendations !== []) {
                throw new \InvalidArgumentException(
                    'Selected recommendations start as not started and must be tracked '
                    . 'before the treatment is completed.'
                );
            }
            if (trim((string) ($data['completionEvidence'] ?? '')) === '') {
                throw new \InvalidArgumentException(
                    'Completing a treatment without recommendations requires completion evidence.'
                );
            }
        }
        if ($data['status'] === 'cancelled'
            && trim((string) ($data['cancellationRationale'] ?? '')) === ''
        ) {
            throw new \InvalidArgumentException('Cancelling a treatment requires a cancellation rationale.');
        }
    }

    /** @return array{id:int,name:string,email:string}|null */
    private function ownerSnapshot(Anr $anr, mixed $ownerId): ?array
    {
        if ($ownerId === null || $ownerId === '') {
            return null;
        }
        if (!is_int($ownerId) && !ctype_digit((string) $ownerId)) {
            throw new \InvalidArgumentException('The selected treatment owner is invalid.');
        }
        $user = $this->userTable->findById((int) $ownerId, false);
        if (!$user instanceof User || $this->userAnrTable->findByAnrAndUser($anr, $user) === null) {
            throw new ActionForbiddenException('The selected treatment owner is not authorised for this analysis.');
        }

        return [
            'id' => $user->getId(),
            'name' => trim($user->getFirstname() . ' ' . $user->getLastname()) ?: $user->getEmail(),
            'email' => $user->getEmail(),
        ];
    }

    private function actorId(): int
    {
        $user = $this->users->getConnectedUser();
        if (!$user instanceof User) {
            throw new ActionForbiddenException('Scenario treatment access is forbidden.');
        }

        return (int) $user->getId();
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
