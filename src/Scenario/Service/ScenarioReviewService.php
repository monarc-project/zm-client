<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use DateTimeInterface;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioReviewTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\UserTable;

/** Coordinates the Scenario review state machine without rewriting assessment, treatment, or acceptance evidence. */
class ScenarioReviewService
{
    public function __construct(
        private ScenarioReviewTable $reviews,
        private ScenarioReviewSchedule $schedule,
        private ConnectedUserService $users,
        private UserTable $userTable,
        private UserAnrTable $userAnrTable
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(Anr $anr): array
    {
        $summary = $this->reviews->summary((int) $anr->getId(), $this->schedule);
        $user = $this->users->getConnectedUser();
        $link = $user instanceof User ? $this->userAnrTable->findByAnrAndUser($anr, $user) : null;

        return $summary + ['canEdit' => $link !== null && $link->hasWriteAccess()];
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listCases(Anr $anr): array
    {
        return $this->reviews->listCases((int) $anr->getId());
    }

    /** @return array<string, mixed>|null */
    public function case(Anr $anr, string $uuid): ?array
    {
        return $this->reviews->case((int) $anr->getId(), $uuid);
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createCase(Anr $anr, array $data, string $correlationId): ?array
    {
        return $this->reviews->createCase(
            (int) $anr->getId(),
            $data,
            $this->actorId(),
            $correlationId,
            $this->schedule
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function recordTriggerOccurrence(Anr $anr, array $data, string $correlationId): ?array
    {
        return $this->reviews->recordTriggerOccurrence(
            (int) $anr->getId(),
            $data,
            $this->actorId(),
            $correlationId,
            $this->schedule
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listOccurrences(Anr $anr): array
    {
        return $this->reviews->listOccurrences((int) $anr->getId());
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function transition(
        Anr $anr,
        string $caseUuid,
        string $action,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        if ($action === 'assign') {
            $this->assertReviewer($anr, (int) $data['assignedReviewerId']);
        }

        return $this->reviews->transition(
            (int) $anr->getId(),
            $caseUuid,
            $action,
            $data,
            $revision,
            $this->actorId(),
            $correlationId,
            $this->schedule
        );
    }

    public function synchronizeStrategyProjection(Anr $anr, ?DateTimeInterface $now = null): void
    {
        $this->reviews->synchronizeProjection((int) $anr->getId(), $this->schedule, $now);
    }

    /** @param callable():void $saveStrategy */
    public function saveStrategyAndSynchronizeProjection(Anr $anr, callable $saveStrategy): void
    {
        $this->reviews->transactionally(function () use ($anr, $saveStrategy): void {
            $saveStrategy();
            $this->synchronizeStrategyProjection($anr);
        });
    }

    /** @return array{opened:int,skipped:bool} */
    public function openDueScheduledCases(?DateTimeInterface $now = null): array
    {
        return $this->reviews->openDueScheduledCases($this->schedule, $now);
    }

    private function assertReviewer(Anr $anr, int $reviewerId): void
    {
        $reviewer = $this->userTable->findById($reviewerId, false);
        if (!$reviewer instanceof User || $this->userAnrTable->findByAnrAndUser($anr, $reviewer) === null) {
            throw new ActionForbiddenException('The selected review owner is not authorised for this analysis.');
        }
    }

    private function actorId(): int
    {
        $user = $this->users->getConnectedUser();
        if (!$user instanceof User) {
            throw new ActionForbiddenException('Scenario review access is forbidden.');
        }

        return (int) $user->getId();
    }
}
