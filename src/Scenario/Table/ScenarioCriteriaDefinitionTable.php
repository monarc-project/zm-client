<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Ramsey\Uuid\Uuid;

/** Persists Scenario-only criteria drafts and the immutable source evidence used when locking them. */
final class ScenarioCriteriaDefinitionTable
{
    private const CLIENT_SCOPE_ID = 1;

    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @return array<string, mixed>|null */
    public function draft(int $anrId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT d.* FROM scenario_criteria_drafts d '
            . 'INNER JOIN scenario_analyses a ON a.id = d.scenario_analysis_id WHERE a.anr_id = ?',
            [$anrId]
        );
        if ($row === false) {
            return null;
        }

        $dimensions = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_criteria_draft_dimensions WHERE draft_id = ? ORDER BY id ASC',
            [(int) $row['id']]
        );
        $levels = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_criteria_draft_levels WHERE draft_id = ? '
            . 'ORDER BY dimension_id ASC, source_scale_index ASC, level_value ASC',
            [(int) $row['id']]
        );

        return $this->projectDraft($row, $dimensions, $levels);
    }

    /** @return array<string, mixed>|null */
    public function locked(int $anrId): ?array
    {
        $lock = $this->connection->fetchAssociative(
            'SELECT l.*, p.uuid AS profile_uuid, p.identifier, p.title, p.revision AS profile_revision, '
            . 'v.uuid AS version_uuid, v.version, v.payload, a.revision AS analysis_revision '
            . 'FROM scenario_criteria_locks l '
            . 'INNER JOIN scenario_analyses a ON a.id = l.scenario_analysis_id '
            . 'INNER JOIN scenario_criteria_profile_versions v ON v.id = l.profile_version_id '
            . 'INNER JOIN scenario_criteria_profiles p ON p.id = v.profile_id '
            . 'WHERE a.anr_id = ?',
            [$anrId]
        );
        if ($lock === false) {
            return null;
        }
        $sources = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_criteria_lock_sources WHERE lock_id = ? '
            . 'ORDER BY kind ASC, dimension_key ASC, source_scale_index ASC',
            [(int) $lock['id']]
        );
        $audit = $this->connection->fetchAllAssociative(
            'SELECT action, target_uuid, created_at FROM scenario_audit_events WHERE anr_id = ? '
            . "AND action IN ('scenario_criteria_profile_version_created', 'scenario_criteria_locked') "
            . 'ORDER BY created_at ASC, id ASC',
            [$anrId]
        );

        return [
            'uuid' => $lock['uuid'],
            'lockedAt' => $this->apiDate((string) $lock['locked_at']),
            'lockedBy' => (int) $lock['locked_by'],
            'analysisRevision' => (int) $lock['analysis_revision'],
            'profile' => [
                'uuid' => $lock['profile_uuid'],
                'versionUuid' => $lock['version_uuid'],
                'identifier' => $lock['identifier'],
                'title' => $lock['title'],
                'version' => (int) $lock['version'],
                'revision' => (int) $lock['profile_revision'],
                'isLocked' => true,
                'payload' => json_decode((string) $lock['payload'], true, 512, JSON_THROW_ON_ERROR),
            ],
            'sourceSnapshot' => [
                'sourceAnrId' => (int) $lock['source_anr_id'],
                'languageCode' => $lock['source_language_code'],
                'readAt' => $this->apiDate((string) $lock['source_read_at']),
                'levels' => array_map([$this, 'projectLockSource'], $sources),
            ],
            'auditEvents' => array_map(static fn (array $event): array => [
                'action' => $event['action'],
                'targetUuid' => $event['target_uuid'],
                'createdAt' => (new \DateTimeImmutable($event['created_at'], new \DateTimeZone('UTC')))
                    ->format('Y-m-d\\TH:i:s\\Z'),
            ], $audit),
        ];
    }

    /** @param array<string, mixed> $source @return array<string, mixed>|null */
    public function createDraft(
        int $anrId,
        array $source,
        int $expectedAnalysisRevision,
        int $actorId,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $source,
            $expectedAnalysisRevision,
            $actorId,
            $correlationId
        ): ?array {
            $analysis = $this->analysisForUpdate($anrId);
            if ($analysis === null || $analysis['criteria_locked_at'] !== null
                || (int) $analysis['revision'] !== $expectedAnalysisRevision) {
                return null;
            }
            $existing = $this->connection->fetchOne(
                'SELECT id FROM scenario_criteria_drafts WHERE scenario_analysis_id = ? FOR UPDATE',
                [(int) $analysis['id']]
            );
            if ($existing !== false) {
                return null;
            }
            $draftUuid = Uuid::uuid4()->toString();
            $this->connection->insert('scenario_criteria_drafts', [
                'uuid' => $draftUuid,
                'scenario_analysis_id' => (int) $analysis['id'],
                'source_anr_id' => $anrId,
                'source_language_code' => $source['languageCode'],
                'source_read_at' => $this->databaseDate($source['readAt']),
                'identifier' => $source['identifier'],
                'title' => $source['title'],
                'aggregation_policy' => $source['aggregationPolicy'],
                'bands' => json_encode($source['bands'], JSON_THROW_ON_ERROR),
                'acceptance_rules' => json_encode($source['acceptanceRules'], JSON_THROW_ON_ERROR),
                'created_by' => $actorId,
            ]);
            $draftId = (int) $this->connection->lastInsertId();
            $dimensions = [];
            foreach ($source['dimensions'] as $dimension) {
                $dimensionUuid = Uuid::uuid4()->toString();
                $this->connection->insert('scenario_criteria_draft_dimensions', [
                    'uuid' => $dimensionUuid,
                    'draft_id' => $draftId,
                    'dimension_key' => $dimension['key'],
                    'source_scale_id' => $dimension['source']['scaleId'],
                    'source_scale_type_id' => $dimension['source']['scaleTypeId'],
                    'source_label' => $dimension['source']['label'],
                    'label' => $dimension['label'],
                    'is_enabled' => 1,
                ]);
                $dimensions[$dimension['key']] = (int) $this->connection->lastInsertId();
                foreach ($dimension['levels'] as $level) {
                    $this->insertLevel($draftId, $dimensions[$dimension['key']], $level);
                }
            }
            foreach ($source['likelihoodLevels'] as $level) {
                $this->insertLevel($draftId, null, $level);
            }
            $this->touchAnalysis(
                $analysis,
                $expectedAnalysisRevision,
                $actorId,
                $correlationId,
                'scenario_criteria_draft_created',
                [
                'draftUuid' => $draftUuid,
                'sourceAnrId' => $anrId,
                'sourceReadAt' => $source['readAt'],
                ]
            );

            return $this->draft($anrId) + ['analysisRevision' => $expectedAnalysisRevision + 1];
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function updateDraft(
        int $anrId,
        array $data,
        int $expectedDraftRevision,
        int $actorId,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $data,
            $expectedDraftRevision,
            $actorId,
            $correlationId
        ): ?array {
            $draft = $this->draftForUpdate($anrId);
            if ($draft === null || (int) $draft['revision'] !== $expectedDraftRevision) {
                return null;
            }
            if ($this->criteriaLocked((int) $draft['scenario_analysis_id'])) {
                return null;
            }
            $this->connection->update('scenario_criteria_drafts', [
                'title' => $data['title'],
                'aggregation_policy' => $data['aggregationPolicy'],
                'bands' => json_encode($data['bands'], JSON_THROW_ON_ERROR),
                'acceptance_rules' => json_encode($data['acceptanceRules'], JSON_THROW_ON_ERROR),
                'revision' => $expectedDraftRevision + 1,
            ], ['id' => $draft['id'], 'revision' => $expectedDraftRevision]);
            $this->removeMissingLevels((int) $draft['id'], null, $data['likelihoodLevels']);
            $this->removeMissingDimensions((int) $draft['id'], $data['dimensions']);
            foreach ($data['likelihoodLevels'] as $level) {
                if ($this->updateLevel((int) $draft['id'], null, $level) === null) {
                    return null;
                }
            }
            foreach ($data['dimensions'] as $dimension) {
                $dimensionId = $this->updateDimension((int) $draft['id'], $dimension);
                if ($dimensionId === null) {
                    return null;
                }
                $this->removeMissingLevels((int) $draft['id'], $dimensionId, $dimension['levels']);
                foreach ($dimension['levels'] as $level) {
                    if ($this->updateLevel((int) $draft['id'], $dimensionId, $level) === null) {
                        return null;
                    }
                }
            }
            $this->audit($anrId, $actorId, $correlationId, $draft['uuid'], 'scenario_criteria_draft_updated', [
                'draftRevision' => $expectedDraftRevision + 1,
                'changedFields' => ['title', 'labels', 'dimensions', 'bands', 'aggregationPolicy', 'acceptanceRules'],
            ]);

            return $this->draft($anrId);
        });
    }

    /** @param array<string, mixed> $profile @return array<string, mixed>|null */
    public function lockDraft(
        int $anrId,
        int $expectedDraftRevision,
        array $profile,
        int $actorId,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $expectedDraftRevision,
            $profile,
            $actorId,
            $correlationId
        ): ?array {
            $analysis = $this->analysisForUpdate($anrId);
            $draft = $this->draftForUpdate($anrId);
            if ($analysis === null || $draft === null || $analysis['criteria_locked_at'] !== null
                || (int) $draft['revision'] !== $expectedDraftRevision) {
                return null;
            }
            $profileUuid = Uuid::uuid4()->toString();
            $this->connection->insert('scenario_criteria_profiles', [
                'uuid' => $profileUuid,
                'client_id' => self::CLIENT_SCOPE_ID,
                'identifier' => $profile['identifier'],
                'title' => $profile['title'],
                'revision' => 1,
            ]);
            $profileId = (int) $this->connection->lastInsertId();
            $versionUuid = Uuid::uuid4()->toString();
            $this->connection->insert('scenario_criteria_profile_versions', [
                'uuid' => $versionUuid,
                'profile_id' => $profileId,
                'version' => 1,
                'payload' => json_encode($profile['payload'], JSON_THROW_ON_ERROR),
                'created_by' => $actorId,
            ]);
            $profileVersionId = (int) $this->connection->lastInsertId();
            $profileSnapshot = [
                '_id' => $profileVersionId,
                'uuid' => $profileUuid,
                'versionUuid' => $versionUuid,
                'identifier' => $profile['identifier'],
                'title' => $profile['title'],
                'version' => 1,
                'revision' => 1,
                'isLocked' => true,
                'payload' => $profile['payload'],
            ];
            $this->connection->executeStatement(
                'INSERT INTO scenario_analysis_criteria_profiles '
                . '(scenario_analysis_id, profile_version_id, profile_snapshot, revision) VALUES (?, ?, ?, 1) '
                . 'ON DUPLICATE KEY UPDATE profile_version_id = VALUES(profile_version_id), '
                . 'profile_snapshot = VALUES(profile_snapshot), revision = revision + 1',
                [
                    (int) $analysis['id'],
                    $profileVersionId,
                    json_encode($profileSnapshot, JSON_THROW_ON_ERROR),
                ]
            );
            $lockUuid = Uuid::uuid4()->toString();
            $this->connection->insert('scenario_criteria_locks', [
                'uuid' => $lockUuid,
                'scenario_analysis_id' => (int) $analysis['id'],
                'profile_version_id' => $profileVersionId,
                'source_anr_id' => (int) $draft['source_anr_id'],
                'source_language_code' => $draft['source_language_code'],
                'source_read_at' => $draft['source_read_at'],
                'locked_by' => $actorId,
            ]);
            $lockId = (int) $this->connection->lastInsertId();
            foreach ($this->draftSourceRows((int) $draft['id']) as $source) {
                $this->connection->insert('scenario_criteria_lock_sources', [
                    'lock_id' => $lockId,
                    'kind' => $source['dimension_key'] === null ? 'likelihood' : 'consequence',
                    'dimension_key' => $source['dimension_key'],
                    'source_scale_id' => $source['source_scale_id'],
                    'source_scale_type_id' => $source['source_scale_type_id'],
                    'source_comment_id' => $source['source_comment_id'],
                    'source_scale_index' => $source['source_scale_index'],
                    'source_scale_value' => $source['source_scale_value'],
                    'source_min' => $source['source_min'],
                    'source_max' => $source['source_max'],
                    'source_label' => $source['source_label'],
                    'source_comment' => $source['source_comment'],
                ]);
            }
            $locked = $this->connection->update('scenario_analyses', [
                'criteria_locked_at' => gmdate('Y-m-d H:i:s'),
                'criteria_locked_by' => $actorId,
                'revision' => (int) $analysis['revision'] + 1,
            ], ['id' => $analysis['id'], 'criteria_locked_at' => null]);
            if ($locked !== 1) {
                return null;
            }
            $this->audit($anrId, $actorId, $correlationId, $versionUuid, 'scenario_criteria_profile_version_created', [
                'identifier' => $profile['identifier'],
                'version' => 1,
            ]);
            $this->audit($anrId, $actorId, $correlationId, $analysis['uuid'], 'scenario_criteria_locked', [
                'lockUuid' => $lockUuid,
                'profileVersionUuid' => $versionUuid,
                'sourceAnrId' => (int) $draft['source_anr_id'],
                'sourceReadAt' => $this->apiDate($draft['source_read_at']),
            ]);

            return $this->locked($anrId);
        });
    }

    private function insertLevel(int $draftId, ?int $dimensionId, array $level): void
    {
        $source = $level['source'];
        $sourceCommentId = (int) ($source['commentId'] ?? 0);
        if ($sourceCommentId < 1 || $this->connection->fetchOne(
            'SELECT id FROM scenario_criteria_draft_levels WHERE draft_id = ? AND source_comment_id = ?',
            [$draftId, $sourceCommentId]
        ) !== false) {
            $sourceCommentId = (int) $this->connection->fetchOne(
                'SELECT COALESCE(MAX(source_comment_id), 0) + 1 FROM scenario_criteria_draft_levels WHERE draft_id = ?',
                [$draftId]
            );
        }
        $this->connection->insert('scenario_criteria_draft_levels', [
            'uuid' => Uuid::uuid4()->toString(),
            'draft_id' => $draftId,
            'dimension_id' => $dimensionId,
            'source_scale_id' => $source['scaleId'],
            'source_scale_type_id' => $source['scaleTypeId'],
            'source_comment_id' => $sourceCommentId,
            'source_scale_index' => $source['scaleIndex'],
            'source_scale_value' => $source['scaleValue'],
            'source_min' => $source['min'],
            'source_max' => $source['max'],
            'source_label' => $source['label'],
            'source_comment' => $source['comment'],
            'level_value' => $level['value'],
            'label' => $level['label'],
            'description' => $level['description'],
        ]);
    }

    private function updateLevel(int $draftId, ?int $dimensionId, array $level): ?int
    {
        $existing = $this->connection->fetchAssociative(
            'SELECT id, source_label FROM scenario_criteria_draft_levels WHERE uuid = ? AND draft_id = ? FOR UPDATE',
            [$level['uuid'], $draftId]
        );
        if ($existing === false) {
            if ($dimensionId === null && !array_key_exists('source', $level)) {
                return null;
            }
            $this->insertLevel($draftId, $dimensionId, $level);

            return (int) $this->connection->lastInsertId();
        }

        $this->connection->update('scenario_criteria_draft_levels', [
            'level_value' => $level['value'],
            'label' => $level['label'],
            'description' => $level['description'],
            'is_enabled' => $level['enabled'] ? 1 : 0,
            'is_override' => $level['label'] !== $existing['source_label'] || !$level['enabled'] ? 1 : 0,
        ], ['uuid' => $level['uuid'], 'draft_id' => $draftId]);

        return (int) $existing['id'];
    }

    /** @param array<string, mixed> $dimension */
    private function updateDimension(int $draftId, array $dimension): ?int
    {
        $existing = $this->connection->fetchAssociative(
            'SELECT id, source_label FROM scenario_criteria_draft_dimensions '
            . 'WHERE uuid = ? AND draft_id = ? FOR UPDATE',
            [$dimension['uuid'], $draftId]
        );
        if ($existing === false) {
            $source = $dimension['source'] ?? null;
            if (!is_array($source)) {
                return null;
            }
            $this->connection->insert('scenario_criteria_draft_dimensions', [
                'uuid' => $dimension['uuid'],
                'draft_id' => $draftId,
                'dimension_key' => $dimension['key'],
                'source_scale_id' => (int) $source['scaleId'],
                'source_scale_type_id' => (int) $source['scaleTypeId'],
                'source_label' => (string) ($source['label'] ?? $dimension['label']),
                'label' => $dimension['label'],
                'is_enabled' => $dimension['enabled'] ? 1 : 0,
                'is_override' => 1,
            ]);

            return (int) $this->connection->lastInsertId();
        }

        $this->connection->update('scenario_criteria_draft_dimensions', [
            'label' => $dimension['label'],
            'is_enabled' => $dimension['enabled'] ? 1 : 0,
            'is_override' => $dimension['label'] !== $existing['source_label'] || !$dimension['enabled'] ? 1 : 0,
        ], ['uuid' => $dimension['uuid'], 'draft_id' => $draftId]);

        return (int) $existing['id'];
    }

    /** @param array<int, array<string, mixed>> $levels */
    private function removeMissingLevels(int $draftId, ?int $dimensionId, array $levels): void
    {
        $uuids = array_column($levels, 'uuid');
        $rows = $this->connection->fetchFirstColumn(
            'SELECT uuid FROM scenario_criteria_draft_levels WHERE draft_id = ? '
            . ($dimensionId === null ? 'AND dimension_id IS NULL' : 'AND dimension_id = ?'),
            $dimensionId === null ? [$draftId] : [$draftId, $dimensionId]
        );
        foreach ($rows as $uuid) {
            if (!in_array($uuid, $uuids, true)) {
                $this->connection->delete('scenario_criteria_draft_levels', ['uuid' => $uuid, 'draft_id' => $draftId]);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $dimensions */
    private function removeMissingDimensions(int $draftId, array $dimensions): void
    {
        $uuids = array_column($dimensions, 'uuid');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, uuid FROM scenario_criteria_draft_dimensions WHERE draft_id = ?',
            [$draftId]
        );
        foreach ($rows as $row) {
            if (!in_array($row['uuid'], $uuids, true)) {
                $this->connection->delete('scenario_criteria_draft_levels', [
                    'draft_id' => $draftId,
                    'dimension_id' => (int) $row['id'],
                ]);
                $this->connection->delete('scenario_criteria_draft_dimensions', ['id' => (int) $row['id']]);
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function analysisForUpdate(int $anrId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, anr_id, uuid, revision, criteria_locked_at FROM scenario_analyses WHERE anr_id = ? FOR UPDATE',
            [$anrId]
        );

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    private function draftForUpdate(int $anrId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT d.* FROM scenario_criteria_drafts d '
            . 'INNER JOIN scenario_analyses a ON a.id = d.scenario_analysis_id '
            . 'WHERE a.anr_id = ? FOR UPDATE',
            [$anrId]
        );

        return $row === false ? null : $row;
    }

    private function criteriaLocked(int $analysisId): bool
    {
        return $this->connection->fetchOne(
            'SELECT id FROM scenario_criteria_locks WHERE scenario_analysis_id = ?',
            [$analysisId]
        ) !== false;
    }

    /** @return array<int, array<string, mixed>> */
    private function draftSourceRows(int $draftId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT l.*, d.dimension_key FROM scenario_criteria_draft_levels l '
            . 'LEFT JOIN scenario_criteria_draft_dimensions d ON d.id = l.dimension_id '
            . 'WHERE l.draft_id = ? ORDER BY l.dimension_id ASC, l.source_scale_index ASC, l.level_value ASC',
            [$draftId]
        );
    }

    /** @param array<string, mixed> $analysis @param array<string, mixed> $patch */
    private function touchAnalysis(
        array $analysis,
        int $revision,
        int $actorId,
        string $correlationId,
        string $action,
        array $patch
    ): void {
        $this->connection->update('scenario_analyses', ['revision' => $revision + 1], [
            'id' => $analysis['id'],
            'revision' => $revision,
        ]);
        $this->audit((int) $analysis['anr_id'], $actorId, $correlationId, $analysis['uuid'], $action, $patch);
    }

    /** @param array<string, mixed> $patch */
    private function audit(
        int $anrId,
        int $actorId,
        string $correlationId,
        string $targetUuid,
        string $action,
        array $patch
    ): void {
        $this->connection->insert('scenario_audit_events', [
            'uuid' => Uuid::uuid4()->toString(),
            'anr_id' => $anrId,
            'actor_id' => $actorId,
            'correlation_id' => substr($correlationId, 0, 128),
            'target_uuid' => $targetUuid,
            'action' => $action,
            'change_patch' => json_encode($patch, JSON_THROW_ON_ERROR),
        ]);
    }

    /** @param array<string, mixed> $draft @param array<int, array<string, mixed>> $dimensions @param array<int, array<string, mixed>> $levels @return array<string, mixed> */
    private function projectDraft(array $draft, array $dimensions, array $levels): array
    {
        $likelihood = [];
        $dimensionLevels = [];
        foreach ($levels as $level) {
            $item = $this->projectLevel($level);
            if ($level['dimension_id'] === null) {
                $likelihood[] = $item;
            } else {
                $dimensionLevels[(int) $level['dimension_id']][] = $item;
            }
        }
        $projectedDimensions = array_map(function (array $dimension) use ($dimensionLevels): array {
            return [
                'uuid' => $dimension['uuid'],
                'key' => $dimension['dimension_key'],
                'label' => $dimension['label'],
                'enabled' => (bool) $dimension['is_enabled'],
                'overridden' => (bool) $dimension['is_override'],
                'sourceLabel' => $dimension['source_label'],
                'source' => [
                    'scaleId' => (int) $dimension['source_scale_id'],
                    'scaleTypeId' => (int) $dimension['source_scale_type_id'],
                    'label' => $dimension['source_label'],
                ],
                'levels' => $dimensionLevels[(int) $dimension['id']] ?? [],
            ];
        }, $dimensions);

        return [
            'uuid' => $draft['uuid'],
            'identifier' => $draft['identifier'],
            'title' => $draft['title'],
            'revision' => (int) $draft['revision'],
            'aggregationPolicy' => $draft['aggregation_policy'],
            'bands' => json_decode((string) $draft['bands'], true, 512, JSON_THROW_ON_ERROR),
            'acceptanceRules' => json_decode((string) $draft['acceptance_rules'], true, 512, JSON_THROW_ON_ERROR),
            'source' => [
                'sourceAnrId' => (int) $draft['source_anr_id'],
                'languageCode' => $draft['source_language_code'],
                'readAt' => $this->apiDate((string) $draft['source_read_at']),
            ],
            'likelihoodLevels' => $likelihood,
            'dimensions' => $projectedDimensions,
        ];
    }

    /** @param array<string, mixed> $level @return array<string, mixed> */
    private function projectLevel(array $level): array
    {
        return [
            'uuid' => $level['uuid'],
            'value' => (int) $level['level_value'],
            'label' => $level['label'],
            'description' => $level['description'],
            'enabled' => (bool) $level['is_enabled'],
            'overridden' => (bool) $level['is_override'],
            'sourceLabel' => $level['source_label'],
            'source' => [
                'scaleId' => (int) $level['source_scale_id'],
                'scaleTypeId' => $level['source_scale_type_id'] === null ? null : (int) $level['source_scale_type_id'],
                'commentId' => (int) $level['source_comment_id'],
                'scaleIndex' => (int) $level['source_scale_index'],
                'scaleValue' => (int) $level['source_scale_value'],
                'min' => (int) $level['source_min'],
                'max' => (int) $level['source_max'],
                'label' => $level['source_label'],
                'comment' => $level['source_comment'],
            ],
        ];
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    private function projectLockSource(array $source): array
    {
        return [
            'kind' => $source['kind'],
            'dimensionKey' => $source['dimension_key'],
            'scaleId' => (int) $source['source_scale_id'],
            'scaleTypeId' => $source['source_scale_type_id'] === null ? null : (int) $source['source_scale_type_id'],
            'commentId' => (int) $source['source_comment_id'],
            'scaleIndex' => (int) $source['source_scale_index'],
            'scaleValue' => (int) $source['source_scale_value'],
            'min' => (int) $source['source_min'],
            'max' => (int) $source['source_max'],
            'label' => $source['source_label'],
            'comment' => $source['source_comment'],
        ];
    }

    private function databaseDate(string $date): string
    {
        return (new \DateTimeImmutable($date))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function apiDate(string $date): string
    {
        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s\\Z');
    }
}
