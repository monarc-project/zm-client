<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Ramsey\Uuid\Uuid;

/** Stores Context Establishment evidence without creating graph events. */
final class ScenarioTemplateEventEvaluationTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @param array<string, mixed> $snapshot @return array<int, array<string, mixed>> */
    public function grouped(int $anrId, int $snapshotId, array $snapshot): array
    {
        $evaluations = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_template_event_evaluations WHERE anr_id = ? AND snapshot_id = ?',
            [$anrId, $snapshotId]
        );
        $byEventRecord = [];
        foreach ($evaluations as $evaluation) {
            $byEventRecord[$evaluation['event_record_uuid']] = $evaluation;
        }
        $groups = [];
        foreach ($snapshot['records'] as $record) {
            if ($record['type'] !== 'template-risk-scenario-model') {
                continue;
            }
            $content = $record['content'];
            $groups[$record['sourceUuid']] = [
                'modelRecordUuid' => $record['uuid'],
                'modelStableUuid' => $record['sourceUuid'],
                'title' => $content['title'] ?? 'Untitled strategic risk scenario',
                'description' => $content['description'] ?? null,
                'position' => $record['content']['position'] ?? 0,
                'sourceRevision' => (int) ($record['revision'] ?? 1),
                'events' => [],
            ];
        }
        foreach ($snapshot['records'] as $record) {
            if ($record['type'] !== 'template-model-event') {
                continue;
            }
            $content = $record['content'];
            $modelUuid = $content['modelStableUuid'] ?? null;
            if (!is_string($modelUuid) || !isset($groups[$modelUuid])) {
                continue;
            }
            $evaluation = $byEventRecord[$record['uuid']] ?? null;
            $groups[$modelUuid]['events'][] = [
                'eventRecordUuid' => $record['uuid'],
                'eventStableUuid' => $record['sourceUuid'],
                'eventVersion' => $record['sourceVersion'],
                'eventTitle' => $content['event_title_snapshot'] ?? 'Untitled available event',
                'guidance' => $content['guidance'] ?? null,
                'state' => $evaluation['state'] ?? 'not_evaluated',
                'comment' => $evaluation['comment'] ?? null,
                'revision' => $evaluation === null ? 0 : (int) $evaluation['revision'],
                'actorId' => ($evaluation['evaluated_by'] ?? null) === null ? null : (int) $evaluation['evaluated_by'],
                'evaluatedAt' => $this->apiDate($evaluation['evaluated_at'] ?? null),
                '_position' => (int) ($content['position'] ?? 0),
            ];
        }
        $groups = array_values($groups);
        usort($groups, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);
        foreach ($groups as &$group) {
            usort(
                $group['events'],
                static fn (array $left, array $right): int => $left['_position'] <=> $right['_position']
            );
            foreach ($group['events'] as &$event) {
                unset($event['_position']);
            }
            unset($event);
        }
        unset($group);

        return $groups;
    }

    public function containsEventRecord(int $anrId, int $snapshotId, string $eventRecordUuid): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT event.content_json FROM scenario_template_instance_records event '
            . 'WHERE event.anr_id = ? AND event.snapshot_id = ? AND event.uuid = ? '
            . "AND event.record_type = 'template-model-event'",
            [$anrId, $snapshotId, $eventRecordUuid]
        );
        if ($row === false) {
            return false;
        }
        $content = json_decode($row['content_json'], true, 512, JSON_THROW_ON_ERROR);
        $modelStableUuid = $content['modelStableUuid'] ?? null;
        if (!is_string($modelStableUuid)) {
            return false;
        }

        return $this->connection->fetchOne(
            'SELECT id FROM scenario_template_instance_records '
            . 'WHERE anr_id = ? AND snapshot_id = ? AND source_uuid = ? '
            . "AND record_type = 'template-risk-scenario-model'",
            [$anrId, $snapshotId, $modelStableUuid]
        ) !== false;
    }

    /** @return array<string, mixed>|null */
    public function save(
        int $anrId,
        int $snapshotId,
        string $eventRecordUuid,
        string $state,
        ?string $comment,
        int $revision,
        int $actorId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $snapshotId,
            $eventRecordUuid,
            $state,
            $comment,
            $revision,
            $actorId
        ): ?array {
            $existing = $this->connection->fetchAssociative(
                'SELECT id, uuid, revision FROM scenario_template_event_evaluations '
                . 'WHERE anr_id = ? AND snapshot_id = ? AND event_record_uuid = ? FOR UPDATE',
                [$anrId, $snapshotId, $eventRecordUuid]
            );
            $now = gmdate('Y-m-d H:i:s');
            if ($existing === false) {
                if ($revision !== 0) {
                    return null;
                }
                $uuid = Uuid::uuid4()->toString();
                $this->connection->insert('scenario_template_event_evaluations', [
                    'uuid' => $uuid,
                    'anr_id' => $anrId,
                    'snapshot_id' => $snapshotId,
                    'event_record_uuid' => $eventRecordUuid,
                    'state' => $state,
                    'comment' => $comment,
                    'evaluated_by' => $actorId,
                    'evaluated_at' => $now,
                ]);

                return ['uuid' => $uuid, 'revision' => 1, 'state' => $state, 'comment' => $comment];
            }
            if ((int) $existing['revision'] !== $revision) {
                return null;
            }
            $nextRevision = $revision + 1;
            if ($this->connection->update(
                'scenario_template_event_evaluations',
                [
                    'state' => $state,
                    'comment' => $comment,
                    'revision' => $nextRevision,
                    'evaluated_by' => $actorId,
                    'evaluated_at' => $now,
                ],
                ['id' => (int) $existing['id'], 'revision' => $revision]
            ) !== 1) {
                return null;
            }

            return [
                'uuid' => $existing['uuid'],
                'revision' => $nextRevision,
                'state' => $state,
                'comment' => $comment,
            ];
        });
    }

    private function apiDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s\\Z');
    }
}
