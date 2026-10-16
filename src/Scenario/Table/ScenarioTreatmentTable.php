<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Ramsey\Uuid\Uuid;

/** Persists the mutable Scenario treatment plan and its append-only implementation evidence. */
final class ScenarioTreatmentTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @return array<string, mixed>|null */
    public function get(int $anrId, string $riskScenarioUuid, string $treatmentUuid): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT t.*, s.uuid AS risk_scenario_uuid, u.firstname AS owner_firstname, '
            . 'u.lastname AS owner_lastname, u.email AS owner_email '
            . 'FROM scenario_treatments t '
            . 'INNER JOIN scenario_risk_scenarios s ON s.id = t.risk_scenario_id '
            . 'LEFT JOIN users u ON u.id = t.owner_id '
            . 'WHERE t.anr_id = ? AND s.uuid = ? AND t.uuid = ?',
            [$anrId, $riskScenarioUuid, $treatmentUuid]
        );

        return $row === false ? null : $this->projectTreatment($row);
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function list(int $anrId, string $riskScenarioUuid): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.*, s.uuid AS risk_scenario_uuid, u.firstname AS owner_firstname, '
            . 'u.lastname AS owner_lastname, u.email AS owner_email '
            . 'FROM scenario_treatments t '
            . 'INNER JOIN scenario_risk_scenarios s ON s.id = t.risk_scenario_id '
            . 'LEFT JOIN users u ON u.id = t.owner_id '
            . 'WHERE t.anr_id = ? AND s.uuid = ? ORDER BY t.created_at ASC, t.id ASC',
            [$anrId, $riskScenarioUuid]
        );

        return ['items' => array_map([$this, 'projectTreatment'], $rows)];
    }

    /**
     * A proposed residual assessment can only be applied after every recorded treatment
     * and every selected recommendation implementation reached a terminal state.
     */
    public function isRiskReductionEligible(int $anrId, string $riskScenarioUuid): bool
    {
        $riskScenarioId = $this->connection->fetchOne(
            'SELECT id FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ?',
            [$anrId, $riskScenarioUuid]
        );
        if ($riskScenarioId === false) {
            return false;
        }

        $treatmentCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scenario_treatments WHERE anr_id = ? AND risk_scenario_id = ?',
            [$anrId, (int) $riskScenarioId]
        );
        if ($treatmentCount === 0) {
            return false;
        }

        $unfinishedTreatments = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM scenario_treatments "
            . "WHERE anr_id = ? AND risk_scenario_id = ? AND status NOT IN ('completed', 'cancelled')",
            [$anrId, (int) $riskScenarioId]
        );
        if ($unfinishedTreatments > 0) {
            return false;
        }

        $unfinishedRecommendations = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scenario_treatment_recommendation_implementations i '
            . 'INNER JOIN scenario_treatments t ON t.id = i.treatment_id '
            . "WHERE t.anr_id = ? AND t.risk_scenario_id = ? AND i.status NOT IN ('implemented', 'not_applicable')",
            [$anrId, (int) $riskScenarioId]
        );

        return $unfinishedRecommendations === 0;
    }

    /** @return array<string, mixed>|null */
    public function getImplementation(
        int $anrId,
        string $riskScenarioUuid,
        string $treatmentUuid,
        string $implementationUuid
    ): ?array {
        $row = $this->connection->fetchAssociative(
            'SELECT i.*, r.recommendation_uuid, r.recommendation_snapshot, t.uuid AS treatment_uuid, '
            . 'u.firstname AS owner_firstname, u.lastname AS owner_lastname, u.email AS owner_email '
            . 'FROM scenario_treatment_recommendation_implementations i '
            . 'INNER JOIN scenario_treatments t ON t.id = i.treatment_id '
            . 'INNER JOIN scenario_risk_scenarios s ON s.id = t.risk_scenario_id '
            . 'INNER JOIN scenario_treatment_recommendations r ON r.id = i.treatment_recommendation_id '
            . 'LEFT JOIN users u ON u.id = i.owner_id '
            . 'WHERE i.anr_id = ? AND s.uuid = ? AND t.uuid = ? AND i.uuid = ?',
            [$anrId, $riskScenarioUuid, $treatmentUuid, $implementationUuid]
        );

        return $row === false ? null : $this->projectImplementation($row);
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function history(int $anrId, string $riskScenarioUuid, string $treatmentUuid): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT h.*, i.uuid AS implementation_uuid, r.recommendation_snapshot, '
            . 'actor.firstname AS actor_firstname, actor.lastname AS actor_lastname, actor.email AS actor_email '
            . 'FROM scenario_treatment_implementation_history h '
            . 'INNER JOIN scenario_treatments t ON t.id = h.treatment_id '
            . 'INNER JOIN scenario_risk_scenarios s ON s.id = t.risk_scenario_id '
            . 'LEFT JOIN scenario_treatment_recommendation_implementations i ON i.id = h.implementation_id '
            . 'LEFT JOIN scenario_treatment_recommendations r ON r.id = i.treatment_recommendation_id '
            . 'LEFT JOIN users actor ON actor.id = h.actor_id '
            . 'WHERE h.anr_id = ? AND s.uuid = ? AND t.uuid = ? '
            . 'ORDER BY h.created_at DESC, h.id DESC',
            [$anrId, $riskScenarioUuid, $treatmentUuid]
        );

        return ['items' => array_map([$this, 'projectHistory'], $rows)];
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function updateTreatment(
        int $anrId,
        string $riskScenarioUuid,
        string $treatmentUuid,
        array $data,
        int $riskScenarioRevision,
        int $treatmentRevision,
        int $actorId,
        string $correlationId,
        string $idempotencyKey
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $riskScenarioUuid,
            $treatmentUuid,
            $data,
            $riskScenarioRevision,
            $treatmentRevision,
            $actorId,
            $correlationId,
            $idempotencyKey
        ): ?array {
            $locked = $this->lockedTreatment($anrId, $riskScenarioUuid, $treatmentUuid);
            if ($locked === null) {
                return null;
            }
            $existing = $this->idempotentResult((int) $locked['id'], $idempotencyKey);
            if ($existing !== null) {
                return $this->get($anrId, $riskScenarioUuid, $treatmentUuid) + ['idempotent' => true];
            }
            if ((int) $locked['risk_scenario_revision'] !== $riskScenarioRevision
                || (int) $locked['revision'] !== $treatmentRevision) {
                return null;
            }
            if (in_array($locked['status'], ['completed', 'cancelled'], true)) {
                throw new \LogicException(
                    'Completed and cancelled treatments are historical records and cannot be changed.'
                );
            }

            $after = $this->mergeTreatment($locked, $data);
            $this->assertTreatmentTerminalState((int) $locked['id'], $after);
            $updates = $this->treatmentUpdates($after, $locked);
            $updates['revision'] = $treatmentRevision + 1;
            $this->connection->update('scenario_treatments', $updates, ['id' => $locked['id']]);
            $this->touchRiskScenario((int) $locked['risk_scenario_id'], $riskScenarioRevision);
            $this->historyEntry(
                $anrId,
                (int) $locked['id'],
                null,
                $actorId,
                $data['ownerSnapshot'] ?? $this->ownerSnapshot($after),
                'scenario_treatment_updated',
                $this->treatmentSnapshot($locked),
                $this->treatmentSnapshot($after),
                $idempotencyKey
            );
            $this->audit($anrId, $actorId, $correlationId, $treatmentUuid, 'scenario_treatment_updated', [
                'before' => $this->treatmentSnapshot($locked),
                'after' => $this->treatmentSnapshot($after),
            ]);

            return $this->get($anrId, $riskScenarioUuid, $treatmentUuid)
                + ['riskScenarioRevision' => $riskScenarioRevision + 1];
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function updateImplementation(
        int $anrId,
        string $riskScenarioUuid,
        string $treatmentUuid,
        string $implementationUuid,
        array $data,
        int $riskScenarioRevision,
        int $treatmentRevision,
        int $actorId,
        string $correlationId,
        string $idempotencyKey
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $riskScenarioUuid,
            $treatmentUuid,
            $implementationUuid,
            $data,
            $riskScenarioRevision,
            $treatmentRevision,
            $actorId,
            $correlationId,
            $idempotencyKey
        ): ?array {
            $locked = $this->lockedTreatment($anrId, $riskScenarioUuid, $treatmentUuid);
            if ($locked === null) {
                return null;
            }
            $existing = $this->idempotentResult((int) $locked['id'], $idempotencyKey);
            if ($existing !== null) {
                return $this->getImplementation($anrId, $riskScenarioUuid, $treatmentUuid, $implementationUuid)
                    + ['idempotent' => true];
            }
            if ((int) $locked['risk_scenario_revision'] !== $riskScenarioRevision
                || (int) $locked['revision'] !== $treatmentRevision) {
                return null;
            }
            if (in_array($locked['status'], ['completed', 'cancelled'], true)) {
                throw new \LogicException(
                    'Recommendation implementation cannot change after the treatment is terminal.'
                );
            }
            $implementation = $this->connection->fetchAssociative(
                'SELECT i.* FROM scenario_treatment_recommendation_implementations i '
                . 'WHERE i.anr_id = ? AND i.treatment_id = ? AND i.uuid = ? FOR UPDATE',
                [$anrId, $locked['id'], $implementationUuid]
            );
            if ($implementation === false) {
                return null;
            }
            if (in_array($implementation['status'], ['implemented', 'not_applicable'], true)) {
                throw new \LogicException(
                    'Implemented and not-applicable recommendation evidence is historical and cannot be changed.'
                );
            }
            $after = $this->mergeImplementation($implementation, $data, $actorId);
            $this->assertImplementationTerminalState($after);
            $updates = $this->implementationUpdates($after, $implementation);
            $updates['revision'] = (int) $implementation['revision'] + 1;
            $this->connection->update(
                'scenario_treatment_recommendation_implementations',
                $updates,
                ['id' => $implementation['id']]
            );
            $this->connection->update(
                'scenario_treatments',
                ['revision' => $treatmentRevision + 1],
                ['id' => $locked['id']]
            );
            $this->touchRiskScenario((int) $locked['risk_scenario_id'], $riskScenarioRevision);
            $this->historyEntry(
                $anrId,
                (int) $locked['id'],
                (int) $implementation['id'],
                $actorId,
                $data['ownerSnapshot'] ?? $this->ownerSnapshot($after),
                'scenario_treatment_recommendation_implementation_updated',
                $this->implementationSnapshot($implementation),
                $this->implementationSnapshot($after),
                $idempotencyKey
            );
            $this->audit(
                $anrId,
                $actorId,
                $correlationId,
                $implementationUuid,
                'scenario_treatment_recommendation_implementation_updated',
                [
                    'before' => $this->implementationSnapshot($implementation),
                    'after' => $this->implementationSnapshot($after),
                ]
            );

            return $this->getImplementation($anrId, $riskScenarioUuid, $treatmentUuid, $implementationUuid)
                + ['treatmentRevision' => $treatmentRevision + 1, 'riskScenarioRevision' => $riskScenarioRevision + 1];
        });
    }

    /** @return array<string, mixed>|null */
    private function lockedTreatment(int $anrId, string $riskScenarioUuid, string $treatmentUuid): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT t.*, s.id AS risk_scenario_id, s.revision AS risk_scenario_revision '
            . 'FROM scenario_treatments t INNER JOIN scenario_risk_scenarios s ON s.id = t.risk_scenario_id '
            . 'WHERE t.anr_id = ? AND s.uuid = ? AND t.uuid = ? FOR UPDATE',
            [$anrId, $riskScenarioUuid, $treatmentUuid]
        );

        return $row === false ? null : $row;
    }

    private function idempotentResult(int $treatmentId, string $idempotencyKey): ?int
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM scenario_treatment_implementation_history WHERE treatment_id = ? AND idempotency_key = ?',
            [$treatmentId, $idempotencyKey]
        );

        return $id === false ? null : (int) $id;
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $data @return array<string, mixed> */
    private function mergeTreatment(array $current, array $data): array
    {
        $fields = [
            'treatment_type' => 'treatmentType',
            'action_text' => 'actionText',
            'owner_id' => 'ownerId',
            'due_at' => 'dueAt',
            'status' => 'status',
            'completion_evidence' => 'completionEvidence',
            'cancellation_rationale' => 'cancellationRationale',
        ];
        foreach ($fields as $column => $field) {
            if (array_key_exists($field, $data)) {
                $current[$column] = $data[$field];
            }
        }

        return $current;
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $data @return array<string, mixed> */
    private function mergeImplementation(array $current, array $data, int $actorId): array
    {
        $previousStatus = $current['status'];
        $fields = [
            'status' => 'status',
            'owner_id' => 'ownerId',
            'target_at' => 'targetAt',
            'working_note' => 'workingNote',
            'completion_evidence' => 'completionEvidence',
            'not_applicable_rationale' => 'notApplicableRationale',
        ];
        foreach ($fields as $column => $field) {
            if (array_key_exists($field, $data)) {
                $current[$column] = $data[$field];
            }
        }
        if ($current['status'] === 'implemented' && $previousStatus !== 'implemented') {
            $current['implemented_at'] = gmdate('Y-m-d H:i:s');
            $current['implemented_by'] = $actorId;
        }

        return $current;
    }

    /** @param array<string, mixed> $treatment */
    private function assertTreatmentTerminalState(int $treatmentId, array $treatment): void
    {
        if ($treatment['status'] === 'cancelled' && trim((string) $treatment['cancellation_rationale']) === '') {
            throw new \InvalidArgumentException('Cancelling a treatment requires a cancellation rationale.');
        }
        if ($treatment['status'] !== 'completed') {
            return;
        }
        $recommendations = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scenario_treatment_recommendation_implementations WHERE treatment_id = ?',
            [$treatmentId]
        );
        if ($recommendations === 0 && trim((string) $treatment['completion_evidence']) === '') {
            throw new \InvalidArgumentException(
                'Completing a treatment without recommendations requires completion evidence.'
            );
        }
        if ($recommendations > 0 && (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM scenario_treatment_recommendation_implementations "
            . "WHERE treatment_id = ? AND (status NOT IN ('implemented', 'not_applicable') "
            . "OR (status = 'not_applicable' AND (not_applicable_rationale IS NULL "
            . "OR TRIM(not_applicable_rationale) = '')))",
            [$treatmentId]
        ) > 0) {
            throw new \InvalidArgumentException(
                'Complete or mark every selected recommendation not applicable before completing this treatment.'
            );
        }
    }

    /** @param array<string, mixed> $implementation */
    private function assertImplementationTerminalState(array $implementation): void
    {
        if ($implementation['status'] === 'implemented'
            && trim((string) $implementation['completion_evidence']) === '') {
            throw new \InvalidArgumentException('Implemented recommendations require completion evidence.');
        }
        if ($implementation['status'] === 'not_applicable'
            && trim((string) $implementation['not_applicable_rationale']) === '') {
            throw new \InvalidArgumentException('Not-applicable recommendations require a rationale.');
        }
    }

    /** @param array<string, mixed> $after @param array<string, mixed> $before @return array<string, mixed> */
    private function treatmentUpdates(array $after, array $before): array
    {
        $updates = [];
        foreach ([
            'treatment_type',
            'action_text',
            'owner_id',
            'due_at',
            'status',
            'completion_evidence',
            'cancellation_rationale',
        ] as $field) {
            if ($after[$field] !== $before[$field]) {
                $updates[$field] = $after[$field];
            }
        }

        return $updates;
    }

    /** @param array<string, mixed> $after @param array<string, mixed> $before @return array<string, mixed> */
    private function implementationUpdates(array $after, array $before): array
    {
        $updates = [];
        foreach ([
            'status', 'owner_id', 'target_at', 'working_note', 'completion_evidence', 'not_applicable_rationale',
            'implemented_by', 'implemented_at',
        ] as $field) {
            if ($after[$field] !== $before[$field]) {
                $updates[$field] = $after[$field];
            }
        }

        return $updates;
    }

    private function touchRiskScenario(int $riskScenarioId, int $revision): void
    {
        $this->connection->update('scenario_risk_scenarios', ['revision' => $revision + 1], ['id' => $riskScenarioId]);
    }

    /** @param array<string, mixed>|null $owner @param array<string, mixed> $before @param array<string, mixed> $after */
    private function historyEntry(
        int $anrId,
        int $treatmentId,
        ?int $implementationId,
        int $actorId,
        ?array $owner,
        string $action,
        array $before,
        array $after,
        string $idempotencyKey
    ): void {
        $this->connection->insert('scenario_treatment_implementation_history', [
            'uuid' => Uuid::uuid4()->toString(),
            'anr_id' => $anrId,
            'treatment_id' => $treatmentId,
            'implementation_id' => $implementationId,
            'actor_id' => $actorId,
            'owner_snapshot' => $owner === null ? null : json_encode($owner, JSON_THROW_ON_ERROR),
            'action' => $action,
            'before_snapshot' => json_encode($before, JSON_THROW_ON_ERROR),
            'after_snapshot' => json_encode($after, JSON_THROW_ON_ERROR),
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /** @param array<string, mixed> $row @return array<string, mixed>|null */
    private function ownerSnapshot(array $row): ?array
    {
        if (!isset($row['owner_id']) || $row['owner_id'] === null) {
            return null;
        }

        $owner = $this->connection->fetchAssociative(
            'SELECT firstname, lastname, email FROM users WHERE id = ?',
            [(int) $row['owner_id']]
        );
        if ($owner === false) {
            return ['id' => (int) $row['owner_id'], 'name' => 'User #' . (int) $row['owner_id'], 'email' => null];
        }

        return [
            'id' => (int) $row['owner_id'],
            'name' => trim((string) $owner['firstname'] . ' ' . (string) $owner['lastname'])
                ?: (string) $owner['email'],
            'email' => $owner['email'] === null ? null : (string) $owner['email'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function treatmentSnapshot(array $row): array
    {
        return [
            'treatmentType' => (string) $row['treatment_type'],
            'actionText' => (string) $row['action_text'],
            'ownerId' => $row['owner_id'] === null ? null : (int) $row['owner_id'],
            'dueAt' => $row['due_at'],
            'status' => (string) $row['status'],
            'completionEvidence' => $row['completion_evidence'],
            'cancellationRationale' => $row['cancellation_rationale'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function implementationSnapshot(array $row): array
    {
        return [
            'status' => (string) $row['status'],
            'ownerId' => $row['owner_id'] === null ? null : (int) $row['owner_id'],
            'targetAt' => $row['target_at'],
            'workingNote' => $row['working_note'],
            'completionEvidence' => $row['completion_evidence'],
            'notApplicableRationale' => $row['not_applicable_rationale'],
            'implementedBy' => $row['implemented_by'] === null ? null : (int) $row['implemented_by'],
            'implementedAt' => $row['implemented_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectTreatment(array $row): array
    {
        return [
            'uuid' => (string) $row['uuid'],
            'revision' => (int) $row['revision'],
            'treatmentType' => (string) $row['treatment_type'],
            'actionText' => (string) $row['action_text'],
            'ownerId' => $row['owner_id'] === null ? null : (int) $row['owner_id'],
            'owner' => $row['owner_id'] === null ? null : [
                'id' => (int) $row['owner_id'],
                'name' => trim((string) $row['owner_firstname'] . ' ' . (string) $row['owner_lastname']),
                'email' => $row['owner_email'] === null ? null : (string) $row['owner_email'],
            ],
            'dueAt' => $row['due_at'],
            'status' => (string) $row['status'],
            'completionEvidence' => $row['completion_evidence'],
            'cancellationRationale' => $row['cancellation_rationale'],
            'recommendations' => $this->implementations((int) $row['id']),
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function implementations(int $treatmentId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT i.*, r.recommendation_uuid, r.recommendation_snapshot, '
            . 'u.firstname AS owner_firstname, u.lastname AS owner_lastname, u.email AS owner_email '
            . 'FROM scenario_treatment_recommendation_implementations i '
            . 'INNER JOIN scenario_treatment_recommendations r ON r.id = i.treatment_recommendation_id '
            . 'LEFT JOIN users u ON u.id = i.owner_id '
            . 'WHERE i.treatment_id = ? ORDER BY r.id ASC',
            [$treatmentId]
        );

        $implementations = array_map([$this, 'projectImplementation'], $rows);
        usort($implementations, static function (array $left, array $right): int {
            $importance = (int) $right['treatmentImportance'] <=> (int) $left['treatmentImportance'];
            if ($importance !== 0) {
                return $importance;
            }

            $sourceImportance = (int) $right['importance'] <=> (int) $left['importance'];
            if ($sourceImportance !== 0) {
                return $sourceImportance;
            }

            $leftPosition = (int) $left['position'];
            $rightPosition = (int) $right['position'];
            if ($leftPosition === 0) {
                $leftPosition = PHP_INT_MAX;
            }
            if ($rightPosition === 0) {
                $rightPosition = PHP_INT_MAX;
            }

            return $leftPosition <=> $rightPosition;
        });

        return $implementations;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectImplementation(array $row): array
    {
        $snapshot = json_decode((string) $row['recommendation_snapshot'], true, 512, JSON_THROW_ON_ERROR);

        return [
            'uuid' => (string) $row['uuid'],
            'revision' => (int) $row['revision'],
            'recommendationUuid' => (string) $row['recommendation_uuid'],
            'code' => (string) ($snapshot['code'] ?? ''),
            'description' => (string) ($snapshot['description'] ?? ''),
            'importance' => (int) ($snapshot['importance'] ?? 0),
            'position' => (int) ($snapshot['position'] ?? 0),
            'treatmentImportance' => (int) ($snapshot['treatmentImportance'] ?? $snapshot['importance'] ?? 0),
            'recommendationSetUuid' => $snapshot['recommendationSetUuid'] ?? null,
            'recommendationSetLabel' => (string) ($snapshot['recommendationSetLabel'] ?? ''),
            'status' => (string) $row['status'],
            'ownerId' => $row['owner_id'] === null ? null : (int) $row['owner_id'],
            'owner' => $row['owner_id'] === null ? null : [
                'id' => (int) $row['owner_id'],
                'name' => trim((string) ($row['owner_firstname'] ?? '') . ' ' . (string) ($row['owner_lastname'] ?? ''))
                    ?: (string) ($row['owner_email'] ?? ''),
                'email' => ($row['owner_email'] ?? null) === null ? null : (string) $row['owner_email'],
            ],
            'targetAt' => $row['target_at'],
            'workingNote' => $row['working_note'],
            'completionEvidence' => $row['completion_evidence'],
            'notApplicableRationale' => $row['not_applicable_rationale'],
            'implementedBy' => $row['implemented_by'] === null ? null : (int) $row['implemented_by'],
            'implementedAt' => $row['implemented_at'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectHistory(array $row): array
    {
        $recommendation = $row['recommendation_snapshot'] === null
            ? null
            : json_decode((string) $row['recommendation_snapshot'], true, 512, JSON_THROW_ON_ERROR);

        return [
            'uuid' => (string) $row['uuid'],
            'action' => (string) $row['action'],
            'implementationUuid' => $row['implementation_uuid'] === null ? null : (string) $row['implementation_uuid'],
            'recommendation' => $recommendation === null ? null : [
                'code' => $recommendation['code'] ?? '',
                'description' => $recommendation['description'] ?? '',
                'recommendationSetLabel' => $recommendation['recommendationSetLabel'] ?? '',
            ],
            'actor' => [
                'name' => trim((string) ($row['actor_firstname'] ?? '') . ' ' . (string) ($row['actor_lastname'] ?? ''))
                    ?: ((isset($row['actor_email']) && $row['actor_email'] !== null)
                        ? (string) $row['actor_email']
                        : 'User #' . (int) $row['actor_id']),
                'email' => $row['actor_email'] === null ? null : (string) $row['actor_email'],
            ],
            'owner' => $row['owner_snapshot'] === null
                ? null
                : json_decode((string) $row['owner_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            'before' => $row['before_snapshot'] === null
                ? null
                : json_decode((string) $row['before_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            'after' => json_decode((string) $row['after_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            'createdAt' => (string) $row['created_at'],
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
            'justification' => null,
        ]);
    }
}
