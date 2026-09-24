<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Ramsey\Uuid\Uuid;

/** Client-side immutable catalog snapshots and their copy-on-write records. */
final class ScenarioTemplateSnapshotTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @return array<string, mixed>|null */
    public function find(int $anrId, string $templateUuid, int $templateVersion): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scenario_template_snapshots WHERE anr_id = ? '
            . 'AND catalog_template_uuid = ? AND catalog_template_version = ?',
            [$anrId, $templateUuid, $templateVersion]
        );

        return $row === false ? null : $this->project($row);
    }

    /** @return array<string, mixed>|null */
    public function findLatestByAnr(int $anrId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scenario_template_snapshots WHERE anr_id = ? ORDER BY id DESC LIMIT 1',
            [$anrId]
        );

        return $row === false ? null : $this->project($row);
    }

    /** @return array<string, mixed>|null */
    public function findByUuid(int $anrId, string $snapshotUuid): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scenario_template_snapshots WHERE anr_id = ? AND uuid = ?',
            [$anrId, $snapshotUuid]
        );

        return $row === false ? null : $this->project($row);
    }

    public function findIdByUuid(int $anrId, string $snapshotUuid): ?int
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM scenario_template_snapshots WHERE anr_id = ? AND uuid = ?',
            [$anrId, $snapshotUuid]
        );

        return $id === false ? null : (int) $id;
    }

    /** @template T @param callable():T $callback @return T */
    public function transactional(callable $callback)
    {
        return $this->connection->transactional($callback);
    }

    /** @param array<string, mixed> $catalogSnapshot @return array<string, mixed> */
    public function create(
        int $anrId,
        int $analysisId,
        int $actorId,
        array $catalogSnapshot
    ): array {
        $payload = $this->normalise($catalogSnapshot);
        $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $uuid = Uuid::uuid4()->toString();
        $this->connection->insert('scenario_template_snapshots', [
            'uuid' => $uuid,
            'anr_id' => $anrId,
            'scenario_analysis_id' => $analysisId,
            'catalog_template_uuid' => $catalogSnapshot['stable_uuid'],
            'catalog_template_version' => (int) $catalogSnapshot['version'],
            'catalog_release_uuid' => $catalogSnapshot['release']['uuid'] ?? null,
            'catalog_release_checksum' => $checksum,
            'catalog_provenance' => $catalogSnapshot['provenance'],
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_by' => $actorId,
        ]);
        $snapshotId = (int) $this->connection->lastInsertId();
        $this->insertRecords($anrId, $snapshotId, $payload);

        return $this->find($anrId, $catalogSnapshot['stable_uuid'], (int) $catalogSnapshot['version']);
    }

    /** @return array<string, mixed>|null */
    public function saveOverride(
        int $anrId,
        int $actorId,
        int $snapshotId,
        string $recordUuid,
        string $fieldName,
        mixed $value,
        int $revision
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $actorId,
            $snapshotId,
            $recordUuid,
            $fieldName,
            $value,
            $revision
        ): ?array {
            $recordId = $this->connection->fetchOne(
                'SELECT id FROM scenario_template_instance_records WHERE anr_id = ? '
                . 'AND snapshot_id = ? AND uuid = ?',
                [$anrId, $snapshotId, $recordUuid]
            );
            if ($recordId === false) {
                return null;
            }
            $existing = $this->connection->fetchAssociative(
                'SELECT id, uuid, revision FROM scenario_template_local_overrides '
                . 'WHERE snapshot_id = ? AND instance_record_uuid = ? AND field_name = ? FOR UPDATE',
                [$snapshotId, $recordUuid, $fieldName]
            );
            if ($existing === false) {
                if ($revision !== 0) {
                    return null;
                }
                $uuid = Uuid::uuid4()->toString();
                $this->connection->insert('scenario_template_local_overrides', [
                    'uuid' => $uuid,
                    'anr_id' => $anrId,
                    'snapshot_id' => $snapshotId,
                    'instance_record_uuid' => $recordUuid,
                    'field_name' => $fieldName,
                    'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
                    'created_by' => $actorId,
                ]);

                return ['uuid' => $uuid, 'revision' => 1];
            }
            if ((int) $existing['revision'] !== $revision) {
                return null;
            }
            $nextRevision = $revision + 1;
            if ($this->connection->update(
                'scenario_template_local_overrides',
                [
                    'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
                    'revision' => $nextRevision,
                    'created_by' => $actorId,
                ],
                ['id' => (int) $existing['id'], 'revision' => $revision]
            ) !== 1) {
                return null;
            }

            return ['uuid' => $existing['uuid'], 'revision' => $nextRevision];
        });
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function project(array $snapshot): array
    {
        $records = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_template_instance_records WHERE anr_id = ? '
            . 'AND snapshot_id = ? ORDER BY position, id',
            [(int) $snapshot['anr_id'], (int) $snapshot['id']]
        );
        $overrides = $this->connection->fetchAllAssociative(
            'SELECT instance_record_uuid, field_name, value_json, revision FROM scenario_template_local_overrides '
            . 'WHERE anr_id = ? AND snapshot_id = ?',
            [(int) $snapshot['anr_id'], (int) $snapshot['id']]
        );
        $local = [];
        foreach ($overrides as $override) {
            $local[$override['instance_record_uuid']][$override['field_name']] = [
                'value' => json_decode($override['value_json'], true, 512, JSON_THROW_ON_ERROR),
                'revision' => (int) $override['revision'],
            ];
        }
        return [
            'uuid' => $snapshot['uuid'],
            'catalogTemplateUuid' => $snapshot['catalog_template_uuid'],
            'catalogTemplateVersion' => (int) $snapshot['catalog_template_version'],
            'catalogReleaseChecksum' => $snapshot['catalog_release_checksum'],
            'catalogProvenance' => $snapshot['catalog_provenance'],
            'payload' => json_decode($snapshot['payload_json'], true, 512, JSON_THROW_ON_ERROR),
            'records' => array_map(static fn (array $record): array => [
                'uuid' => $record['uuid'],
                'type' => $record['record_type'],
                'sourceUuid' => $record['source_uuid'],
                'sourceVersion' => $record['source_version'] === null
                    ? null
                    : (int) $record['source_version'],
                'content' => json_decode($record['content_json'], true, 512, JSON_THROW_ON_ERROR),
                'localOverrides' => $local[$record['uuid']] ?? [],
            ], $records),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function insertRecords(int $anrId, int $snapshotId, array $payload): void
    {
        $this->insertRecord(
            $anrId,
            $snapshotId,
            'template',
            $payload['stable_uuid'],
            (int) $payload['version'],
            0,
            $payload
        );
        foreach ($payload['events'] as $event) {
            $this->insertRecord(
                $anrId,
                $snapshotId,
                'event',
                $event['stable_uuid'],
                (int) $event['version'],
                (int) $event['position'],
                $event
            );
        }
        foreach ($payload['suggestions'] as $position => $suggestion) {
            $this->insertRecord($anrId, $snapshotId, 'suggestion', null, null, $position + 1, $suggestion);
        }
    }

    private function insertRecord(
        int $anrId,
        int $snapshotId,
        string $type,
        ?string $sourceUuid,
        ?int $sourceVersion,
        int $position,
        array $content
    ): void {
        $this->connection->insert('scenario_template_instance_records', [
            'uuid' => Uuid::uuid4()->toString(),
            'anr_id' => $anrId,
            'snapshot_id' => $snapshotId,
            'record_type' => $type,
            'source_uuid' => $sourceUuid,
            'source_version' => $sourceVersion,
            'position' => $position,
            'content_json' => json_encode($content, JSON_THROW_ON_ERROR),
        ]);
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function normalise(array $value): array
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->normalise($item);
            }
        }
        unset($item);
        return $value;
    }
}
