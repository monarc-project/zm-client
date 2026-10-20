<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Service\ScenarioReviewSchedule;
use Ramsey\Uuid\Uuid;

/** Persists ANR-scoped, append-only Scenario review cases and trigger occurrences. */
final class ScenarioReviewTable
{
    private const OPEN_STATUSES = ['open', 'in_progress', 'awaiting_decision'];

    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @return array<string, mixed> */
    public function summary(int $anrId, ScenarioReviewSchedule $schedule, ?DateTimeInterface $now = null): array
    {
        $analysis = $this->analysis($anrId);
        if ($analysis === null) {
            throw new \InvalidArgumentException('Scenario analysis was not found.');
        }
        $projection = $schedule->calculate(
            $analysis['reassessment_last_review_date'] === null
                ? null
                : (string) $analysis['reassessment_last_review_date'],
            $analysis['reassessment_review_frequency'] === null
                ? null
                : (string) $analysis['reassessment_review_frequency'],
            $now
        );
        $activeCases = $this->connection->fetchAllAssociative(
            'SELECT uuid, reason, status, scope_type, scheduled_for, occurrence_context_at, revision, '
            . 'created_at, updated_at '
            . "FROM scenario_review_cases WHERE anr_id = ? AND status IN ('open', 'in_progress', 'awaiting_decision') "
            . 'ORDER BY created_at DESC, id DESC',
            [$anrId]
        );

        return $projection + [
            'activeCases' => array_map([$this, 'projectCaseSummary'], $activeCases),
            'acceptanceStaleness' => $this->acceptanceStaleness($anrId),
        ];
    }

    public function synchronizeProjection(
        int $anrId,
        ScenarioReviewSchedule $schedule,
        ?DateTimeInterface $now = null
    ): void {
        $analysis = $this->analysis($anrId);
        if ($analysis === null) {
            return;
        }
        $projection = $schedule->calculate(
            $analysis['reassessment_last_review_date'] === null
                ? null
                : (string) $analysis['reassessment_last_review_date'],
            $analysis['reassessment_review_frequency'] === null
                ? null
                : (string) $analysis['reassessment_review_frequency'],
            $now
        );
        $nextReviewAt = $projection['nextReviewAt'] === null
            ? null
            : $this->databaseDate($projection['nextReviewAt']);
        $this->connection->update('scenario_analyses', ['next_review_at' => $nextReviewAt], ['anr_id' => $anrId]);
    }

    /** @param callable():void $operation */
    public function transactionally(callable $operation): void
    {
        $this->connection->transactional($operation);
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listCases(int $anrId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_review_cases WHERE anr_id = ? ORDER BY created_at DESC, id DESC',
            [$anrId]
        );

        return ['items' => array_map(fn (array $row): array => $this->projectCase($row), $rows)];
    }

