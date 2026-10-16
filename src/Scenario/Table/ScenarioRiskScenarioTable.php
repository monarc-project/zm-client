<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Exception\ScenarioRiskScenarioException;
use Monarc\FrontOffice\Scenario\Service\ScenarioRiskGraph;
use Ramsey\Uuid\Uuid;

/** Persists ANR-scoped editable Scenario risk scenarios and causal relations. */
final class ScenarioRiskScenarioTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @return array{items: array<int, array<string, mixed>>, page: int, pageSize: int, total: int} */
    public function list(int $anrId, int $page, int $pageSize): array
    {
        $total = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scenario_risk_scenarios WHERE anr_id = ?',
            [$anrId]
        );
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_risk_scenarios WHERE anr_id = ? '
            . 'ORDER BY created_at ASC, uuid ASC LIMIT ? OFFSET ?',
            [$anrId, $pageSize, ($page - 1) * $pageSize],
            [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        return [
            'items' => array_map([$this, 'projectScenario'], $rows),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findScenario(int $anrId, string $uuid): ?array
    {
        $row = $this->scenarioRow($anrId, $uuid);

        return $row === null ? null : $this->projectScenario($row);
    }

    /** @return array{uuid: string, revision: int}|null */
    public function status(int $anrId, string $uuid): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT uuid, revision FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ?',
            [$anrId, $uuid]
        );

        return $row === false ? null : [
            'uuid' => (string) $row['uuid'],
            'revision' => (int) $row['revision'],
        ];
    }

    /** @return array<string, mixed>|null */
    public function detail(int $anrId, string $uuid): ?array
    {
        $scenario = $this->scenarioRow($anrId, $uuid);
        if ($scenario === null) {
            return null;
        }

        return $this->detailFromRow($scenario);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createScenario(
        int $anrId,
        int $analysisId,
        int $actorId,
        array $data,
        string $correlationId
    ): array {
        return $this->connection->transactional(function () use (
            $anrId,
            $analysisId,
            $actorId,
            $data,
            $correlationId
        ): array {
            $uuid = Uuid::uuid4()->toString();
            $row = [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'scenario_analysis_id' => $analysisId,
                'title' => $data['title'],
                'source_snapshot_uuid' => $data['sourceSnapshotUuid'] ?? null,
                'source_instance_record_uuid' => $data['sourceInstanceRecordUuid'] ?? null,
                'description' => $data['description'] ?? null,
                'summary_addendum' => $data['summaryAddendum'] ?? null,
                // This is internal lineage, not workshop-authored content. The
                // actor, time and request correlation are recorded in the
                // append-only audit event below.
                'provenance' => $this->generatedProvenance($uuid),
            ];
            $this->connection->insert('scenario_risk_scenarios', $row);
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'risk_scenario_created', [
                'after' => $this->safeScenarioPatch($row),
            ]);

            return $this->detail($anrId, $uuid) ?? throw new ScenarioRiskScenarioException(
                'write_failed',
                'The risk scenario could not be created.'
            );
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function updateScenario(
        int $anrId,
        string $uuid,
        int $actorId,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $uuid,
            $actorId,
            $data,
            $revision,
            $correlationId
        ): ?array {
            $scenario = $this->lockScenario($anrId, $uuid, $revision);
            if ($scenario === null) {
                return null;
            }
            $updates = $this->scenarioColumns($data);
            if ($updates === []) {
                return $this->detailFromRow($scenario);
            }
            $updates['revision'] = $revision + 1;
            $this->connection->update('scenario_risk_scenarios', $updates, ['id' => $scenario['id']]);
            $after = array_merge($scenario, $updates);
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'risk_scenario_updated', [
                'before' => $this->safeScenarioPatch($scenario),
                'after' => $this->safeScenarioPatch($after),
            ]);

            return $this->detail($anrId, $uuid);
        });
    }

    public function deleteScenario(
        int $anrId,
        string $uuid,
        int $actorId,
        int $revision,
        string $correlationId
    ): bool {
        return $this->connection->transactional(function () use (
            $anrId,
            $uuid,
            $actorId,
            $revision,
            $correlationId
        ): bool {
            $scenario = $this->lockScenario($anrId, $uuid, $revision);
            if ($scenario === null) {
                return false;
            }
            $scenarioId = (int) $scenario['id'];
            $this->connection->delete('scenario_event_edges', ['risk_scenario_id' => $scenarioId]);
            $this->connection->delete('scenario_event_consequences', ['risk_scenario_id' => $scenarioId]);
            $this->connection->delete('scenario_subject_links', ['risk_scenario_id' => $scenarioId]);
            $this->connection->delete('scenario_control_links', ['risk_scenario_id' => $scenarioId]);
            $this->connection->delete('scenario_events', ['risk_scenario_id' => $scenarioId]);
            $this->connection->delete('scenario_consequences', ['risk_scenario_id' => $scenarioId]);
            $this->connection->delete('scenario_risk_sources', ['risk_scenario_id' => $scenarioId]);
            $this->connection->delete('scenario_causes', ['risk_scenario_id' => $scenarioId]);
            $this->connection->delete('scenario_risk_scenarios', ['id' => $scenarioId]);
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'risk_scenario_deleted', [
                'before' => $this->safeScenarioPatch($scenario),
            ]);

            return true;
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function saveSource(
        int $anrId,
        string $scenarioUuid,
        int $actorId,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        return $this->saveSingleRiskScenarioRecord(
            'scenario_risk_sources',
            'risk_source',
            $anrId,
            $scenarioUuid,
            $actorId,
            $data,
            $revision,
            $correlationId,
            ['title', 'description', 'legacyRiskSourceId', 'referenceSnapshot']
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function saveCause(
        int $anrId,
        string $scenarioUuid,
        int $actorId,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        return $this->saveSingleRiskScenarioRecord(
            'scenario_causes',
            'cause',
            $anrId,
            $scenarioUuid,
            $actorId,
            $data,
            $revision,
            $correlationId,
            [
                'title',
                'description',
                'threatReferenceUuid',
                'vulnerabilityReferenceUuid',
                'threatReferenceSnapshot',
                'vulnerabilityReferenceSnapshot',
            ]
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createNode(
        int $anrId,
        string $scenarioUuid,
        string $type,
        int $actorId,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        $table = $this->nodeTable($type);
        if ($table === null) {
            return null;
        }

        return $this->connection->transactional(function () use (
            $anrId,
            $scenarioUuid,
            $type,
            $table,
            $actorId,
            $data,
            $revision,
            $correlationId
        ): ?array {
            $scenario = $this->lockScenario($anrId, $scenarioUuid, $revision);
            if ($scenario === null) {
                return null;
            }
            $uuid = Uuid::uuid4()->toString();
            $row = [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'risk_scenario_id' => (int) $scenario['id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ];
            $this->connection->insert($table, $row);
            $this->touchScenario((int) $scenario['id'], $revision);
            $result = $this->projectNode($this->nodeRow($table, $anrId, $uuid) ?: $row);
            $this->audit($anrId, $actorId, $correlationId, $uuid, $type . '_created', [
                'after' => $result,
            ]);

            return $result + ['riskScenarioRevision' => $revision + 1];
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function updateNode(
        int $anrId,
        string $scenarioUuid,
        string $type,
        string $uuid,
        int $actorId,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        $table = $this->nodeTable($type);
        if ($table === null) {
            return null;
        }

        return $this->connection->transactional(function () use (
            $anrId,
            $scenarioUuid,
            $type,
            $table,
            $uuid,
            $actorId,
            $data,
            $revision,
            $correlationId
        ): ?array {
            $scenario = $this->lockScenario($anrId, $scenarioUuid, $revision);
            $node = $this->nodeRow($table, $anrId, $uuid);
            if ($scenario === null || $node === null || (int) $node['risk_scenario_id'] !== (int) $scenario['id']) {
                return null;
            }
            $updates = $this->nodeColumns($data);
            if ($updates === []) {
                return $this->projectNode($node) + ['riskScenarioRevision' => $revision];
            }
            $updates['revision'] = (int) $node['revision'] + 1;
            $this->connection->update($table, $updates, ['id' => $node['id']]);
            $this->touchScenario((int) $scenario['id'], $revision);
            $after = $this->nodeRow($table, $anrId, $uuid) ?: array_merge($node, $updates);
            $this->audit($anrId, $actorId, $correlationId, $uuid, $type . '_updated', [
                'before' => $this->projectNode($node),
                'after' => $this->projectNode($after),
            ]);

            return $this->projectNode($after) + ['riskScenarioRevision' => $revision + 1];
        });
    }

    public function deleteNode(
        int $anrId,
        string $scenarioUuid,
        string $type,
        string $uuid,
        int $actorId,
        int $revision,
        string $correlationId
    ): bool {
        $table = $this->nodeTable($type);
        if ($table === null) {
            return false;
        }

        return $this->connection->transactional(function () use (
            $anrId,
            $scenarioUuid,
            $type,
            $table,
            $uuid,
            $actorId,
            $revision,
            $correlationId
        ): bool {
            $scenario = $this->lockScenario($anrId, $scenarioUuid, $revision);
            $node = $this->nodeRow($table, $anrId, $uuid);
            if ($scenario === null || $node === null || (int) $node['risk_scenario_id'] !== (int) $scenario['id']) {
                return false;
            }
            $scenarioId = (int) $scenario['id'];
            if ($type === 'event') {
                $this->connection->executeStatement(
                    'DELETE FROM scenario_event_edges WHERE risk_scenario_id = ? '
                    . 'AND (from_event_id = ? OR to_event_id = ?)',
                    [$scenarioId, $node['id'], $node['id']]
                );
                $this->connection->delete('scenario_event_consequences', ['event_id' => $node['id']]);
            } else {
                $this->connection->delete('scenario_event_consequences', ['consequence_id' => $node['id']]);
            }
            $this->connection->executeStatement(
                'DELETE FROM scenario_subject_links WHERE risk_scenario_id = ? AND target_uuid = ?',
                [$scenarioId, $uuid]
            );
            $this->connection->executeStatement(
                'DELETE FROM scenario_control_links WHERE risk_scenario_id = ? AND target_uuid = ?',
                [$scenarioId, $uuid]
            );
            $this->connection->delete($table, ['id' => $node['id']]);
            $this->touchScenario($scenarioId, $revision);
            $this->audit($anrId, $actorId, $correlationId, $uuid, $type . '_deleted', [
                'before' => $this->projectNode($node),
            ]);

            return true;
        });
    }

    /** @return array<string, mixed>|null */
    public function createRelation(
        int $anrId,
        string $scenarioUuid,
        string $type,
        int $actorId,
        array $data,
        int $revision,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $scenarioUuid,
            $type,
            $actorId,
            $data,
            $revision,
            $correlationId
        ): ?array {
            $scenario = $this->lockScenario($anrId, $scenarioUuid, $revision);
            if ($scenario === null) {
                return null;
            }
            $result = match ($type) {
                'edge' => $this->insertEdge($anrId, $scenario, $data),
                'event-consequence' => $this->insertEventConsequence($anrId, $scenario, $data),
                'subject-link' => $this->insertLink('scenario_subject_links', $anrId, $scenario, $data),
                'control-link' => $this->insertLink('scenario_control_links', $anrId, $scenario, $data),
                default => throw new ScenarioRiskScenarioException(
                    'invalid_relation',
                    'The Scenario relation is invalid.'
                ),
            };
            $this->touchScenario((int) $scenario['id'], $revision);
            $this->audit($anrId, $actorId, $correlationId, $result['uuid'], $type . '_created', [
                'after' => $result,
            ]);

            return $result + ['riskScenarioRevision' => $revision + 1];
        });
    }

    public function deleteRelation(
        int $anrId,
        string $scenarioUuid,
        string $type,
        string $uuid,
        int $actorId,
        int $revision,
        string $correlationId
    ): bool {
        $table = $this->relationTable($type);
        if ($table === null) {
            return false;
        }

        return $this->connection->transactional(function () use (
            $anrId,
            $scenarioUuid,
            $type,
            $uuid,
            $actorId,
            $revision,
            $correlationId,
            $table
        ): bool {
            $scenario = $this->lockScenario($anrId, $scenarioUuid, $revision);
            $relation = $this->connection->fetchAssociative(
                'SELECT * FROM ' . $table . ' WHERE anr_id = ? AND uuid = ?',
                [$anrId, $uuid]
            );
            if ($scenario === null || $relation === false
                || (int) $relation['risk_scenario_id'] !== (int) $scenario['id']) {
                return false;
            }
            $this->connection->delete($table, ['id' => $relation['id']]);
            $this->touchScenario((int) $scenario['id'], $revision);
            $this->audit($anrId, $actorId, $correlationId, $uuid, $type . '_deleted', [
                'before' => $this->projectRelation($type, $relation),
            ]);

            return true;
        });
    }

    /** @return array<string, mixed> */
    public function completeness(int $anrId, ?string $riskScenarioUuid = null): array
    {
        $riskScenarios = $riskScenarioUuid === null
            ? $this->connection->fetchAllAssociative(
                'SELECT * FROM scenario_risk_scenarios WHERE anr_id = ? ORDER BY created_at ASC, uuid ASC',
                [$anrId]
            )
            : $this->connection->fetchAllAssociative(
                'SELECT * FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ? ORDER BY created_at ASC, uuid ASC',
                [$anrId, $riskScenarioUuid]
            );
        $warnings = [];
        if ($riskScenarios === [] && $riskScenarioUuid === null) {
            $warnings[] = ['code' => 'risk_scenario_missing', 'message' => 'Create at least one risk scenario.'];
        }
        foreach ($riskScenarios as $riskScenario) {
            $riskScenarioId = (int) $riskScenario['id'];
            $prefix = ['riskScenarioUuid' => $riskScenario['uuid']];
            if (!$this->hasRiskScenarioRecord('scenario_risk_sources', $riskScenarioId)) {
                $warnings[] = $prefix + ['code' => 'risk_source_missing', 'message' => 'Add a local risk source.'];
            }
            if (!$this->hasRiskScenarioRecord('scenario_events', $riskScenarioId)) {
                $warnings[] = $prefix + ['code' => 'event_missing', 'message' => 'Add at least one event.'];
            }
            if (!$this->hasRiskScenarioRecord('scenario_consequences', $riskScenarioId)) {
                $warnings[] = $prefix + ['code' => 'consequence_missing', 'message' => 'Add at least one consequence.'];
            }
            if (!$this->hasRiskScenarioRecord('scenario_event_consequences', $riskScenarioId)) {
                $warnings[] = $prefix + [
                    'code' => 'event_consequence_missing',
                    'message' => 'Link an event to a consequence.',
                ];
            }
        }

        return ['complete' => $warnings === [], 'warnings' => $warnings];
    }

    /** @param array<string, mixed> $scenario @param array<string, mixed> $data @return array<string, mixed> */
    private function insertEdge(int $anrId, array $scenario, array $data): array
    {
        $from = $this->nodeRow('scenario_events', $anrId, (string) $data['fromEventUuid']);
        $to = $this->nodeRow('scenario_events', $anrId, (string) $data['toEventUuid']);
        if ($from === null || $to === null
            || (int) $from['risk_scenario_id'] !== (int) $scenario['id']
            || (int) $to['risk_scenario_id'] !== (int) $scenario['id']) {
            throw new ScenarioRiskScenarioException(
                'invalid_relation',
                'Both events must belong to this risk scenario.'
            );
        }
        if ($from['id'] === $to['id']) {
            throw new ScenarioRiskScenarioException('self_edge', 'An event cannot cause itself.');
        }
        if ($this->createsCycle((int) $scenario['id'], (int) $from['id'], (int) $to['id'])) {
            throw new ScenarioRiskScenarioException('cycle', 'The event relation would create a directed cycle.');
        }
        $uuid = Uuid::uuid4()->toString();
        try {
            $this->connection->insert('scenario_event_edges', [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'risk_scenario_id' => $scenario['id'],
                'from_event_id' => $from['id'],
                'to_event_id' => $to['id'],
            ]);
        } catch (\Throwable) {
            throw new ScenarioRiskScenarioException('duplicate_relation', 'This event relation already exists.');
        }

        return ['uuid' => $uuid, 'fromEventUuid' => $from['uuid'], 'toEventUuid' => $to['uuid'], 'revision' => 1];
    }

    /** @param array<string, mixed> $scenario @param array<string, mixed> $data @return array<string, mixed> */
    private function insertEventConsequence(int $anrId, array $scenario, array $data): array
    {
        $event = $this->nodeRow('scenario_events', $anrId, (string) $data['eventUuid']);
        $consequence = $this->nodeRow('scenario_consequences', $anrId, (string) $data['consequenceUuid']);
        if ($event === null || $consequence === null
            || (int) $event['risk_scenario_id'] !== (int) $scenario['id']
            || (int) $consequence['risk_scenario_id'] !== (int) $scenario['id']) {
            throw new ScenarioRiskScenarioException(
                'invalid_relation',
                'The event and consequence must belong to this risk scenario.'
            );
        }
        $uuid = Uuid::uuid4()->toString();
        try {
            $this->connection->insert('scenario_event_consequences', [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'risk_scenario_id' => $scenario['id'],
                'event_id' => $event['id'],
                'consequence_id' => $consequence['id'],
            ]);
        } catch (\Throwable) {
            throw new ScenarioRiskScenarioException(
                'duplicate_relation',
                'This event-to-consequence relation already exists.'
            );
        }

        return [
            'uuid' => $uuid,
            'eventUuid' => $event['uuid'],
            'consequenceUuid' => $consequence['uuid'],
            'revision' => 1,
        ];
    }

    /** @param array<string, mixed> $scenario @param array<string, mixed> $data @return array<string, mixed> */
    private function insertLink(string $table, int $anrId, array $scenario, array $data): array
    {
        $targetType = $data['targetType'] ?? 'risk_scenario';
        $targetUuid = $data['targetUuid'] ?? $scenario['uuid'];
        if (!$this->validLinkTarget($anrId, $scenario, $targetType, $targetUuid)) {
            throw new ScenarioRiskScenarioException('invalid_target', 'The link target is not in this risk scenario.');
        }
        $uuid = Uuid::uuid4()->toString();
        $row = [
            'uuid' => $uuid,
            'anr_id' => $anrId,
            'risk_scenario_id' => $scenario['id'],
            'target_type' => $targetType,
            'target_uuid' => $targetUuid,
            'relationship_intent' => $data['relationshipIntent'] ?? null,
            'description' => $data['description'] ?? null,
        ];
        if ($table === 'scenario_subject_links') {
            $row['subject_type'] = $data['subjectType'];
            $row['subject_reference_uuid'] = $data['subjectReferenceUuid'];
        } else {
            $row['control_reference_uuid'] = $data['controlReferenceUuid'];
            $row['relationship_intent'] = $data['relationshipIntent'];
        }
        $row['reference_snapshot'] = json_encode($data['referenceSnapshot'] ?? null, JSON_THROW_ON_ERROR);
        try {
            $this->connection->insert($table, $row);
        } catch (\Throwable) {
            throw new ScenarioRiskScenarioException('duplicate_relation', 'This Scenario link already exists.');
        }

        return $this->projectRelation(
            $table === 'scenario_subject_links' ? 'subject-link' : 'control-link',
            $row
        );
    }

    /** @param array<string, mixed> $data @param array<int, string> $fields @return array<string, mixed>|null */
    private function saveSingleRiskScenarioRecord(
        string $table,
        string $type,
        int $anrId,
        string $scenarioUuid,
        int $actorId,
        array $data,
        int $revision,
        string $correlationId,
        array $fields
    ): ?array {
        return $this->connection->transactional(function () use (
            $table,
            $type,
            $anrId,
            $scenarioUuid,
            $actorId,
            $data,
            $revision,
            $correlationId,
            $fields
        ): ?array {
            $scenario = $this->lockScenario($anrId, $scenarioUuid, $revision);
            if ($scenario === null) {
                return null;
            }
            $existing = $this->connection->fetchAssociative(
                'SELECT * FROM ' . $table . ' WHERE risk_scenario_id = ?',
                [$scenario['id']]
            );
            $columns = $this->recordColumns($data, $fields);
            if ($existing === false) {
                $uuid = Uuid::uuid4()->toString();
                $columns += [
                    'uuid' => $uuid,
                    'anr_id' => $anrId,
                    'risk_scenario_id' => $scenario['id'],
                ];
                $this->connection->insert($table, $columns);
                $before = null;
            } else {
                $uuid = $existing['uuid'];
                $columns['revision'] = (int) $existing['revision'] + 1;
                $this->connection->update($table, $columns, ['id' => $existing['id']]);
                $before = $this->projectRecord($existing);
            }
            $this->touchScenario((int) $scenario['id'], $revision);
            $record = $this->connection->fetchAssociative('SELECT * FROM ' . $table . ' WHERE uuid = ?', [$uuid]);
            $result = $this->projectRecord($record ?: $columns + ['uuid' => $uuid]);
            $this->audit($anrId, $actorId, $correlationId, $uuid, $type . '_saved', [
                'before' => $before,
                'after' => $result,
            ]);

            return $result + ['riskScenarioRevision' => $revision + 1];
        });
    }

    /** @return array<string, mixed>|null */
    private function scenarioRow(int $anrId, string $uuid): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ?',
            [$anrId, $uuid]
        );

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    private function lockScenario(int $anrId, string $uuid, int $revision): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ? FOR UPDATE',
            [$anrId, $uuid]
        );

        return $row === false || (int) $row['revision'] !== $revision ? null : $row;
    }

    /** @return array<string, mixed>|null */
    private function nodeRow(string $table, int $anrId, string $uuid): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . $table . ' WHERE anr_id = ? AND uuid = ?',
            [$anrId, $uuid]
        );

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $scenario @return array<string, mixed> */
    private function detailFromRow(array $scenario): array
    {
        $scenarioId = (int) $scenario['id'];
        $events = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_events WHERE risk_scenario_id = ? ORDER BY created_at ASC, uuid ASC',
            [$scenarioId]
        );
        $consequences = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_consequences WHERE risk_scenario_id = ? ORDER BY created_at ASC, uuid ASC',
            [$scenarioId]
        );

        return $this->projectScenario($scenario) + [
            'riskSource' => $this->riskScenarioRecord('scenario_risk_sources', $scenarioId),
            'cause' => $this->riskScenarioRecord('scenario_causes', $scenarioId),
            'events' => array_map([$this, 'projectNode'], $events),
            'consequences' => array_map([$this, 'projectNode'], $consequences),
            'edges' => $this->edgeRows($scenarioId),
            'eventConsequences' => $this->eventConsequenceRows($scenarioId),
            'subjectLinks' => $this->linkRows(
                'scenario_subject_links',
                'subject-link',
                $scenarioId,
                (int) $scenario['anr_id']
            ),
            'controlLinks' => $this->linkRows(
                'scenario_control_links',
                'control-link',
                $scenarioId,
                (int) $scenario['anr_id']
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    private function riskScenarioRecord(string $table, int $scenarioId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . $table . ' WHERE risk_scenario_id = ?',
            [$scenarioId]
        );

        return $row === false ? null : $this->projectRecord($row);
    }

    /** @return array<int, array<string, mixed>> */
    private function edgeRows(int $scenarioId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT edge.*, from_event.uuid AS from_event_uuid, to_event.uuid AS to_event_uuid '
            . 'FROM scenario_event_edges edge '
            . 'INNER JOIN scenario_events from_event ON from_event.id = edge.from_event_id '
            . 'INNER JOIN scenario_events to_event ON to_event.id = edge.to_event_id '
            . 'WHERE edge.risk_scenario_id = ? ORDER BY edge.created_at ASC, edge.uuid ASC',
            [$scenarioId]
        );

        return array_map(fn (array $row): array => $this->projectRelation('edge', $row), $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function eventConsequenceRows(int $scenarioId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT relation.*, event.uuid AS event_uuid, consequence.uuid AS consequence_uuid '
            . 'FROM scenario_event_consequences relation '
            . 'INNER JOIN scenario_events event ON event.id = relation.event_id '
            . 'INNER JOIN scenario_consequences consequence ON consequence.id = relation.consequence_id '
            . 'WHERE relation.risk_scenario_id = ? ORDER BY relation.created_at ASC, relation.uuid ASC',
            [$scenarioId]
        );

        return array_map(fn (array $row): array => $this->projectRelation('event-consequence', $row), $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function linkRows(string $table, string $type, int $scenarioId, int $anrId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . $table . ' WHERE risk_scenario_id = ? ORDER BY created_at ASC, uuid ASC',
            [$scenarioId]
        );

        return array_map(
            fn (array $row): array => $this->projectRelation($type, $row, $anrId),
            $rows
        );
    }

    private function createsCycle(int $scenarioId, int $fromId, int $toId): bool
    {
        return ScenarioRiskGraph::createsCycle($fromId, $toId, function (int $id) use ($scenarioId): array {
            return array_map('intval', $this->connection->fetchFirstColumn(
                'SELECT to_event_id FROM scenario_event_edges WHERE risk_scenario_id = ? AND from_event_id = ?',
                [$scenarioId, $id]
            ));
        });
    }

    /** @param array<string, mixed> $scenario */
    private function validLinkTarget(int $anrId, array $scenario, string $type, string $uuid): bool
    {
        if ($type === 'risk_scenario') {
            return $uuid === $scenario['uuid'];
        }
        $table = $type === 'event' ? 'scenario_events' : ($type === 'consequence' ? 'scenario_consequences' : null);
        $target = $table === null ? null : $this->nodeRow($table, $anrId, $uuid);

        return $target !== null && (int) $target['risk_scenario_id'] === (int) $scenario['id'];
    }

    private function touchScenario(int $id, int $revision): void
    {
        if ($this->connection->update(
            'scenario_risk_scenarios',
            ['revision' => $revision + 1],
            ['id' => $id, 'revision' => $revision]
        ) !== 1) {
            throw new ScenarioRiskScenarioException('revision_conflict', 'Reload the risk scenario before retrying.');
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function scenarioColumns(array $data): array
    {
        return $this->recordColumns($data, [
            'title', 'sourceSnapshotUuid', 'sourceInstanceRecordUuid', 'description', 'summaryAddendum',
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function nodeColumns(array $data): array
    {
        return $this->recordColumns($data, ['title', 'description']);
    }

    /** @param array<string, mixed> $data @param array<int, string> $fields @return array<string, mixed> */
    private function recordColumns(array $data, array $fields): array
    {
        $columns = [];
        foreach ($fields as $field) {
            // Laminas input filters expose omitted optional fields as null.
            // A partial Description update must therefore not overwrite the
            // required title, while nullable description/source fields can
            // still be explicitly cleared.
            if (array_key_exists($field, $data) && !($field === 'title' && $data[$field] === null)) {
                $columns[$this->columnName($field)] = str_ends_with($field, 'Snapshot')
                    ? json_encode($data[$field], JSON_THROW_ON_ERROR)
                    : $data[$field];
            }
        }

        return $columns;
    }

    private function columnName(string $field): string
    {
        if ($field === 'legacyRiskSourceId') {
            return 'legacy_risk_source_uuid';
        }

        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $field));
    }

    private function generatedProvenance(string $uuid): string
    {
        return sprintf('risk_scenario:%s', $uuid);
    }

    private function nodeTable(string $type): ?string
    {
        return match ($type) {
            'event' => 'scenario_events',
            'consequence' => 'scenario_consequences',
            default => null,
        };
    }

    private function relationTable(string $type): ?string
    {
        return match ($type) {
            'edge' => 'scenario_event_edges',
            'event-consequence' => 'scenario_event_consequences',
            'subject-link' => 'scenario_subject_links',
            'control-link' => 'scenario_control_links',
            default => null,
        };
    }

    private function hasRiskScenarioRecord(string $table, int $scenarioId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM ' . $table . ' WHERE risk_scenario_id = ? LIMIT 1',
            [$scenarioId]
        );
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectScenario(array $row): array
    {
        return [
            'uuid' => $row['uuid'],
            'anrId' => (int) $row['anr_id'],
            'title' => $row['title'],
            'sourceSnapshotUuid' => $row['source_snapshot_uuid'],
            'sourceInstanceRecordUuid' => $row['source_instance_record_uuid'],
            'description' => $row['description'] ?? null,
            'summaryAddendum' => $row['summary_addendum'] ?? null,
            'revision' => (int) $row['revision'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectNode(array $row): array
    {
        return [
            'uuid' => $row['uuid'],
            'title' => $row['title'],
            'description' => $row['description'],
            'revision' => (int) $row['revision'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectRecord(array $row): array
    {
        $result = [
            'uuid' => $row['uuid'],
            'title' => $row['title'] ?? '',
            'description' => $row['description'],
            'revision' => (int) ($row['revision'] ?? 1),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
        if (array_key_exists('legacy_risk_source_uuid', $row)) {
            $result['legacyRiskSourceId'] = $row['legacy_risk_source_uuid'];
            $result['referenceSnapshot'] = $this->snapshot($row, 'reference_snapshot');
        }
        if (array_key_exists('threat_reference_uuid', $row)) {
            $result['threatReferenceUuid'] = $row['threat_reference_uuid'];
            $result['vulnerabilityReferenceUuid'] = $row['vulnerability_reference_uuid'];
            $result['threatReferenceSnapshot'] = $this->snapshot($row, 'threat_reference_snapshot');
            $result['vulnerabilityReferenceSnapshot'] = $this->snapshot($row, 'vulnerability_reference_snapshot');
        }

        return $result;
    }

    /** @param array<string, mixed> $row @return array<string, mixed>|null */
    private function snapshot(array $row, string $column): ?array
    {
        if (!isset($row[$column]) || $row[$column] === null) {
            return null;
        }

        return json_decode((string) $row[$column], true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectRelation(string $type, array $row, ?int $anrId = null): array
    {
        $result = ['uuid' => $row['uuid'], 'revision' => (int) ($row['revision'] ?? 1)];
        if ($type === 'edge') {
            return $result + ['fromEventUuid' => $row['from_event_uuid'], 'toEventUuid' => $row['to_event_uuid']];
        }
        if ($type === 'event-consequence') {
            return $result + ['eventUuid' => $row['event_uuid'], 'consequenceUuid' => $row['consequence_uuid']];
        }
        $result += [
            'targetType' => $row['target_type'],
            'targetUuid' => $row['target_uuid'],
            'relationshipIntent' => $row['relationship_intent'],
            'description' => $row['description'],
        ];
        if ($type === 'subject-link') {
            $result['subjectType'] = $row['subject_type'];
            $result['subjectReferenceUuid'] = $row['subject_reference_uuid'];
        } else {
            $result['controlReferenceUuid'] = $row['control_reference_uuid'];
        }
        $result['referenceSnapshot'] = $this->relationSnapshot($type, $row, $anrId);

        return $result;
    }

    /** Resolve legacy links that predate persisted, readable reference snapshots. */
    private function relationSnapshot(string $type, array $row, ?int $anrId): ?array
    {
        $snapshot = isset($row['reference_snapshot']) && $row['reference_snapshot'] !== null
            ? json_decode((string) $row['reference_snapshot'], true, 512, JSON_THROW_ON_ERROR)
            : null;
        $storedLabel = is_array($snapshot) ? trim((string) ($snapshot['label'] ?? '')) : '';
        if ($anrId === null || ($storedLabel !== '' && !self::isUuid($storedLabel))) {
            return is_array($snapshot) ? $snapshot : null;
        }

        $language = (int) $this->connection->fetchOne(
            'SELECT language FROM anrs WHERE id = ? LIMIT 1',
            [$anrId]
        );
        $language = $language >= 1 && $language <= 4 ? $language : 1;
        $labelField = 'label' . $language;
        $uuid = $type === 'subject-link'
            ? (string) ($row['subject_reference_uuid'] ?? '')
            : (string) ($row['control_reference_uuid'] ?? '');
        if ($uuid === '') {
            return is_array($snapshot) ? $snapshot : null;
        }

        if ($type === 'control-link') {
            $control = $this->connection->fetchAssociative(
                sprintf(
                    'SELECT measure.%1$s AS control_label, referential.%1$s AS referential_label '
                    . 'FROM measures measure LEFT JOIN referentials referential '
                    . 'ON referential.anr_id = measure.anr_id '
                    . 'AND referential.uuid = measure.referential_uuid '
                    . 'WHERE measure.anr_id = ? AND measure.uuid = ? LIMIT 1',
                    $labelField
                ),
                [$anrId, $uuid]
            );
            if ($control !== false) {
                $controlLabel = trim((string) ($control['control_label'] ?? ''));
                $referentialLabel = trim((string) ($control['referential_label'] ?? ''));
                if ($controlLabel !== '') {
                    return [
                        'kind' => 'control',
                        'identity' => $uuid,
                        'label' => $referentialLabel !== ''
                            ? $controlLabel . ' — ' . $referentialLabel
                            : $controlLabel,
                        'sourceScope' => 'current-analysis',
                    ];
                }
            }
        } else {
            $subjectType = (string) ($row['subject_type'] ?? 'asset');
            if ($subjectType === 'asset') {
                $asset = $this->connection->fetchAssociative(
                    sprintf(
                        'SELECT CONCAT('
                        . 'COALESCE(NULLIF(object.name%1$d, \'\'), '
                        . 'NULLIF(object.label%1$d, \'\'), \'Unlabelled asset\'), '
                        . 'IF(asset.uuid IS NULL, \'\', CONCAT(\' — \', '
                        . 'COALESCE(NULLIF(asset.label%1$d, \'\'), \'Unlabelled asset type\')))) AS label '
                        . 'FROM objects object LEFT JOIN assets asset '
                        . 'ON asset.anr_id = object.anr_id AND asset.uuid = object.asset_id '
                        . 'WHERE object.anr_id = ? AND object.uuid = ? AND object.asset_id IS NOT NULL LIMIT 1',
                        $language
                    ),
                    [$anrId, $uuid]
                );
                if ($asset !== false && trim((string) $asset['label']) !== '') {
                    return [
                        'kind' => 'asset',
                        'identity' => $uuid,
                        'label' => trim((string) $asset['label']),
                        'sourceScope' => 'current-analysis',
                    ];
                }
            }
            $object = $this->connection->fetchAssociative(
                sprintf(
                    'SELECT COALESCE(NULLIF(object.name%1$d, \'\'), '
                    . 'NULLIF(object.label%1$d, \'\'), \'Unlabelled reference\') AS label '
                    . 'FROM objects object WHERE object.anr_id = ? AND object.uuid = ? LIMIT 1',
                    $language
                ),
                [$anrId, $uuid]
            );
            if ($object !== false && trim((string) $object['label']) !== '') {
                return [
                    'kind' => $subjectType,
                    'identity' => $uuid,
                    'label' => trim((string) $object['label']),
                    'sourceScope' => 'current-analysis',
                ];
            }
        }

        return is_array($snapshot) ? $snapshot : null;
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function safeScenarioPatch(array $row): array
    {
        return [
            'title' => $row['title'],
            'sourceSnapshotUuid' => $row['source_snapshot_uuid'] ?? null,
            'sourceInstanceRecordUuid' => $row['source_instance_record_uuid'] ?? null,
            'description' => $row['description'] ?? null,
        ];
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
}
