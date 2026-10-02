<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Ramsey\Uuid\Uuid;

/** Persists immutable Scenario evidence links to existing ANR interested parties. */
final class ScenarioInterestedPartyReferenceTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @return array<string, mixed> */
    public function list(int $anrId, string $query, int $page, int $pageSize): array
    {
        $where = 'party.anr_id = ?';
        $parameters = [$anrId];
        if ($query !== '') {
            $where .= ' AND (party.stakeholder LIKE ? OR party.requirement LIKE ?)';
            $parameters[] = '%' . $query . '%';
            $parameters[] = '%' . $query . '%';
        }
        $total = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM anr_interested_parties party WHERE %s', $where),
            $parameters
        );
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT party.id, party.stakeholder, party.requirement, party.position, link.uuid AS link_uuid, '
                . 'link.revision AS link_revision, link.stakeholder_snapshot, link.requirement_snapshot '
                . 'FROM anr_interested_parties party '
                . 'LEFT JOIN scenario_analysis_interested_party_links link '
                . 'ON link.anr_id = party.anr_id AND link.interested_party_id = party.id '
                . 'WHERE %s ORDER BY party.position ASC, party.id ASC LIMIT %d OFFSET %d',
                $where,
                $pageSize,
                ($page - 1) * $pageSize
            ),
            $parameters
        );

        return [
            'items' => array_map([$this, 'project'], $rows),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'analysisRevision' => $this->analysisRevision($anrId),
        ];
    }

    /** @return array<string, mixed>|null */
    public function attach(
        int $anrId,
        int $actorId,
        int $interestedPartyId,
        int $analysisRevision,
        string $correlationId
    ): ?array {
        return $this->connection->transactional(function () use (
            $anrId,
            $actorId,
            $interestedPartyId,
            $analysisRevision,
            $correlationId
        ): ?array {
            $analysis = $this->connection->fetchAssociative(
                'SELECT id, uuid, revision FROM scenario_analyses WHERE anr_id = ? FOR UPDATE',
                [$anrId]
            );
            if ($analysis === false || (int) $analysis['revision'] !== $analysisRevision) {
                return null;
            }
            $party = $this->connection->fetchAssociative(
                'SELECT id, stakeholder, requirement FROM anr_interested_parties WHERE id = ? AND anr_id = ?',
                [$interestedPartyId, $anrId]
            );
            if ($party === false) {
                return null;
            }
            $existing = $this->connection->fetchAssociative(
                'SELECT * FROM scenario_analysis_interested_party_links WHERE anr_id = ? AND interested_party_id = ?',
                [$anrId, $interestedPartyId]
            );
            if ($existing !== false) {
                return $this->project(array_merge($party, [
                    'position' => 0,
                    'link_uuid' => $existing['uuid'],
                    'link_revision' => $existing['revision'],
                    'stakeholder_snapshot' => $existing['stakeholder_snapshot'],
                    'requirement_snapshot' => $existing['requirement_snapshot'],
                ]));
            }
            $uuid = Uuid::uuid4()->toString();
            $snapshot = [
                'stakeholder' => (string) $party['stakeholder'],
                'requirement' => (string) $party['requirement'],
                'sourceScope' => 'current-analysis',
            ];
            $this->connection->insert('scenario_analysis_interested_party_links', [
                'uuid' => $uuid,
                'anr_id' => $anrId,
                'scenario_analysis_id' => $analysis['id'],
                'interested_party_id' => $interestedPartyId,
                'stakeholder_snapshot' => $snapshot['stakeholder'],
                'requirement_snapshot' => $snapshot['requirement'],
            ]);
            $this->connection->update('scenario_analyses', ['revision' => $analysisRevision + 1], ['id' => $analysis['id']]);
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'scenario_interested_party_attached', $snapshot);

            return $this->project(array_merge($party, [
                'position' => 0,
                'link_uuid' => $uuid,
                'link_revision' => 1,
                'stakeholder_snapshot' => $snapshot['stakeholder'],
                'requirement_snapshot' => $snapshot['requirement'],
            ]));
        });
    }

    public function detach(int $anrId, int $actorId, string $uuid, int $analysisRevision, string $correlationId): bool
    {
        return $this->connection->transactional(function () use (
            $anrId,
            $actorId,
            $uuid,
            $analysisRevision,
            $correlationId
        ): bool {
            $analysis = $this->connection->fetchAssociative(
                'SELECT id, revision FROM scenario_analyses WHERE anr_id = ? FOR UPDATE',
                [$anrId]
            );
            if ($analysis === false || (int) $analysis['revision'] !== $analysisRevision) {
                return false;
            }
            $link = $this->connection->fetchAssociative(
                'SELECT * FROM scenario_analysis_interested_party_links WHERE anr_id = ? AND uuid = ? FOR UPDATE',
                [$anrId, $uuid]
            );
            if ($link === false) {
                return false;
            }
            $this->connection->delete('scenario_analysis_interested_party_links', ['id' => $link['id']]);
            $this->connection->update('scenario_analyses', ['revision' => $analysisRevision + 1], ['id' => $analysis['id']]);
            $this->audit($anrId, $actorId, $correlationId, $uuid, 'scenario_interested_party_detached', [
                'snapshot' => [
                    'stakeholder' => $link['stakeholder_snapshot'],
                    'requirement' => $link['requirement_snapshot'],
                    'sourceScope' => 'current-analysis',
                ],
            ]);

            return true;
        });
    }

    private function analysisRevision(int $anrId): int
    {
        return (int) $this->connection->fetchOne('SELECT revision FROM scenario_analyses WHERE anr_id = ?', [$anrId]);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function project(array $row): array
    {
        $linked = $row['link_uuid'] !== null;

        return [
            'interestedPartyId' => (int) $row['id'],
            'stakeholder' => (string) $row['stakeholder'],
            'requirement' => (string) $row['requirement'],
            'linked' => $linked,
            'linkUuid' => $linked ? (string) $row['link_uuid'] : null,
            'referenceSnapshot' => $linked ? [
                'stakeholder' => (string) $row['stakeholder_snapshot'],
                'requirement' => (string) $row['requirement_snapshot'],
                'sourceScope' => 'current-analysis',
            ] : null,
        ];
    }

    /** @param array<string, mixed> $patch */
    private function audit(int $anrId, int $actorId, string $correlationId, string $targetUuid, string $action, array $patch): void
    {
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
