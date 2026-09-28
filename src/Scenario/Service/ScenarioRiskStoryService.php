<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Exception\ScenarioRiskStoryException;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioReferenceTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioRiskStoryTable;

/** Coordinates authorised Scenario risk-story writes without exposing DBAL to controllers. */
final class ScenarioRiskStoryService
{
    public function __construct(
        private ScenarioRiskStoryTable $stories,
        private ScenarioAnalysisTable $analyses,
        private ScenarioReferenceTable $references,
        private ConnectedUserService $users
    ) {
    }

    /** @return array<string, mixed> */
    public function list(Anr $anr, int $page, int $pageSize): array
    {
        return $this->stories->list((int) $anr->getId(), $page, $pageSize);
    }

    /** @return array<string, mixed>|null */
    public function get(Anr $anr, string $uuid): ?array
    {
        return $this->stories->detail((int) $anr->getId(), $uuid);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(Anr $anr, array $data, string $correlationId): array
    {
        $analysisId = $this->analyses->idByAnr((int) $anr->getId());
        if ($analysisId === null) {
            throw new ActionForbiddenException('Scenario analysis was not found.');
        }

        return $this->stories->createScenario(
            (int) $anr->getId(),
            $analysisId,
            $this->actorId(),
            $data,
            $correlationId
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function update(
        Anr $anr,
        string $uuid,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        return $this->stories->updateScenario(
            (int) $anr->getId(),
            $uuid,
            $this->actorId(),
            $data,
            $revision,
            $correlationId
        );
    }

    public function delete(Anr $anr, string $uuid, int $revision, string $correlationId): bool
    {
        return $this->stories->deleteScenario(
            (int) $anr->getId(),
            $uuid,
            $this->actorId(),
            $revision,
            $correlationId
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function saveSource(
        Anr $anr,
        string $uuid,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        if (isset($data['legacyRiskSourceId'])
            && !$this->references->hasRiskSource((int) $anr->getId(), (string) $data['legacyRiskSourceId'])) {
            throw new ScenarioRiskStoryException(
                'unavailable_reference',
                'The selected risk source is unavailable in this analysis.'
            );
        }

        return $this->stories->saveSource(
            (int) $anr->getId(),
            $uuid,
            $this->actorId(),
            $data,
            $revision,
            $correlationId
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function saveCause(
        Anr $anr,
        string $uuid,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        $anrId = (int) $anr->getId();
        if (isset($data['threatReferenceUuid'])
            && !$this->references->hasThreat($anrId, (string) $data['threatReferenceUuid'])) {
            throw new ScenarioRiskStoryException('unavailable_reference', 'The selected threat is unavailable.');
        }
        if (isset($data['vulnerabilityReferenceUuid'])
            && !$this->references->hasVulnerability($anrId, (string) $data['vulnerabilityReferenceUuid'])) {
            throw new ScenarioRiskStoryException(
                'unavailable_reference',
                'The selected vulnerability is unavailable.'
            );
        }

        return $this->stories->saveCause(
            $anrId,
            $uuid,
            $this->actorId(),
            $data,
            $revision,
            $correlationId
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createNode(
        Anr $anr,
        string $scenarioUuid,
        string $type,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        return $this->stories->createNode(
            (int) $anr->getId(),
            $scenarioUuid,
            $type,
            $this->actorId(),
            $data,
            $revision,
            $correlationId
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function updateNode(
        Anr $anr,
        string $scenarioUuid,
        string $type,
        string $uuid,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        return $this->stories->updateNode(
            (int) $anr->getId(),
            $scenarioUuid,
            $type,
            $uuid,
            $this->actorId(),
            $data,
            $revision,
            $correlationId
        );
    }

    public function deleteNode(
        Anr $anr,
        string $scenarioUuid,
        string $type,
        string $uuid,
        int $revision,
        string $correlationId
    ): bool {
        return $this->stories->deleteNode(
            (int) $anr->getId(),
            $scenarioUuid,
            $type,
            $uuid,
            $this->actorId(),
            $revision,
            $correlationId
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createRelation(
        Anr $anr,
        string $scenarioUuid,
        string $type,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        $this->assertLinkReference($anr, $type, $data);

        return $this->stories->createRelation(
            (int) $anr->getId(),
            $scenarioUuid,
            $type,
            $this->actorId(),
            $data,
            $revision,
            $correlationId
        );
    }

    public function deleteRelation(
        Anr $anr,
        string $scenarioUuid,
        string $type,
        string $uuid,
        int $revision,
        string $correlationId
    ): bool {
        return $this->stories->deleteRelation(
            (int) $anr->getId(),
            $scenarioUuid,
            $type,
            $uuid,
            $this->actorId(),
            $revision,
            $correlationId
        );
    }

    /** @return array<string, mixed> */
    public function completeness(Anr $anr): array
    {
        return $this->stories->completeness((int) $anr->getId());
    }

    /** @param array<string, mixed> $data */
    private function assertLinkReference(Anr $anr, string $type, array $data): void
    {
        $anrId = (int) $anr->getId();
        if ($type === 'subject-link' && !$this->references->hasSubject(
            $anrId,
            (string) $data['subjectType'],
            (string) $data['subjectReferenceUuid']
        )) {
            throw new ScenarioRiskStoryException(
                'unavailable_reference',
                'The selected subject is unavailable in this analysis.'
            );
        }
        if ($type === 'control-link' && !$this->references->hasControl(
            $anrId,
            (string) $data['controlReferenceUuid']
        )) {
            throw new ScenarioRiskStoryException(
                'unavailable_reference',
                'The selected control is unavailable in this analysis.'
            );
        }
    }

    private function actorId(): int
    {
        $user = $this->users->getConnectedUser();
        if (!$user instanceof User) {
            throw new ActionForbiddenException('Scenario analysis access is forbidden.');
        }

        return (int) $user->getId();
    }
}
