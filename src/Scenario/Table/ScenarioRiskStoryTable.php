<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Exception\ScenarioRiskStoryException;
use Monarc\FrontOffice\Scenario\Service\ScenarioRiskGraph;
use Ramsey\Uuid\Uuid;

/** Persists ANR-scoped editable Scenario risk stories and causal relations. */
final class ScenarioRiskStoryTable
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
                'provenance' => $data['provenance'] ?? null,
            ];
            $this->connection->insert('scenario_risk_scenarios', $row);
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'risk_scenario_created', [
                'after' => $this->safeScenarioPatch($row),
            ]);

            return $this->detail($anrId, $uuid) ?? throw new ScenarioRiskStoryException(
                'write_failed',
                'The risk story could not be created.'
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
        return $this->saveSingleStoryRecord(
            'scenario_risk_sources',
            'risk_source',
            $anrId,
            $scenarioUuid,
            $actorId,
            $data,
            $revision,
            $correlationId,
            ['title', 'narrative', 'legacyRiskSourceId']
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
        return $this->saveSingleStoryRecord(
            'scenario_causes',
            'cause',
            $anrId,
            $scenarioUuid,
            $actorId,
            $data,
            $revision,
            $correlationId,
            ['title', 'narrative', 'threatReferenceUuid', 'vulnerabilityReferenceUuid']
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
                'narrative' => $data['narrative'] ?? null,
            ];
            $this->connection->insert($table, $row);
            $this->touchScenario((int) $scenario['id'], $revision);
            $result = $this->projectNode($this->nodeRow($table, $anrId, $uuid) ?: $row);
            $this->audit($anrId, $actorId, $correlationId, $uuid, $type . '_created', [
                'after' => $result,
            ]);

            return $result + ['scenarioRevision' => $revision + 1];
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
                return $this->projectNode($node) + ['scenarioRevision' => $revision];
            }
            $updates['revision'] = (int) $node['revision'] + 1;
            $this->connection->update($table, $updates, ['id' => $node['id']]);
            $this->touchScenario((int) $scenario['id'], $revision);
            $after = $this->nodeRow($table, $anrId, $uuid) ?: array_merge($node, $updates);
            $this->audit($anrId, $actorId, $correlationId, $uuid, $type . '_updated', [
                'before' => $this->projectNode($node),
                'after' => $this->projectNode($after),
            ]);

            return $this->projectNode($after) + ['scenarioRevision' => $revision + 1];
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
                default => throw new ScenarioRiskStoryException(
                    'invalid_relation',
                    'The Scenario relation is invalid.'
                ),
            };
            $this->touchScenario((int) $scenario['id'], $revision);
            $this->audit($anrId, $actorId, $correlationId, $result['uuid'], $type . '_created', [
                'after' => $result,
            ]);

            return $result + ['scenarioRevision' => $revision + 1];
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
    public function completeness(int $anrId): array
    {
        $stories = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_risk_scenarios WHERE anr_id = ? ORDER BY created_at ASC, uuid ASC',
            [$anrId]
        );
        $warnings = [];
        if ($stories === []) {
            $warnings[] = ['code' => 'risk_scenario_missing', 'message' => 'Create at least one risk story.'];
        }
        foreach ($stories as $story) {
            $storyId = (int) $story['id'];
            $prefix = ['riskScenarioUuid' => $story['uuid']];
            if (!$this->hasStoryRecord('scenario_risk_sources', $storyId)) {
                $warnings[] = $prefix + ['code' => 'risk_source_missing', 'message' => 'Add a local risk source.'];
            }
            if (!$this->hasStoryRecord('scenario_causes', $storyId)) {
                $warnings[] = $prefix + ['code' => 'cause_missing', 'message' => 'Add a local cause.'];
            }
            if (!$this->hasStoryRecord('scenario_events', $storyId)) {
                $warnings[] = $prefix + ['code' => 'event_missing', 'message' => 'Add at least one event.'];
            }
            if (!$this->hasStoryRecord('scenario_consequences', $storyId)) {
                $warnings[] = $prefix + ['code' => 'consequence_missing', 'message' => 'Add at least one consequence.'];
            }
            if (!$this->hasStoryRecord('scenario_event_consequences', $storyId)) {
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
            throw new ScenarioRiskStoryException('invalid_relation', 'Both events must belong to this risk story.');
        }
        if ($from['id'] === $to['id']) {
            throw new ScenarioRiskStoryException('self_edge', 'An event cannot cause itself.');
        }
        if ($this->createsCycle((int) $scenario['id'], (int) $from['id'], (int) $to['id'])) {
            throw new ScenarioRiskStoryException('cycle', 'The event relation would create a directed cycle.');
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
            throw new ScenarioRiskStoryException('duplicate_relation', 'This event relation already exists.');
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
            throw new ScenarioRiskStoryException(
                'invalid_relation',
                'The event and consequence must belong to this risk story.'
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
            throw new ScenarioRiskStoryException(
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
            throw new ScenarioRiskStoryException('invalid_target', 'The link target is not in this risk story.');
        }
        $uuid = Uuid::uuid4()->toString();
        $row = [
            'uuid' => $uuid,
            'anr_id' => $anrId,
            'risk_scenario_id' => $scenario['id'],
            'target_type' => $targetType,
            'target_uuid' => $targetUuid,
            'relationship_intent' => $data['relationshipIntent'] ?? null,
            'narrative' => $data['narrative'] ?? null,
        ];
        if ($table === 'scenario_subject_links') {
            $row['subject_type'] = $data['subjectType'];
            $row['subject_reference_uuid'] = $data['subjectReferenceUuid'];
        } else {
            $row['control_reference_uuid'] = $data['controlReferenceUuid'];
            $row['relationship_intent'] = $data['relationshipIntent'];
        }
        try {
            $this->connection->insert($table, $row);
        } catch (\Throwable) {
            throw new ScenarioRiskStoryException('duplicate_relation', 'This Scenario link already exists.');
        }

        return $this->projectRelation(
            $table === 'scenario_subject_links' ? 'subject-link' : 'control-link',
            $row
        );
    }

    /** @param array<string, mixed> $data @param array<int, string> $fields @return array<string, mixed>|null */
    private function saveSingleStoryRecord(
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

            return $result + ['scenarioRevision' => $revision + 1];
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
            'riskSource' => $this->storyRecord('scenario_risk_sources', $scenarioId),
            'cause' => $this->storyRecord('scenario_causes', $scenarioId),
            'events' => array_map([$this, 'projectNode'], $events),
            'consequences' => array_map([$this, 'projectNode'], $consequences),
            'edges' => $this->edgeRows($scenarioId),
            'eventConsequences' => $this->eventConsequenceRows($scenarioId),
            'subjectLinks' => $this->linkRows('scenario_subject_links', 'subject-link', $scenarioId),
            'controlLinks' => $this->linkRows('scenario_control_links', 'control-link', $scenarioId),
        ];
    }

    /** @return array<string, mixed>|null */
    private function storyRecord(string $table, int $scenarioId): ?array
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
    private function linkRows(string $table, string $type, int $scenarioId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . $table . ' WHERE risk_scenario_id = ? ORDER BY created_at ASC, uuid ASC',
            [$scenarioId]
        );

        return array_map(fn (array $row): array => $this->projectRelation($type, $row), $rows);
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
            throw new ScenarioRiskStoryException('revision_conflict', 'Reload the risk story before retrying.');
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function scenarioColumns(array $data): array
    {
        return $this->recordColumns($data, [
            'title', 'sourceSnapshotUuid', 'sourceInstanceRecordUuid', 'provenance',
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function nodeColumns(array $data): array
    {
        return $this->recordColumns($data, ['title', 'narrative']);
    }

    /** @param array<string, mixed> $data @param array<int, string> $fields @return array<string, mixed> */
    private function recordColumns(array $data, array $fields): array
    {
        $columns = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $columns[$this->columnName($field)] = $data[$field];
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

    private function hasStoryRecord(string $table, int $scenarioId): bool
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
            'provenance' => $row['provenance'],
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
            'narrative' => $row['narrative'],
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
            'title' => $row['title'],
            'narrative' => $row['narrative'],
            'revision' => (int) ($row['revision'] ?? 1),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
        if (array_key_exists('legacy_risk_source_uuid', $row)) {
            $result['legacyRiskSourceId'] = $row['legacy_risk_source_uuid'];
        }
        if (array_key_exists('threat_reference_uuid', $row)) {
            $result['threatReferenceUuid'] = $row['threat_reference_uuid'];
            $result['vulnerabilityReferenceUuid'] = $row['vulnerability_reference_uuid'];
        }

        return $result;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectRelation(string $type, array $row): array
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
            'narrative' => $row['narrative'],
        ];
        if ($type === 'subject-link') {
            $result['subjectType'] = $row['subject_type'];
            $result['subjectReferenceUuid'] = $row['subject_reference_uuid'];
        } else {
            $result['controlReferenceUuid'] = $row['control_reference_uuid'];
        }

        return $result;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function safeScenarioPatch(array $row): array
    {
        return [
            'title' => $row['title'],
            'sourceSnapshotUuid' => $row['source_snapshot_uuid'] ?? null,
            'sourceInstanceRecordUuid' => $row['source_instance_record_uuid'] ?? null,
            'provenance' => $row['provenance'] ?? null,
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
