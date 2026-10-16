<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Table\ScenarioCriteriaDefinitionTable;
use PHPUnit\Framework\TestCase;

final class ScenarioCriteriaDefinitionTableTest extends TestCase
{
    public function testLockedDefinitionReadsTheNormalisedImmutableSourceSnapshotAndAuditEvidence(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 9,
            'uuid' => '00000000-0000-4000-8000-000000000009',
            'locked_at' => '2026-10-12 10:00:00',
            'locked_by' => 6,
            'source_anr_id' => 7,
            'source_language_code' => 'en',
            'source_read_at' => '2026-10-12 09:00:00',
            'profile_uuid' => '00000000-0000-4000-8000-000000000010',
            'identifier' => 'operational-risk-anr-7',
            'title' => 'Operational risk criteria',
            'profile_revision' => 1,
            'version_uuid' => '00000000-0000-4000-8000-000000000011',
            'version' => 1,
            'payload' => json_encode(['aggregationPolicy' => 'highest_consequence_dimension']),
            'analysis_revision' => 4,
        ]);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls([
            [
                'kind' => 'likelihood',
                'dimension_key' => null,
                'source_scale_id' => 21,
                'source_scale_type_id' => null,
                'source_comment_id' => 31,
                'source_scale_index' => 1,
                'source_scale_value' => 1,
                'source_min' => 1,
                'source_max' => 5,
                'source_label' => 'Rare',
                'source_comment' => 'Original operational scale label.',
            ],
        ], [
            [
                'action' => 'scenario_criteria_locked',
                'target_uuid' => '00000000-0000-4000-8000-000000000009',
                'created_at' => '2026-10-12 10:00:00',
            ],
        ]);

        $table = $this->table($connection);
        $locked = $table->locked(7);

        self::assertSame(7, $locked['sourceSnapshot']['sourceAnrId']);
        self::assertSame(21, $locked['sourceSnapshot']['levels'][0]['scaleId']);
        self::assertSame('Rare', $locked['sourceSnapshot']['levels'][0]['label']);
        self::assertSame('scenario_criteria_locked', $locked['auditEvents'][0]['action']);
        self::assertTrue($locked['profile']['isLocked']);
    }

    public function testItRejectsStaleOrPostLockDraftWritesBeforeChangingNormalisedDraftRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->expects(self::never())->method('update');
        $connection->method('fetchAssociative')->willReturn([
            'id' => 4,
            'scenario_analysis_id' => 8,
            'uuid' => '00000000-0000-4000-8000-000000000004',
            'revision' => 2,
        ]);

        self::assertNull($this->table($connection)->updateDraft(7, [], 1, 6, 'request-1'));

        $lockedConnection = $this->createMock(Connection::class);
        $lockedConnection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $lockedConnection->expects(self::never())->method('update');
        $lockedConnection->method('fetchAssociative')->willReturn([
            'id' => 4,
            'scenario_analysis_id' => 8,
            'uuid' => '00000000-0000-4000-8000-000000000004',
            'revision' => 1,
        ]);
        $lockedConnection->method('fetchOne')->willReturn(9);

        self::assertNull($this->table($lockedConnection)->updateDraft(7, [], 1, 6, 'request-2'));
    }

    public function testLockDraftReplacesAnExistingProvisionalCriteriaAssignment(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'id' => 8,
                'revision' => 3,
                'criteria_locked_at' => null,
            ],
            [
                'id' => 9,
                'revision' => 2,
                'source_anr_id' => 7,
                'source_language_code' => 'en',
                'source_read_at' => '2026-10-15 12:00:00',
            ],
        );
        $connection->method('lastInsertId')->willReturnOnConsecutiveCalls('31', '32', '33');
        $connection->method('fetchAllAssociative')->willReturn([]);
        $connection->method('update')->willReturn(0);
        $connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('ON DUPLICATE KEY UPDATE profile_version_id = VALUES(profile_version_id)'),
                self::callback(static fn (array $parameters): bool => $parameters[0] === 8 && $parameters[1] === 32),
            )
            ->willReturn(2);

        self::assertNull($this->table($connection)->lockDraft(
            7,
            2,
            [
                'identifier' => 'operational-risk-anr-7',
                'title' => 'Operational risk criteria',
                'payload' => ['likelihoodLabels' => []],
            ],
            6,
            'request-criteria-lock',
        ));
    }

    private function table(Connection $connection): ScenarioCriteriaDefinitionTable
    {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new ScenarioCriteriaDefinitionTable($entityManager);
    }
}
