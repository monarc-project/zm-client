<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateEventEvaluationTable;
use PHPUnit\Framework\TestCase;

/** @covers \Monarc\FrontOffice\Scenario\Table\ScenarioTemplateEventEvaluationTable */
final class ScenarioTemplateEventEvaluationTableTest extends TestCase
{
    public function testGroupedProjectsUnevaluatedSnapshotEventsWithoutAnActor(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([]);
        $table = $this->table($connection);

        $groups = $table->grouped(7, 11, [
            'records' => [
                [
                    'type' => 'template-risk-scenario-model',
                    'uuid' => '00000000-0000-4000-8000-000000000011',
                    'sourceUuid' => '00000000-0000-4000-8000-000000000012',
                    'revision' => 1,
                    'content' => ['title' => 'Supplier interruption', 'position' => 1],
                ],
                [
                    'type' => 'template-model-event',
                    'uuid' => '00000000-0000-4000-8000-000000000013',
                    'sourceUuid' => '00000000-0000-4000-8000-000000000014',
                    'sourceVersion' => 2,
                    'content' => [
                        'modelStableUuid' => '00000000-0000-4000-8000-000000000012',
                        'event_title_snapshot' => 'Supplier outage',
                        'guidance' => 'Consider the reporting period.',
                        'position' => 1,
                    ],
                ],
            ],
        ]);

        self::assertSame('Supplier interruption', $groups[0]['title']);
        self::assertSame(1, $groups[0]['sourceRevision']);
        self::assertSame('not_evaluated', $groups[0]['events'][0]['state']);
        self::assertNull($groups[0]['events'][0]['actorId']);
        self::assertSame(0, $groups[0]['events'][0]['revision']);
    }

    public function testContainsEventRecordRequiresItsOwningModelInTheSameSnapshot(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->willReturn([
            'content_json' => json_encode([
                'modelStableUuid' => '00000000-0000-4000-8000-000000000012',
            ], JSON_THROW_ON_ERROR),
        ]);
        $connection->expects(self::once())->method('fetchOne')->with(
            self::stringContains("record_type = 'template-risk-scenario-model'"),
            [7, 11, '00000000-0000-4000-8000-000000000012']
        )->willReturn(false);

        self::assertFalse($this->table($connection)->containsEventRecord(
            7,
            11,
            '00000000-0000-4000-8000-000000000013'
        ));
    }

    public function testSaveReturnsNullForAStaleRevisionWithoutUpdatingTheEvidence(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->expects(self::once())->method('fetchAssociative')->willReturn([
            'id' => 10,
            'uuid' => '00000000-0000-4000-8000-000000000015',
            'revision' => 2,
        ]);
        $connection->expects(self::never())->method('update');

        self::assertNull($this->table($connection)->save(
            7,
            11,
            '00000000-0000-4000-8000-000000000013',
            'relevant',
            'A stale writer must not overwrite newer evidence.',
            1,
            8
        ));
    }

    private function table(Connection $connection): ScenarioTemplateEventEvaluationTable
    {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new ScenarioTemplateEventEvaluationTable($entityManager);
    }
}
