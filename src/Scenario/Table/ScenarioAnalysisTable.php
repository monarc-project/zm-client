<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Scenario\Contract\ScenarioAnalysisLifecycle;
use Ramsey\Uuid\Uuid;

final class ScenarioAnalysisTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    public function findByAnr(int $anrId): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM scenario_analyses WHERE anr_id = ?', [$anrId]);

        return $row ? $this->project($row) : null;
    }

    public function idByAnr(int $anrId): ?int
    {
        $id = $this->connection->fetchOne('SELECT id FROM scenario_analyses WHERE anr_id = ?', [$anrId]);

        return $id === false ? null : (int) $id;
    }

    /** @param array<string, mixed> $patch */
    public function auditTemplateSelection(
        int $anrId,
        int $actorId,
        string $correlationId,
        string $targetUuid,
        array $patch
    ): void {
        $this->audit($anrId, $actorId, $correlationId, $targetUuid, 'template_selected', $patch);
    }

    /** @param array<string, mixed> $patch */
    public function auditTemplateOverride(
        int $anrId,
        int $actorId,
        string $correlationId,
        string $targetUuid,
        array $patch
    ): void {
        $this->audit($anrId, $actorId, $correlationId, $targetUuid, 'template_override_updated', $patch);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(int $anrId, int $actorId, array $data, string $correlationId): array
    {
        return $this->connection->transactional(function () use (
            $anrId,
            $actorId,
            $data,
            $correlationId
        ): array {
            $uuid = Uuid::uuid4()->toString();
            $row = [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'lifecycle' => 'draft',
                'owner_id' => $actorId,
                'language_code' => $data['languageCode'],
                'next_review_at' => $this->databaseDate($data['nextReviewAt'] ?? null),
                'source_anr_id' => $data['sourceAnrId'] ?? null,
            ];
            $this->connection->insert('scenario_analyses', $row);
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'created', $row);

            return $this->findByAnr($anrId);
        });
    }

    public function update(
        int $anrId,
        string $uuid,
        int $actorId,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        $current = $this->findByAnr($anrId);
        if ($current === null
            || $current['uuid'] !== $uuid
            || (int) $current['revision'] !== $revision
            || (isset($data['lifecycle'])
                && !$this->isValidTransition($current['lifecycle'], $data['lifecycle']))
        ) {
            return null;
        }
        $updates = [];
        if (isset($data['lifecycle'])) {
            $updates['lifecycle'] = $data['lifecycle'];
        }
        if (array_key_exists('nextReviewAt', $data)) {
            $updates['next_review_at'] = $this->databaseDate($data['nextReviewAt'] ?: null);
        }
        if ($updates === []) {
            return $current;
        }
        $updates['revision'] = $revision + 1;

        return $this->connection->transactional(function () use (
            $anrId,
            $actorId,
            $correlationId,
            $current,
            $data,
            $revision,
            $updates
        ): ?array {
            if ($this->connection->update(
                'scenario_analyses',
                $updates,
                ['anr_id' => $anrId, 'revision' => $revision]
            ) !== 1) {
                return null;
            }
            $this->audit(
                $anrId,
                $actorId,
                $correlationId,
                $current['uuid'],
                'updated',
                ['before' => $current, 'after' => $updates],
                $data['rationale'] ?? null
            );

            return $this->findByAnr($anrId);
        });
    }

    private function audit(
        int $anrId,
        int $actorId,
        string $correlationId,
        string $targetUuid,
        string $action,
        array $patch,
        ?string $rationale = null
    ): void {
        $this->connection->insert('scenario_audit_events', [
            'uuid' => Uuid::uuid4()->toString(),
            'anr_id' => $anrId,
            'actor_id' => $actorId,
            'correlation_id' => substr($correlationId, 0, 128),
            'target_uuid' => $targetUuid,
            'action' => $action,
            'change_patch' => json_encode($patch, JSON_THROW_ON_ERROR),
            'rationale' => $rationale,
        ]);
    }

    private function isValidTransition(string $from, string $to): bool
    {
        return ScenarioAnalysisLifecycle::canTransition($from, $to);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function project(array $row): array
    {
        return [
            'uuid' => $row['uuid'],
            'anrId' => (int) $row['anr_id'],
            'lifecycle' => $row['lifecycle'],
            'ownerId' => $row['owner_id'] === null ? null : (int) $row['owner_id'],
            'languageCode' => $row['language_code'],
            'nextReviewAt' => $this->apiDate($row['next_review_at']),
            'sourceAnrId' => $row['source_anr_id'] === null ? null : (int) $row['source_anr_id'],
            'revision' => (int) $row['revision'],
            'createdAt' => $this->apiDate($row['created_at']),
            'updatedAt' => $this->apiDate($row['updated_at']),
        ];
    }

    private function databaseDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return (new \DateTimeImmutable($date))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    private function apiDate(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\\TH:i:s\\Z');
    }
}
