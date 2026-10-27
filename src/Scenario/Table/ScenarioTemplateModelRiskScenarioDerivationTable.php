<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Ramsey\Uuid\Uuid;

/** Immutable, analysis-local provenance for explicit template-model creation. */
final class ScenarioTemplateModelRiskScenarioDerivationTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @return array<string, mixed>|null */
    public function findRequest(int $anrId, string $idempotencyKey): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scenario_template_model_creation_requests '
            . 'WHERE anr_id = ? AND idempotency_key = ? FOR UPDATE',
            [$anrId, $idempotencyKey]
        );

        return $row === false ? null : $row;
    }

    /** @return array{id: int, uuid: string} */
    public function createRequest(
        int $anrId,
        int $snapshotId,
        string $idempotencyKey,
        string $requestChecksum,
        int $sourceRevision,
        int $actorId,
        string $correlationId
    ): array {
        $uuid = Uuid::uuid4()->toString();
        $this->connection->insert('scenario_template_model_creation_requests', [
            'uuid' => $uuid,
            'anr_id' => $anrId,
            'snapshot_id' => $snapshotId,
            'idempotency_key' => $idempotencyKey,
            'request_checksum' => $requestChecksum,
            'source_revision' => $sourceRevision,
            'actor_id' => $actorId,
            'correlation_id' => substr($correlationId, 0, 128),
        ]);

        return ['id' => (int) $this->connection->lastInsertId(), 'uuid' => $uuid];
    }

    /** @param array<string, mixed> $sourcePayload @return array{id: int, uuid: string} */
    public function createModelDerivation(
        int $anrId,
        int $snapshotId,
        int $requestId,
        string $modelRecordUuid,
        string $riskScenarioUuid,
        array $sourcePayload,
        int $actorId
    ): array {
        $riskScenarioId = $this->connection->fetchOne(
            'SELECT id FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ?',
            [$anrId, $riskScenarioUuid]
        );
        if ($riskScenarioId === false) {
            throw new \RuntimeException('Created risk scenario was not found.');
        }
        $uuid = Uuid::uuid4()->toString();
        $payload = $this->json($sourcePayload);
        $this->connection->insert('scenario_template_model_risk_scenario_derivations', [
            'uuid' => $uuid,
            'anr_id' => $anrId,
            'snapshot_id' => $snapshotId,
            'creation_request_id' => $requestId,
            'model_record_uuid' => $modelRecordUuid,
            'risk_scenario_id' => (int) $riskScenarioId,
            'source_payload' => $payload,
            'source_checksum' => hash('sha256', $payload),
            'created_by' => $actorId,
        ]);

        return ['id' => (int) $this->connection->lastInsertId(), 'uuid' => $uuid];
    }

    /** @param array<string, mixed> $sourcePayload */
    public function createEventDerivation(
        int $anrId,
        int $modelDerivationId,
        string $eventRecordUuid,
        string $eventUuid,
        array $sourcePayload
    ): void {
        $eventId = $this->connection->fetchOne(
            'SELECT id FROM scenario_events WHERE anr_id = ? AND uuid = ?',
            [$anrId, $eventUuid]
        );
        if ($eventId === false) {
            throw new \RuntimeException('Created local event was not found.');
        }
        $payload = $this->json($sourcePayload);
        $this->connection->insert('scenario_template_model_event_derivations', [
            'uuid' => Uuid::uuid4()->toString(),
            'anr_id' => $anrId,
            'model_derivation_id' => $modelDerivationId,
            'event_record_uuid' => $eventRecordUuid,
            'event_id' => (int) $eventId,
            'source_payload' => $payload,
            'source_checksum' => hash('sha256', $payload),
        ]);
    }

    /** @return array<int, array{modelRecordUuid: string, riskScenarioUuid: string, title: string, createdAt: string}> */
    public function listBySnapshot(int $anrId, int $snapshotId): array
    {
        return array_map(
            static fn (array $row): array => [
                'modelRecordUuid' => (string) $row['model_record_uuid'],
                'riskScenarioUuid' => (string) $row['risk_scenario_uuid'],
                'title' => (string) $row['title'],
                'createdAt' => (string) $row['created_at'],
            ],
            $this->connection->fetchAllAssociative(
                'SELECT derivation.model_record_uuid, risk_scenario.uuid AS risk_scenario_uuid, '
                . 'risk_scenario.title, derivation.created_at '
                . 'FROM scenario_template_model_risk_scenario_derivations derivation '
                . 'INNER JOIN scenario_risk_scenarios risk_scenario ON risk_scenario.id = derivation.risk_scenario_id '
                . 'WHERE derivation.anr_id = ? AND derivation.snapshot_id = ? '
                . 'ORDER BY derivation.created_at ASC, derivation.id ASC',
                [$anrId, $snapshotId]
            )
        );
    }

    /** @return array<int, array{riskScenarioUuid: string, title: string, createdAt: string}> */
    public function listByRequest(int $requestId): array
    {
        return array_map(
            static fn (array $row): array => [
                'riskScenarioUuid' => (string) $row['risk_scenario_uuid'],
                'title' => (string) $row['title'],
                'createdAt' => (string) $row['created_at'],
            ],
            $this->connection->fetchAllAssociative(
                'SELECT risk_scenario.uuid AS risk_scenario_uuid, risk_scenario.title, derivation.created_at '
                . 'FROM scenario_template_model_risk_scenario_derivations derivation '
                . 'INNER JOIN scenario_risk_scenarios risk_scenario ON risk_scenario.id = derivation.risk_scenario_id '
                . 'WHERE derivation.creation_request_id = ? ORDER BY derivation.id ASC',
                [$requestId]
            )
        );
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        $this->sort($value);

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $value */
    private function sort(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item) && !array_is_list($item)) {
                $this->sort($item);
            }
        }
        unset($item);
    }
}
