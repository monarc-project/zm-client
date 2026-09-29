<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Ramsey\Uuid\Uuid;

/** Persists client-scoped profile versions and append-only Scenario assessment evidence. */
final class ScenarioAssessmentTable
{
    /** Each FrontOffice client has its own database; this preserves the existing schema scope. */
    private const CLIENT_SCOPE_ID = 1;

    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listProfiles(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.uuid AS profile_uuid, p.identifier, p.title, p.revision, '
            . 'v.uuid AS version_uuid, v.version, v.payload, v.created_at '
            . 'FROM scenario_criteria_profiles p '
            . 'INNER JOIN scenario_criteria_profile_versions v ON v.profile_id = p.id '
            . 'ORDER BY p.identifier ASC, v.version DESC'
        );

        return ['items' => array_map([$this, 'projectProfile'], $rows)];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed>|null */
    public function createProfileVersion(
        string $identifier,
        string $title,
        array $payload,
        int $expectedRevision,
        int $actorId,
        int $anrId,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $identifier,
            $title,
            $payload,
            $expectedRevision,
            $actorId,
            $anrId,
            $correlationId
        ): ?array {
            $profile = $this->connection->fetchAssociative(
                'SELECT * FROM scenario_criteria_profiles WHERE client_id = ? AND identifier = ? FOR UPDATE',
                [self::CLIENT_SCOPE_ID, $identifier]
            );
            if ($profile === false) {
                if ($expectedRevision !== 0) {
                    return null;
                }
                $profile = [
                    'id' => null,
                    'uuid' => Uuid::uuid4()->toString(),
                    'identifier' => $identifier,
                    'title' => $title,
                    'revision' => 1,
                ];
                $this->connection->insert('scenario_criteria_profiles', [
                    'uuid' => $profile['uuid'],
                    'client_id' => self::CLIENT_SCOPE_ID,
                    'identifier' => $identifier,
                    'title' => $title,
                    'revision' => 1,
                ]);
                $profile['id'] = (int) $this->connection->lastInsertId();
            } elseif ((int) $profile['revision'] !== $expectedRevision) {
                return null;
            } else {
                $this->connection->update('scenario_criteria_profiles', [
                    'title' => $title,
                    'revision' => $expectedRevision + 1,
                ], ['id' => $profile['id']]);
                $profile['title'] = $title;
                $profile['revision'] = $expectedRevision + 1;
            }
            $version = (int) $this->connection->fetchOne(
                'SELECT COALESCE(MAX(version), 0) + 1 FROM scenario_criteria_profile_versions WHERE profile_id = ?',
                [(int) $profile['id']]
            );
            $versionUuid = Uuid::uuid4()->toString();
            $this->connection->insert('scenario_criteria_profile_versions', [
                'uuid' => $versionUuid,
                'profile_id' => (int) $profile['id'],
                'version' => $version,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_by' => $actorId,
            ]);
            $this->audit($anrId, $actorId, $correlationId, $versionUuid, 'scenario_criteria_profile_version_created', [
                'identifier' => $identifier,
                'version' => $version,
            ]);

            return $this->profileByVersionUuid($versionUuid);
        });
    }

    /** @return array<string, mixed>|null */
    public function assignProfile(
        int $anrId,
        string $profileVersionUuid,
        int $expectedAnalysisRevision,
        int $actorId,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $profileVersionUuid,
            $expectedAnalysisRevision,
            $actorId,
            $correlationId
        ): ?array {
            $analysis = $this->connection->fetchAssociative(
                'SELECT id, uuid, revision FROM scenario_analyses WHERE anr_id = ? FOR UPDATE',
                [$anrId]
            );
            $profile = $this->profileByVersionUuid($profileVersionUuid, true);
            if ($analysis === false || $profile === null || (int) $analysis['revision'] !== $expectedAnalysisRevision) {
                return null;
            }
            $this->connection->executeStatement(
                'INSERT INTO scenario_analysis_criteria_profiles '
                . '(scenario_analysis_id, profile_version_id, profile_snapshot, revision) VALUES (?, ?, ?, 1) '
                . 'ON DUPLICATE KEY UPDATE profile_version_id = VALUES(profile_version_id), '
                . 'profile_snapshot = VALUES(profile_snapshot), revision = revision + 1',
                [(int) $analysis['id'], (int) $profile['_id'], json_encode($profile, JSON_THROW_ON_ERROR)]
            );
            $this->connection->update(
                'scenario_analyses',
                ['revision' => $expectedAnalysisRevision + 1],
                ['id' => $analysis['id']]
            );
            $this->audit(
                $anrId,
                $actorId,
                $correlationId,
                (string) $analysis['uuid'],
                'scenario_criteria_profile_assigned',
                [
                    'profileVersionUuid' => $profileVersionUuid,
                    'analysisRevision' => $expectedAnalysisRevision + 1,
                ]
            );

            return $profile + ['analysisRevision' => $expectedAnalysisRevision + 1];
        });
    }

    /** @return array<string, mixed>|null */
    public function assignedProfile(int $anrId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT acp.profile_snapshot FROM scenario_analysis_criteria_profiles acp '
            . 'INNER JOIN scenario_analyses a ON a.id = acp.scenario_analysis_id WHERE a.anr_id = ?',
            [$anrId]
        );

        return $row === false ? null : json_decode((string) $row['profile_snapshot'], true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $snapshot @param array<string, mixed>|null $legacyComparison @return array<string, mixed>|null */
    public function saveAssessment(
        int $anrId,
        string $storyUuid,
        string $type,
        string $profileVersionUuid,
        array $snapshot,
        ?array $legacyComparison,
        int $storyRevision,
        int $actorId,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $storyUuid,
            $type,
            $profileVersionUuid,
            $snapshot,
            $legacyComparison,
            $storyRevision,
            $actorId,
            $correlationId
        ): ?array {
            $story = $this->connection->fetchAssociative(
                'SELECT id, revision FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ? FOR UPDATE',
                [$anrId, $storyUuid]
            );
            $profile = $this->assignedProfile($anrId);
            if ($story === false || $profile === null
                || ($profile['versionUuid'] ?? null) !== $profileVersionUuid
                || (int) $story['revision'] !== $storyRevision) {
                return null;
            }
            $previous = $this->connection->fetchAssociative(
                'SELECT uuid FROM scenario_assessments WHERE risk_scenario_id = ? AND assessment_type = ? '
                . 'ORDER BY created_at DESC, id DESC LIMIT 1',
                [(int) $story['id'], $type]
            );
            $uuid = Uuid::uuid4()->toString();
            $this->connection->insert('scenario_assessments', [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'risk_scenario_id' => (int) $story['id'],
                'assessment_type' => $type,
                'profile_version_id' => (int) $profile['_id'],
                'calculation_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'legacy_comparison_snapshot' => $legacyComparison === null
                    ? null
                    : json_encode($legacyComparison, JSON_THROW_ON_ERROR),
                'supersedes_uuid' => $previous === false ? null : (string) $previous['uuid'],
                'created_by' => $actorId,
            ]);
            $this->connection->update(
                'scenario_risk_scenarios',
                ['revision' => $storyRevision + 1],
                ['id' => $story['id']]
            );
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'scenario_assessment_saved', [
                'assessmentType' => $type,
                'profileVersionUuid' => $profileVersionUuid,
                'band' => $snapshot['band'] ?? null,
            ]);

            return $this->assessmentByUuid($uuid) + ['storyRevision' => $storyRevision + 1];
        });
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listAssessments(int $anrId, string $storyUuid): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.* FROM scenario_assessments a INNER JOIN scenario_risk_scenarios s ON s.id = a.risk_scenario_id '
            . 'WHERE a.anr_id = ? AND s.uuid = ? ORDER BY a.created_at DESC, a.id DESC',
            [$anrId, $storyUuid]
        );

        return ['items' => array_map([$this, 'projectAssessment'], $rows)];
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createTreatment(
        int $anrId,
        string $storyUuid,
        array $data,
        int $storyRevision,
        int $actorId,
        string $correlationId
    ): ?array {
        return $this->createStoryRecord(
            'scenario_treatments',
            $anrId,
            $storyUuid,
            $data,
            $storyRevision,
            $actorId,
            $correlationId
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createMonitoring(
        int $anrId,
        string $storyUuid,
        array $data,
        int $storyRevision,
        int $actorId,
        string $correlationId
    ): ?array {
        return $this->createStoryRecord(
            'scenario_monitoring_observations',
            $anrId,
            $storyUuid,
            $data,
            $storyRevision,
            $actorId,
            $correlationId
        );
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listStoryRecords(int $anrId, string $storyUuid, string $table): array
    {
        $this->assertStoryRecordTable($table);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.* FROM ' . $table . ' r INNER JOIN scenario_risk_scenarios s ON s.id = r.risk_scenario_id '
            . 'WHERE r.anr_id = ? AND s.uuid = ? ORDER BY r.created_at DESC, r.id DESC',
            [$anrId, $storyUuid]
        );

        return ['items' => array_map([$this, 'projectStoryRecord'], $rows)];
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed>|null */
    public function createAcceptance(
        int $anrId,
        string $storyUuid,
        string $assessmentUuid,
        int $assignedSupervisorId,
        array $snapshot,
        int $storyRevision,
        int $actorId,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $storyUuid,
            $assessmentUuid,
            $assignedSupervisorId,
            $snapshot,
            $storyRevision,
            $actorId,
            $correlationId
        ): ?array {
            $story = $this->lockedStory($anrId, $storyUuid, $storyRevision);
            $assessment = $this->connection->fetchAssociative(
                'SELECT id, assessment_type, calculation_snapshot FROM scenario_assessments '
                . 'WHERE anr_id = ? AND risk_scenario_id = ? AND uuid = ?',
                [$anrId, $story['id'] ?? 0, $assessmentUuid]
            );
            if ($story === null || $assessment === false) {
                return null;
            }
            if ($assessment['assessment_type'] !== 'proposed_residual') {
                throw new \InvalidArgumentException(
                    'Residual-risk acceptance requires a proposed-residual assessment.'
                );
            }
            $calculation = json_decode((string) $assessment['calculation_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            if (in_array($calculation['band'] ?? null, ['high', 'critical'], true)
                && (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM scenario_treatments WHERE risk_scenario_id = ?',
                    [(int) $story['id']]
                ) === 0) {
                throw new \InvalidArgumentException(
                    'High or critical residual risk requires a recorded treatment before acceptance.'
                );
            }
            $uuid = Uuid::uuid4()->toString();
            $this->connection->insert('scenario_acceptance_decisions', [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'risk_scenario_id' => $story['id'],
                'assessment_id' => $assessment['id'],
                'assigned_supervisor_id' => $assignedSupervisorId,
                'decision_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            ]);
            $this->touchStory((int) $story['id'], $storyRevision);
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'scenario_residual_risk_accepted', [
                'assessmentUuid' => $assessmentUuid,
                'decision' => $snapshot['decision'] ?? null,
                'assignedSupervisorId' => $assignedSupervisorId,
            ]);

            return $this->acceptanceByUuid($uuid) + ['storyRevision' => $storyRevision + 1];
        });
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listAcceptances(int $anrId, string $storyUuid): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT d.* FROM scenario_acceptance_decisions d '
            . 'INNER JOIN scenario_risk_scenarios s ON s.id = d.risk_scenario_id '
            . 'WHERE d.anr_id = ? AND s.uuid = ? ORDER BY d.created_at DESC, d.id DESC',
            [$anrId, $storyUuid]
        );

        return ['items' => array_map([$this, 'projectAcceptance'], $rows)];
    }

    /** @return array<string, mixed>|null */
    private function profileByVersionUuid(string $uuid, bool $includeInternalId = false): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT p.uuid AS profile_uuid, p.identifier, p.title, p.revision, '
            . 'v.id AS version_id, v.uuid AS version_uuid, v.version, v.payload, v.created_at '
            . 'FROM scenario_criteria_profiles p '
            . 'INNER JOIN scenario_criteria_profile_versions v ON v.profile_id = p.id '
            . 'WHERE v.uuid = ?',
            [$uuid]
        );
        if ($row === false) {
            return null;
        }
        $profile = $this->projectProfile($row);
        if ($includeInternalId) {
            $profile['_id'] = (int) $row['version_id'];
        }

        return $profile;
    }

    /** @return array<string, mixed> */
    private function createStoryRecord(
        string $table,
        int $anrId,
        string $storyUuid,
        array $data,
        int $storyRevision,
        int $actorId,
        string $correlationId
    ): ?array {
        $this->assertStoryRecordTable($table);

        return $this->connection->transactional(function () use (
            $table,
            $anrId,
            $storyUuid,
            $data,
            $storyRevision,
            $actorId,
            $correlationId
        ): ?array {
            $story = $this->lockedStory($anrId, $storyUuid, $storyRevision);
            if ($story === null) {
                return null;
            }
            $uuid = Uuid::uuid4()->toString();
            $this->connection->insert($table, $data + [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'risk_scenario_id' => (int) $story['id'],
            ]);
            $this->touchStory((int) $story['id'], $storyRevision);
            $this->audit($anrId, $actorId, $correlationId, $uuid, $table === 'scenario_treatments'
                ? 'scenario_treatment_created'
                : 'scenario_monitoring_created', ['storyUuid' => $storyUuid]);

            return $this->projectStoryRecord($data + ['uuid' => $uuid, 'revision' => 1])
                + ['storyRevision' => $storyRevision + 1];
        });
    }

    /** @return array<string, mixed>|null */
    private function lockedStory(int $anrId, string $storyUuid, int $revision): ?array
    {
        $story = $this->connection->fetchAssociative(
            'SELECT id, revision FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ? FOR UPDATE',
            [$anrId, $storyUuid]
        );

        return $story === false || (int) $story['revision'] !== $revision ? null : $story;
    }

    private function touchStory(int $storyId, int $revision): void
    {
        $this->connection->update('scenario_risk_scenarios', ['revision' => $revision + 1], ['id' => $storyId]);
    }

    private function assertStoryRecordTable(string $table): void
    {
        if (!in_array($table, ['scenario_treatments', 'scenario_monitoring_observations'], true)) {
            throw new \InvalidArgumentException('Scenario record table is invalid.');
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectProfile(array $row): array
    {
        $payload = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);

        return [
            'uuid' => (string) $row['profile_uuid'],
            'versionUuid' => (string) $row['version_uuid'],
            'identifier' => (string) ($payload['identifier'] ?? $row['identifier']),
            'title' => (string) ($payload['title'] ?? $row['title']),
            'revision' => (int) $row['revision'],
            'version' => (int) $row['version'],
            'payload' => $payload,
            'createdAt' => (string) $row['created_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function assessmentByUuid(string $uuid): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM scenario_assessments WHERE uuid = ?', [$uuid]);
        if ($row === false) {
            throw new \RuntimeException('Scenario assessment was not saved.');
        }

        return $this->projectAssessment($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectAssessment(array $row): array
    {
        return [
            'uuid' => (string) $row['uuid'],
            'assessmentType' => (string) $row['assessment_type'],
            'revision' => (int) $row['revision'],
            'supersedesUuid' => $row['supersedes_uuid'] ?? null,
            'calculation' => json_decode((string) $row['calculation_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            'legacyComparison' => $row['legacy_comparison_snapshot'] === null
                ? null
                : json_decode((string) $row['legacy_comparison_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            'createdAt' => (string) $row['created_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectStoryRecord(array $row): array
    {
        $record = ['uuid' => (string) $row['uuid'], 'revision' => (int) ($row['revision'] ?? 1)];
        foreach ([
            'treatment_type',
            'action_text',
            'owner_id',
            'due_at',
            'status',
            'indicator',
            'reassessment_trigger',
            'observation',
        ] as $field) {
            if (array_key_exists($field, $row)) {
                $record[$this->camel($field)] = $row[$field];
            }
        }

        return $record;
    }

    /** @return array<string, mixed> */
    private function acceptanceByUuid(string $uuid): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scenario_acceptance_decisions WHERE uuid = ?',
            [$uuid]
        );
        if ($row === false) {
            throw new \RuntimeException('Scenario acceptance was not saved.');
        }

        return $this->projectAcceptance($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectAcceptance(array $row): array
    {
        return [
            'uuid' => (string) $row['uuid'],
            'assignedSupervisorId' => (int) $row['assigned_supervisor_id'],
            'revision' => (int) $row['revision'],
            'decision' => json_decode(
                (string) $row['decision_snapshot'],
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
            'createdAt' => (string) $row['created_at'],
        ];
    }

    private function camel(string $field): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $field))));
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
            'rationale' => null,
        ]);
    }
}
