<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Exception\ScenarioRiskScenarioException;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioReferenceTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioRiskScenarioTable;

/** Coordinates authorised Scenario risk-scenario writes without exposing DBAL to controllers. */
final class ScenarioRiskScenarioService
{
    public function __construct(
        private ScenarioRiskScenarioTable $riskScenarios,
        private ScenarioAnalysisTable $analyses,
        private ScenarioReferenceTable $references,
        private ConnectedUserService $users
    ) {
    }

    /** @return array<string, mixed> */
    public function list(Anr $anr, int $page, int $pageSize): array
    {
        return $this->riskScenarios->list((int) $anr->getId(), $page, $pageSize);
    }

    /** @return array<string, mixed>|null */
    public function get(Anr $anr, string $uuid): ?array
    {
        return $this->riskScenarios->detail((int) $anr->getId(), $uuid);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(Anr $anr, array $data, string $correlationId): array
    {
        $analysisId = $this->analyses->idByAnr((int) $anr->getId());
        if ($analysisId === null) {
            throw new ActionForbiddenException('Scenario analysis was not found.');
        }

        return $this->riskScenarios->createScenario(
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
        return $this->riskScenarios->updateScenario(
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
        return $this->riskScenarios->deleteScenario(
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
        if (isset($data['legacyRiskSourceId'])) {
            if (!$this->references->hasRiskSource((int) $anr->getId(), (string) $data['legacyRiskSourceId'])) {
                throw new ScenarioRiskScenarioException(
                    'unavailable_reference',
                    'The selected risk source is unavailable in this analysis.'
                );
            }
            $snapshot = $this->references->snapshot($anr, 'risk-source', (string) $data['legacyRiskSourceId']);
            if ($snapshot === null) {
                throw new ScenarioRiskScenarioException(
                    'unavailable_reference',
                    'The selected risk source is unavailable in this analysis.'
                );
            }
            $data['referenceSnapshot'] = $snapshot;
        }

        return $this->riskScenarios->saveSource(
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
        if (isset($data['threatReferenceUuid'])) {
            if (!$this->references->hasThreat($anrId, (string) $data['threatReferenceUuid'])) {
                throw new ScenarioRiskScenarioException('unavailable_reference', 'The selected threat is unavailable.');
            }
            $snapshot = $this->references->snapshot($anr, 'threat', (string) $data['threatReferenceUuid']);
            if ($snapshot === null) {
                throw new ScenarioRiskScenarioException('unavailable_reference', 'The selected threat is unavailable.');
            }
            $data['threatReferenceSnapshot'] = $snapshot;
        }
        if (isset($data['vulnerabilityReferenceUuid'])) {
            if (!$this->references->hasVulnerability($anrId, (string) $data['vulnerabilityReferenceUuid'])) {
                throw new ScenarioRiskScenarioException(
                    'unavailable_reference',
                    'The selected vulnerability is unavailable.'
                );
            }
            $snapshot = $this->references->snapshot(
                $anr,
                'vulnerability',
                (string) $data['vulnerabilityReferenceUuid']
            );
            if ($snapshot === null) {
                throw new ScenarioRiskScenarioException(
                    'unavailable_reference',
                    'The selected vulnerability is unavailable.'
                );
            }
            $data['vulnerabilityReferenceSnapshot'] = $snapshot;
        }

        return $this->riskScenarios->saveCause(
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
        return $this->riskScenarios->createNode(
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
        return $this->riskScenarios->updateNode(
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
        return $this->riskScenarios->deleteNode(
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

        return $this->riskScenarios->createRelation(
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
        return $this->riskScenarios->deleteRelation(
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
        return $this->riskScenarios->completeness((int) $anr->getId());
    }

    /** @param array<string, mixed> $data */
    private function assertLinkReference(Anr $anr, string $type, array &$data): void
    {
        $anrId = (int) $anr->getId();
        if ($type === 'subject-link' && !$this->references->hasSubject(
            $anrId,
            (string) $data['subjectType'],
            (string) $data['subjectReferenceUuid']
        )) {
            throw new ScenarioRiskScenarioException(
                'unavailable_reference',
                'The selected subject is unavailable in this analysis.'
            );
        }
        if ($type === 'subject-link') {
            $snapshot = $this->references->snapshot(
                $anr,
                (string) $data['subjectType'],
                (string) $data['subjectReferenceUuid']
            );
            if ($snapshot === null) {
                throw new ScenarioRiskScenarioException(
                    'unavailable_reference',
                    'The selected subject is unavailable in this analysis.'
                );
            }
            $data['referenceSnapshot'] = $snapshot;
        }
        if ($type === 'control-link' && !$this->references->hasControl(
            $anrId,
            (string) $data['controlReferenceUuid']
        )) {
            throw new ScenarioRiskScenarioException(
                'unavailable_reference',
                'The selected control is unavailable in this analysis.'
            );
        }
        if ($type === 'control-link') {
            $snapshot = $this->references->snapshot(
                $anr,
                'control',
                (string) $data['controlReferenceUuid']
            );
            if ($snapshot === null) {
                throw new ScenarioRiskScenarioException(
                    'unavailable_reference',
                    'The selected control is unavailable in this analysis.'
                );
            }
            $data['referenceSnapshot'] = $snapshot;
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