    /** @return array<string, mixed>|null */
    public function case(int $anrId, string $uuid): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scenario_review_cases WHERE anr_id = ? AND uuid = ?',
            [$anrId, $uuid]
        );

        return $row === false ? null : $this->projectCase($row);
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function createCase(
        int $anrId,
        array $data,
        int $actorId,
        string $correlationId,
        ScenarioReviewSchedule $schedule,
        ?DateTimeInterface $now = null
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $data,
            $actorId,
            $correlationId,
            $schedule,
            $now
        ): ?array {
            $existing = $this->idempotentCase($anrId, (string) $data['idempotencyKey']);
            if ($existing !== null) {
                return $this->case($anrId, (string) $existing['uuid']) + ['idempotent' => true];
            }

            $case = $this->createCaseLocked($anrId, $data, $actorId, $correlationId, $schedule, $now);

            return $case === null ? null : $this->case($anrId, (string) $case['uuid']);
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function recordTriggerOccurrence(
        int $anrId,
        array $data,
        int $actorId,
        string $correlationId,
        ScenarioReviewSchedule $schedule,
        ?DateTimeInterface $now = null
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $data,
            $actorId,
            $correlationId,
            $schedule,
            $now
        ): ?array {
            $existing = $this->connection->fetchAssociative(
                'SELECT * FROM scenario_review_trigger_occurrences WHERE anr_id = ? AND idempotency_key = ? FOR UPDATE',
                [$anrId, $data['idempotencyKey']]
            );
            if ($existing !== false) {
                return $this->projectOccurrence($existing) + ['idempotent' => true];
            }

            $trigger = $this->connection->fetchAssociative(
                'SELECT * FROM anr_reassessment_triggers WHERE anr_id = ? AND id = ? FOR UPDATE',
                [$anrId, $data['triggerId']]
            );
            if ($trigger === false || (int) $trigger['is_active'] !== 1) {
                throw new \InvalidArgumentException('Select an active Context Establishment trigger.');
            }
            if (array_key_exists('sourceObservationUuid', $data)) {
                $observation = $this->connection->fetchOne(
                    'SELECT 1 FROM scenario_monitoring_observations WHERE anr_id = ? AND uuid = ? '
                    . 'AND reassessment_trigger_id = ? LIMIT 1',
                    [$anrId, $data['sourceObservationUuid'], $trigger['id']]
                );
                if ($observation === false) {
                    throw new \InvalidArgumentException(
                        'The source monitoring observation is unavailable for this trigger.'
                    );
                }
            }

            $riskScenarioUuids = $data['riskScenarioUuids'];
            $riskScenarioIds = $this->riskScenarioIds($anrId, $riskScenarioUuids);
            if (!$data['analysisWide'] && count($riskScenarioIds) !== count($riskScenarioUuids)) {
                throw new \InvalidArgumentException('A selected risk scenario is unavailable in this analysis.');
            }
            $case = $this->compatibleOpenCase($anrId, $riskScenarioIds, (bool) $data['analysisWide']);
            if ($case === null) {
                $case = $this->createCaseLocked($anrId, [
                    'reason' => 'trigger',
                    'analysisWide' => (bool) $data['analysisWide'],
                    'riskScenarioUuids' => $riskScenarioUuids,
                    'occurrenceAt' => $data['occurredAt'],
                    'idempotencyKey' => Uuid::uuid4()->toString(),
                    'triggerOccurrence' => [
                        'trigger' => $this->triggerSnapshot($trigger),
                        'occurredAt' => $data['occurredAt'],
                        'sourceObservationUuid' => $data['sourceObservationUuid'] ?? null,
                        'sourceText' => $data['sourceText'] ?? null,
                        'triageRationale' => $data['triageRationale'],
                    ],
                ], $actorId, $correlationId, $schedule, $now);
                if ($case === null) {
                    return null;
                }
            }

            $uuid = Uuid::uuid4()->toString();
            $this->connection->insert('scenario_review_trigger_occurrences', [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'review_case_id' => (int) $case['id'],
                'reassessment_trigger_id' => (int) $trigger['id'],
                'trigger_snapshot' => json_encode($this->triggerSnapshot($trigger), JSON_THROW_ON_ERROR),
                'occurred_at' => $this->databaseDate((string) $data['occurredAt']),
                'reporter_id' => $actorId,
                'source_observation_uuid' => $data['sourceObservationUuid'] ?? null,
                'source_text' => $data['sourceText'] ?? null,
                'triage_rationale' => $data['triageRationale'],
                'idempotency_key' => $data['idempotencyKey'],
            ]);
            $occurrence = $this->connection->fetchAssociative(
                'SELECT * FROM scenario_review_trigger_occurrences WHERE uuid = ?',
                [$uuid]
            );
            $this->history(
                $anrId,
                (int) $case['id'],
                $actorId,
                'trigger_occurrence_recorded',
                null,
                (string) $case['status'],
                $data['triageRationale'],
                ['riskScenarioUuids' => $riskScenarioUuids],
                ['occurrenceUuid' => $uuid, 'triggerId' => (int) $trigger['id']],
                $data['idempotencyKey']
            );
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'scenario_review_trigger_recorded', [
                'reviewCaseUuid' => $case['uuid'],
                'triggerId' => (int) $trigger['id'],
            ]);

            return $occurrence === false ? null : $this->projectOccurrence($occurrence);
        });
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function listOccurrences(int $anrId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_review_trigger_occurrences WHERE anr_id = ? ORDER BY occurred_at DESC, id DESC',
            [$anrId]
        );

        return ['items' => array_map([$this, 'projectOccurrence'], $rows)];
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function transition(
        int $anrId,
        string $caseUuid,
        string $action,
        array $data,
        int $revision,
        int $actorId,
        string $correlationId,
        ScenarioReviewSchedule $schedule,
        ?DateTimeInterface $now = null
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $caseUuid,
            $action,
            $data,
            $revision,
            $actorId,
            $correlationId,
            $schedule,
            $now
        ): ?array {
            $case = $this->connection->fetchAssociative(
                'SELECT * FROM scenario_review_cases WHERE anr_id = ? AND uuid = ? FOR UPDATE',
                [$anrId, $caseUuid]
            );
            if ($case === false || (int) $case['revision'] !== $revision) {
                return null;
            }
            $existing = $this->idempotentHistory((int) $case['id'], (string) $data['idempotencyKey']);
            if ($existing) {
                return $this->case($anrId, $caseUuid) + ['idempotent' => true];
            }

            $prior = (string) $case['status'];
            $updates = [];
            $historyReason = null;
            $historyScope = null;
            $evidenceIds = [];
            if ($action === 'assign') {
                if (!in_array($prior, self::OPEN_STATUSES, true)) {
                    throw new \LogicException('Only an active review case can be assigned.');
                }
                $updates = ['assigned_reviewer_id' => $data['assignedReviewerId']];
                if ($prior === 'open') {
                    $updates['status'] = 'in_progress';
                }
                $historyReason = 'Reviewer assignment updated.';
                $evidenceIds = ['assignedReviewerId' => (int) $data['assignedReviewerId']];
            } elseif ($action === 'scope') {
                if (!in_array($prior, ['open', 'in_progress'], true)) {
                    throw new \LogicException('Only an open or in-progress review case can be re-scoped.');
                }
                $riskScenarioUuids = $this->replaceScope($anrId, (int) $case['id'], $data);
                $updates['scope_type'] = $data['analysisWide'] ? 'analysis_wide' : 'risk_scenarios';
                if ($prior === 'open') {
                    $updates['status'] = 'in_progress';
                }
                $historyScope = [
                    'scopeType' => $updates['scope_type'],
                    'riskScenarioUuids' => $riskScenarioUuids,
                ];
            } elseif ($action === 'dispositions') {
                if (!in_array($prior, self::OPEN_STATUSES, true)) {
                    throw new \LogicException('Only an active review case can be updated.');
                }
                $evidenceIds = $this->saveDispositions($anrId, (int) $case['id'], $data['dispositions'], $case);
                $updates['status'] = 'awaiting_decision';
                $historyScope = ['dispositions' => $data['dispositions']];
            } elseif ($action === 'complete') {
                if (!in_array($prior, self::OPEN_STATUSES, true)) {
                    throw new \LogicException('Only an active review case can be completed.');
                }
                $this->assertComplete((int) $case['id']);
                $satisfies = (bool) $data['satisfiesScheduledReview'];
                $updates = [
                    'status' => 'completed',
                    'satisfies_scheduled_review' => $satisfies ? 1 : 0,
                    'completion_rationale' => $data['rationale'] ?? null,
                    'open_scheduled_marker' => null,
                ];
                if ($satisfies) {
                    $completionAt = ($now === null ? new DateTimeImmutable('now', new DateTimeZone('UTC')) : $now)
                        ->setTimezone(new DateTimeZone('UTC'));
                    $this->connection->update('anrs', [
                        'reassessment_last_review_date' => $completionAt->format('Y-m-d'),
                    ], ['id' => $anrId]);
                    $this->synchronizeProjection($anrId, $schedule, $completionAt);
                }
                $historyReason = $data['rationale'] ?? null;
            } elseif ($action === 'dismiss') {
                if (!in_array($prior, self::OPEN_STATUSES, true)) {
                    throw new \LogicException('Only an active review case can be dismissed.');
                }
                $updates = [
                    'status' => 'dismissed',
                    'dismissal_rationale' => $data['rationale'],
                    'open_scheduled_marker' => null,
                ];
                $historyReason = $data['rationale'];
            } else {
                throw new \InvalidArgumentException('The Scenario review action is invalid.');
            }
            $updates['revision'] = $revision + 1;
            $this->connection->update(
                'scenario_review_cases',
                $updates,
                ['id' => $case['id'], 'revision' => $revision]
            );
            $newStatus = (string) ($updates['status'] ?? $prior);
            $this->history(
                $anrId,
                (int) $case['id'],
                $actorId,
                'scenario_review_case_' . $action,
                $prior,
                $newStatus,
                $historyReason,
                $historyScope,
                $evidenceIds,
                $data['idempotencyKey']
            );
            $this->audit($anrId, $actorId, $correlationId, $caseUuid, 'scenario_review_case_' . $action, [
                'priorStatus' => $prior,
                'newStatus' => $newStatus,
                'evidenceIds' => $evidenceIds,
            ]);

            return $this->case($anrId, $caseUuid);
        });
    }

    /** @return array{opened:int,skipped:bool} */
    public function openDueScheduledCases(ScenarioReviewSchedule $schedule, ?DateTimeInterface $now = null): array
    {
        if ((int) $this->connection->fetchOne("SELECT GET_LOCK('scenario-review-schedule', 0)") !== 1) {
            return ['opened' => 0, 'skipped' => true];
        }
        try {
            $anrIds = $this->connection->fetchFirstColumn(
                "SELECT a.anr_id FROM scenario_analyses a INNER JOIN anrs n ON n.id = a.anr_id "
                . "WHERE n.analysis_type = 'scenario'"
            );
            $opened = 0;
            foreach ($anrIds as $anrId) {
                $summary = $this->summary((int) $anrId, $schedule, $now);
                if (!in_array($summary['state'], ['due', 'overdue'], true)) {
                    continue;
                }
                $result = $this->createCase((int) $anrId, [
                    'reason' => 'scheduled',
                    'analysisWide' => true,
                    'riskScenarioUuids' => [],
                    'occurrenceAt' => null,
                    'idempotencyKey' => $this->scheduledIdempotencyKey((int) $anrId, (string) $summary['nextReviewAt']),
                ], 0, 'scenario-review-schedule', $schedule, $now);
                if ($result !== null && empty($result['idempotent'])) {
                    $opened++;
                }
            }

            return ['opened' => $opened, 'skipped' => false];
        } finally {
            $this->connection->executeStatement("SELECT RELEASE_LOCK('scenario-review-schedule')");
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    private function createCaseLocked(
        int $anrId,
        array $data,
        int $actorId,
        string $correlationId,
        ScenarioReviewSchedule $schedule,
        ?DateTimeInterface $now
    ): ?array {
        $analysis = $this->analysis($anrId, true);
        if ($analysis === null) {
            return null;
        }
        $projection = $schedule->calculate(
            $analysis['reassessment_last_review_date'] === null
                ? null
                : (string) $analysis['reassessment_last_review_date'],
            $analysis['reassessment_review_frequency'] === null
                ? null
                : (string) $analysis['reassessment_review_frequency'],
            $now
        );
        if ($data['reason'] === 'scheduled' && !in_array($projection['state'], ['due', 'overdue'], true)) {
            throw new \LogicException('The scheduled review is not due.');
        }
        $scopeRows = $this->scopeRows($anrId, $data['riskScenarioUuids'], (bool) $data['analysisWide']);
        if (!$data['analysisWide'] && count($scopeRows) !== count($data['riskScenarioUuids'])) {
            throw new \InvalidArgumentException('A selected risk scenario is unavailable in this analysis.');
        }
        if (!$data['analysisWide'] && $scopeRows === []) {
            throw new \InvalidArgumentException('A review case needs at least one current risk scenario.');
        }
        $uuid = Uuid::uuid4()->toString();
        $openScheduledMarker = $data['reason'] === 'scheduled' ? 'scheduled' : null;
        if ($openScheduledMarker !== null) {
            $existing = $this->connection->fetchAssociative(
                "SELECT * FROM scenario_review_cases WHERE anr_id = ? "
                . "AND open_scheduled_marker = 'scheduled' FOR UPDATE",
                [$anrId]
            );
            if ($existing !== false) {
                return $existing;
            }
        }
        $nowValue = ($now === null ? new DateTimeImmutable('now', new DateTimeZone('UTC')) : $now)
            ->setTimezone(new DateTimeZone('UTC'));
        $snapshot = $this->startSnapshot($analysis, $scopeRows, $data['triggerOccurrence'] ?? null);
        $this->connection->insert('scenario_review_cases', [
            'uuid' => $uuid,
            'anr_id' => $anrId,
            'scenario_analysis_id' => (int) $analysis['id'],
            'reason' => $data['reason'],
            'status' => $data['reason'] === 'manual' ? 'in_progress' : 'open',
            'scope_type' => $data['analysisWide'] ? 'analysis_wide' : 'risk_scenarios',
            'scheduled_for' => $projection['nextReviewAt'] === null
                ? null
                : $this->databaseDate($projection['nextReviewAt']),
            'occurrence_context_at' => $data['occurrenceAt'] === null
                ? null
                : $this->databaseDate($data['occurrenceAt']),
            'initiated_by' => $actorId === 0 ? null : $actorId,
            'start_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'open_scheduled_marker' => $openScheduledMarker,
            'idempotency_key' => $data['idempotencyKey'],
        ]);
        $caseId = (int) $this->connection->lastInsertId();
        foreach ($scopeRows as $scope) {
            $this->connection->insert('scenario_review_case_scopes', [
                'uuid' => Uuid::uuid4()->toString(),
                'anr_id' => $anrId,
                'review_case_id' => $caseId,
                'risk_scenario_id' => (int) $scope['id'],
                'risk_scenario_uuid' => $scope['uuid'],
                'start_revision' => (int) $scope['revision'],
            ]);
        }
        $this->history(
            $anrId,
            $caseId,
            $actorId === 0 ? null : $actorId,
            'scenario_review_case_created',
            null,
            $data['reason'] === 'manual' ? 'in_progress' : 'open',
            null,
            ['scopeType' => $data['analysisWide'] ? 'analysis_wide' : 'risk_scenarios'],
            ['riskScenarioUuids' => array_column($scopeRows, 'uuid')],
            $data['idempotencyKey']
        );
        $this->audit($anrId, $actorId, $correlationId, $uuid, 'scenario_review_case_created', [
            'reason' => $data['reason'],
            'scopeType' => $data['analysisWide'] ? 'analysis_wide' : 'risk_scenarios',
            'riskScenarioUuids' => array_column($scopeRows, 'uuid'),
        ]);

        return $this->connection->fetchAssociative(
            'SELECT * FROM scenario_review_cases WHERE id = ?',
            [$caseId]
        ) ?: null;
    }

    /** @param array<int, array<string, mixed>> $dispositions @param array<string, mixed> $case @return array<string, mixed> */
    private function saveDispositions(int $anrId, int $caseId, array $dispositions, array $case): array
    {
        $scopeRows = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_review_case_scopes WHERE review_case_id = ? FOR UPDATE',
            [$caseId]
        );
        $scopes = [];
        foreach ($scopeRows as $scope) {
            $scopes[(string) $scope['risk_scenario_uuid']] = $scope;
        }
        $evidence = [];
        foreach ($dispositions as $disposition) {
            $scope = $scopes[$disposition['riskScenarioUuid']] ?? null;
            if ($scope === null) {
                throw new \InvalidArgumentException('A review disposition is outside this review case scope.');
            }
            $risk = $this->connection->fetchAssociative(
                'SELECT revision FROM scenario_risk_scenarios WHERE anr_id = ? AND id = ? FOR UPDATE',
                [$anrId, $scope['risk_scenario_id']]
            );
            if ($risk === false || (int) $risk['revision'] !== (int) $disposition['riskScenarioRevision']) {
                throw new \LogicException('Reload the risk scenario before updating its review disposition.');
            }
            $assessmentUuid = $disposition['reassessedAssessmentUuid'] ?? null;
            if ($disposition['disposition'] === 'reassessed') {
                $assessment = $this->connection->fetchOne(
                    'SELECT uuid FROM scenario_assessments WHERE anr_id = ? AND risk_scenario_id = ? AND uuid = ? '
                    . 'AND created_at >= ? LIMIT 1',
                    [$anrId, $scope['risk_scenario_id'], $assessmentUuid, $case['created_at']]
                );
                if ($assessment === false) {
                    throw new \InvalidArgumentException(
                        'A reassessed disposition needs a newer assessment from this review.'
                    );
                }
                $evidence['assessmentUuids'][] = $assessmentUuid;
            } else {
                $assessmentUuid = null;
            }
            if (in_array($disposition['disposition'], ['not_applicable', 'treatment_follow_up'], true)
                && trim((string) $disposition['rationale']) === '') {
                throw new \InvalidArgumentException('This review disposition requires a rationale.');
            }
            $this->connection->update('scenario_review_case_scopes', [
                'disposition' => $disposition['disposition'],
                'rationale' => $disposition['rationale'] ?? null,
                'reassessed_assessment_uuid' => $assessmentUuid,
                'revision' => (int) $scope['revision'] + 1,
            ], ['id' => $scope['id']]);
            $this->connection->delete('scenario_review_case_treatment_links', ['review_case_scope_id' => $scope['id']]);
            if ($disposition['disposition'] === 'treatment_follow_up') {
                $links = $this->treatmentLinks(
                    $anrId,
                    (int) $scope['risk_scenario_id'],
                    $disposition['treatmentUuids']
                );
                if (count($links) !== count($disposition['treatmentUuids'])) {
                    throw new \InvalidArgumentException('A selected treatment is outside the reviewed risk scenario.');
                }
                foreach ($links as $link) {
                    $this->connection->insert('scenario_review_case_treatment_links', [
                        'anr_id' => $anrId,
                        'review_case_scope_id' => $scope['id'],
                        'treatment_id' => $link['id'],
                        'treatment_uuid' => $link['uuid'],
                        'treatment_snapshot' => json_encode($link['snapshot'], JSON_THROW_ON_ERROR),
                    ]);
                    $evidence['treatmentUuids'][] = $link['uuid'];
                }
            }
            $evidence['riskScenarioUuids'][] = $disposition['riskScenarioUuid'];
        }

        return $evidence;
    }

    private function assertComplete(int $caseId): void
    {
        $incomplete = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scenario_review_case_scopes WHERE review_case_id = ? AND disposition IS NULL',
            [$caseId]
        );
        if ($incomplete > 0) {
            throw new \InvalidArgumentException(
                'Record a valid disposition for every scoped risk scenario before completion.'
            );
        }
    }

    /** @param array<string, mixed> $data @return array<int, string> */
    private function replaceScope(int $anrId, int $caseId, array $data): array
    {
        $existingDispositions = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scenario_review_case_scopes WHERE review_case_id = ? AND disposition IS NOT NULL',
            [$caseId]
        );
        if ($existingDispositions > 0) {
            throw new \LogicException('A review case with recorded dispositions cannot be re-scoped.');
        }
        $scopeRows = $this->scopeRows($anrId, $data['riskScenarioUuids'], (bool) $data['analysisWide']);
        if (!$data['analysisWide'] && count($scopeRows) !== count($data['riskScenarioUuids'])) {
            throw new \InvalidArgumentException('A selected risk scenario is unavailable in this analysis.');
        }
        $this->connection->delete('scenario_review_case_scopes', ['review_case_id' => $caseId]);
        foreach ($scopeRows as $scope) {
            $this->connection->insert('scenario_review_case_scopes', [
                'uuid' => Uuid::uuid4()->toString(),
                'anr_id' => $anrId,
                'review_case_id' => $caseId,
                'risk_scenario_id' => (int) $scope['id'],
                'risk_scenario_uuid' => $scope['uuid'],
                'start_revision' => (int) $scope['revision'],
            ]);
        }

        return array_map('strval', array_column($scopeRows, 'uuid'));
    }

    /** @return array<string, mixed>|null */
    private function analysis(int $anrId, bool $lock = false): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT s.*, a.reassessment_last_review_date, a.reassessment_review_frequency FROM scenario_analyses s '
            . 'INNER JOIN anrs a ON a.id = s.anr_id WHERE s.anr_id = ?' . ($lock ? ' FOR UPDATE' : ''),
            [$anrId]
        );

        return $row === false ? null : $row;
    }

    /** @param array<int, string> $uuids @return array<int, array<string, mixed>> */
    private function scopeRows(int $anrId, array $uuids, bool $analysisWide): array
    {
        if ($analysisWide) {
            return $this->connection->fetchAllAssociative(
                'SELECT id, uuid, revision, title FROM scenario_risk_scenarios WHERE anr_id = ? ORDER BY id ASC',
                [$anrId]
            );
        }
        if ($uuids === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($uuids), '?'));

        return $this->connection->fetchAllAssociative(
            'SELECT id, uuid, revision, title FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid IN ('
            . $placeholders . ') ORDER BY id ASC',
            array_merge([$anrId], $uuids)
        );
    }

    /** @param array<int, string> $uuids @return array<int, int> */
    private function riskScenarioIds(int $anrId, array $uuids): array
    {
        return array_map('intval', array_column($this->scopeRows($anrId, $uuids, false), 'id'));
    }

    /**
     * @param array<string, mixed> $analysis
     * @param array<int, array<string, mixed>> $scopeRows
     * @param array<string, mixed>|null $triggerOccurrence
     * @return array<string, mixed>
     */
    private function startSnapshot(array $analysis, array $scopeRows, ?array $triggerOccurrence): array
    {
        $profile = $this->connection->fetchAssociative(
            'SELECT acp.profile_snapshot FROM scenario_analysis_criteria_profiles acp '
            . 'WHERE acp.scenario_analysis_id = ?',
            [$analysis['id']]
        );
        $scenarios = [];
        foreach ($scopeRows as $scope) {
            $scenarios[] = [
                'uuid' => $scope['uuid'],
                'title' => $scope['title'],
                'revision' => (int) $scope['revision'],
                'assessment' => $this->latestAssessment((int) $analysis['anr_id'], (int) $scope['id']),
                'acceptanceState' => $this->acceptanceState((int) $analysis['anr_id'], (int) $scope['id']),
                'treatments' => $this->treatmentContext((int) $analysis['anr_id'], (int) $scope['id']),
            ];
        }

        return [
            'analysisContext' => [
                'riskAnalysisContext' => $analysis['risk_analysis_context'],
                'trendsSummary' => $analysis['trends_summary'],
                'managementOrganisation' => $analysis['management_organisation'],
                'criteriaProfile' => $profile === false
                    ? null
                    : json_decode((string) $profile['profile_snapshot'], true),
            ],
            'riskScenarios' => $scenarios,
            // This is a historic occurrence context, never monitoring evidence inferred by the server.
            'triggerOccurrences' => $triggerOccurrence === null ? [] : [$triggerOccurrence],
        ];
    }

    /** @return array<string, mixed>|null */
    private function latestAssessment(int $anrId, int $riskScenarioId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT uuid, assessment_type, revision, created_at FROM scenario_assessments '
            . 'WHERE anr_id = ? AND risk_scenario_id = ? ORDER BY created_at DESC, id DESC LIMIT 1',
            [$anrId, $riskScenarioId]
        );

        return $row === false ? null : [
            'uuid' => $row['uuid'],
            'type' => $row['assessment_type'],
            'revision' => (int) $row['revision'],
            'createdAt' => $this->apiDate((string) $row['created_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function acceptanceState(int $anrId, int $riskScenarioId): array
    {
        $proposed = $this->connection->fetchAssociative(
            "SELECT uuid, created_at FROM scenario_assessments WHERE anr_id = ? AND risk_scenario_id = ? "
            . "AND assessment_type = 'proposed_residual' ORDER BY created_at DESC, id DESC LIMIT 1",
            [$anrId, $riskScenarioId]
        );
        $acceptance = $this->connection->fetchAssociative(
            'SELECT d.uuid, d.created_at, a.uuid AS assessment_uuid FROM scenario_acceptance_decisions d '
            . 'INNER JOIN scenario_assessments a ON a.id = d.assessment_id '
            . 'WHERE d.anr_id = ? AND d.risk_scenario_id = ? ORDER BY d.created_at DESC, d.id DESC LIMIT 1',
            [$anrId, $riskScenarioId]
        );
        $stale = $proposed !== false && (
            $acceptance === false
            || strtotime((string) $proposed['created_at']) > strtotime((string) $acceptance['created_at'])
        );

        return [
            'state' => $stale ? 'reassessment_decision_required' : ($acceptance === false ? 'absent' : 'current'),
            'latestProposedResidualAssessmentUuid' => $proposed === false ? null : $proposed['uuid'],
            'latestAcceptanceUuid' => $acceptance === false ? null : $acceptance['uuid'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function treatmentContext(int $anrId, int $riskScenarioId): array
    {
        $treatments = $this->connection->fetchAllAssociative(
            'SELECT id, uuid, status, revision, updated_at FROM scenario_treatments '
            . 'WHERE anr_id = ? AND risk_scenario_id = ? ORDER BY id ASC',
            [$anrId, $riskScenarioId]
        );
        foreach ($treatments as &$treatment) {
            $recommendations = $this->connection->fetchAllAssociative(
                'SELECT i.uuid, i.status, r.recommendation_snapshot '
                . 'FROM scenario_treatment_recommendation_implementations i '
                . 'INNER JOIN scenario_treatment_recommendations r ON r.id = i.treatment_recommendation_id '
                . 'WHERE i.treatment_id = ? ORDER BY i.id ASC',
                [$treatment['id']]
            );
            $treatment['recommendations'] = array_map(static function (array $recommendation): array {
                $snapshot = json_decode(
                    (string) $recommendation['recommendation_snapshot'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                return [
                    'implementationUuid' => $recommendation['uuid'],
                    'status' => $recommendation['status'],
                    'importance' => $snapshot['importance'] ?? 0,
                    'treatmentImportance' => $snapshot['treatmentImportance'] ?? $snapshot['importance'] ?? 0,
                ];
            }, $recommendations);
            unset($treatment['id']);
        }
        unset($treatment);

        return $treatments;
    }

    /** @param array<int, string> $treatmentUuids @return array<int, array<string, mixed>> */
    private function treatmentLinks(int $anrId, int $riskScenarioId, array $treatmentUuids): array
    {
        if ($treatmentUuids === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($treatmentUuids), '?'));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, uuid, status, revision, updated_at FROM scenario_treatments WHERE anr_id = ? '
            . 'AND risk_scenario_id = ? AND uuid IN (' . $placeholders . ')',
            array_merge([$anrId, $riskScenarioId], $treatmentUuids)
        );

        return array_map(function (array $row) use ($anrId, $riskScenarioId): array {
            $context = $this->treatmentContext($anrId, $riskScenarioId);
            $snapshot = null;
            foreach ($context as $treatment) {
                if ((string) $treatment['uuid'] === (string) $row['uuid']) {
                    $snapshot = $treatment;
                    break;
                }
            }

            return [
                'id' => (int) $row['id'],
                'uuid' => $row['uuid'],
                'snapshot' => $snapshot ?? $row,
            ];
        }, $rows);
    }

    /** @param array<int, int> $requestedIds @return array<string, mixed>|null */
    private function compatibleOpenCase(int $anrId, array $requestedIds, bool $analysisWide): ?array
    {
        $cases = $this->connection->fetchAllAssociative(
            "SELECT * FROM scenario_review_cases WHERE anr_id = ? AND status IN ('open', 'in_progress') FOR UPDATE",
            [$anrId]
        );
        foreach ($cases as $case) {
            if ($analysisWide && $case['scope_type'] === 'analysis_wide') {
                return $case;
            }
            if ($analysisWide || $case['scope_type'] !== 'risk_scenarios') {
                continue;
            }
            $scopeIds = array_map('intval', $this->connection->fetchFirstColumn(
                'SELECT risk_scenario_id FROM scenario_review_case_scopes WHERE review_case_id = ?',
                [$case['id']]
            ));
            if (array_diff($requestedIds, $scopeIds) === []) {
                return $case;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectCase(array $row): array
    {
        return $this->projectCaseSummary($row) + [
            'initiatedBy' => $row['initiated_by'] === null ? null : (int) $row['initiated_by'],
            'assignedReviewerId' => $row['assigned_reviewer_id'] === null ? null : (int) $row['assigned_reviewer_id'],
            'satisfiesScheduledReview' => $row['satisfies_scheduled_review'] === null
                ? null
                : (bool) $row['satisfies_scheduled_review'],
            'completionRationale' => $row['completion_rationale'],
            'dismissalRationale' => $row['dismissal_rationale'],
            'startSnapshot' => json_decode((string) $row['start_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            'scopes' => $this->projectScopes((int) $row['id']),
            'occurrences' => $this->projectOccurrences((int) $row['id']),
            'history' => $this->projectHistory((int) $row['id']),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectCaseSummary(array $row): array
    {
        return [
            'uuid' => (string) $row['uuid'],
            'reason' => (string) $row['reason'],
            'status' => (string) $row['status'],
            'scopeType' => (string) $row['scope_type'],
            'scheduledFor' => $this->apiDate($row['scheduled_for']),
            'occurrenceContextAt' => $this->apiDate($row['occurrence_context_at']),
            'revision' => (int) $row['revision'],
            'createdAt' => $this->apiDate((string) $row['created_at']),
            'updatedAt' => $this->apiDate((string) $row['updated_at']),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function projectScopes(int $caseId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_review_case_scopes WHERE review_case_id = ? ORDER BY id ASC',
            [$caseId]
        );

        return array_map(function (array $row): array {
            $links = $this->connection->fetchAllAssociative(
                'SELECT treatment_uuid, treatment_snapshot FROM scenario_review_case_treatment_links '
                . 'WHERE review_case_scope_id = ? ORDER BY id ASC',
                [$row['id']]
            );

            return [
                'uuid' => $row['uuid'],
                'riskScenarioUuid' => $row['risk_scenario_uuid'],
                'startRevision' => (int) $row['start_revision'],
                'disposition' => $row['disposition'],
                'rationale' => $row['rationale'],
                'reassessedAssessmentUuid' => $row['reassessed_assessment_uuid'],
                'treatments' => array_map(static fn (array $link): array => [
                    'uuid' => $link['treatment_uuid'],
                    'snapshot' => json_decode((string) $link['treatment_snapshot'], true, 512, JSON_THROW_ON_ERROR),
                ], $links),
            ];
        }, $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function projectOccurrences(int $caseId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_review_trigger_occurrences WHERE review_case_id = ? '
            . 'ORDER BY occurred_at DESC, id DESC',
            [$caseId]
        );

        return array_map([$this, 'projectOccurrence'], $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function projectHistory(int $caseId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM scenario_review_case_history WHERE review_case_id = ? ORDER BY created_at DESC, id DESC',
            [$caseId]
        );

        return array_map(fn (array $row): array => [
            'uuid' => $row['uuid'],
            'action' => $row['action'],
            'actorId' => $row['actor_id'] === null ? null : (int) $row['actor_id'],
            'priorStatus' => $row['prior_status'],
            'newStatus' => $row['new_status'],
            'reason' => $row['reason'],
            'scope' => $row['scope_snapshot'] === null ? null : json_decode((string) $row['scope_snapshot'], true),
            'evidenceIds' => $row['evidence_ids'] === null ? [] : json_decode((string) $row['evidence_ids'], true),
            'createdAt' => $this->apiDate((string) $row['created_at']),
        ], $rows);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function projectOccurrence(array $row): array
    {
        return [
            'uuid' => $row['uuid'],
            'reviewCaseUuid' => $this->connection->fetchOne(
                'SELECT uuid FROM scenario_review_cases WHERE id = ?',
                [$row['review_case_id']]
            ),
            'triggerId' => (int) $row['reassessment_trigger_id'],
            'trigger' => json_decode((string) $row['trigger_snapshot'], true, 512, JSON_THROW_ON_ERROR),
            'occurredAt' => $this->apiDate((string) $row['occurred_at']),
            'reporterId' => $row['reporter_id'] === null ? null : (int) $row['reporter_id'],
            'sourceObservationUuid' => $row['source_observation_uuid'],
            'sourceText' => $row['source_text'],
            'triageRationale' => $row['triage_rationale'],
            'createdAt' => $this->apiDate((string) $row['created_at']),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function acceptanceStaleness(int $anrId): array
    {
        $scenarios = $this->connection->fetchAllAssociative(
            'SELECT id, uuid FROM scenario_risk_scenarios WHERE anr_id = ?',
            [$anrId]
        );

        return array_map(fn (array $scenario): array => ['riskScenarioUuid' => $scenario['uuid']]
            + $this->acceptanceState($anrId, (int) $scenario['id']), $scenarios);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function triggerSnapshot(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'triggerType' => $row['trigger_type'],
            'description' => $row['description'],
            'monitoringApproach' => $row['monitoring_approach'],
            'position' => (int) $row['position'],
        ];
    }

    /** @param array<string, mixed>|null $scope @param array<string, mixed> $evidence */
    private function history(
        int $anrId,
        int $caseId,
        ?int $actorId,
        string $action,
        ?string $priorStatus,
        ?string $newStatus,
        ?string $reason,
        ?array $scope,
        array $evidence,
        ?string $idempotencyKey
    ): void {
        $this->connection->insert('scenario_review_case_history', [
            'uuid' => Uuid::uuid4()->toString(),
            'anr_id' => $anrId,
            'review_case_id' => $caseId,
            'actor_id' => $actorId === 0 ? null : $actorId,
            'action' => $action,
            'prior_status' => $priorStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'scope_snapshot' => $scope === null ? null : json_encode($scope, JSON_THROW_ON_ERROR),
            'evidence_ids' => $evidence === [] ? null : json_encode($evidence, JSON_THROW_ON_ERROR),
            'idempotency_key' => $idempotencyKey,
        ]);
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

    /** @return array<string, mixed>|null */
    private function idempotentCase(int $anrId, string $key): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scenario_review_cases WHERE anr_id = ? AND idempotency_key = ? FOR UPDATE',
            [$anrId, $key]
        );

        return $row === false ? null : $row;
    }

    private function idempotentHistory(int $caseId, string $key): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM scenario_review_case_history WHERE review_case_id = ? AND idempotency_key = ? LIMIT 1',
            [$caseId, $key]
        ) !== false;
    }

    private function scheduledIdempotencyKey(int $anrId, string $nextReviewAt): string
    {
        return substr(hash('sha256', 'scenario-scheduled-review:' . $anrId . ':' . $nextReviewAt), 0, 8)
            . '-0000-4000-8000-'
            . substr(hash('sha256', $nextReviewAt . ':' . $anrId), 0, 12);
    }

    private function databaseDate(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    private function apiDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\\TH:i:s\\Z');
    }
}
