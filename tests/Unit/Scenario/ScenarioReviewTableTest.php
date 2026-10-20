<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Table\ScenarioReviewTable;
use PHPUnit\Framework\TestCase;

final class ScenarioReviewTableTest extends TestCase
{
    public function testProjectsReviewHistoryDatesWithoutLosingTheTableContext(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->with(
            'SELECT * FROM scenario_review_case_history WHERE review_case_id = ? ORDER BY created_at DESC, id DESC',
            [15]
        )->willReturn([[
            'uuid' => '00000000-0000-4000-8000-000000000015',
            'action' => 'scenario_review_case_created',
            'actor_id' => 7,
            'prior_status' => null,
            'new_status' => 'open',
            'reason' => null,
            'scope_snapshot' => null,
            'evidence_ids' => null,
            'created_at' => '2026-10-19 10:15:00',
        ]]);
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioReviewTable($entityManager);

        $method = new \ReflectionMethod($table, 'projectHistory');
        $history = $method->invoke($table, 15);

        self::assertSame('2026-10-19T10:15:00Z', $history[0]['createdAt']);
        self::assertSame(7, $history[0]['actorId']);
    }

    public function testProjectsTheLastReviewCaseUpdateTime(): void
    {
        $connection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioReviewTable($entityManager);
        $method = new \ReflectionMethod($table, 'projectCaseSummary');
        $summary = $method->invoke($table, [
            'uuid' => '00000000-0000-4000-8000-000000000016',
            'reason' => 'manual',
            'status' => 'open',
            'scope_type' => 'analysis_wide',
            'scheduled_for' => null,
            'occurrence_context_at' => null,
            'revision' => 1,
            'created_at' => '2026-10-19 10:15:00',
            'updated_at' => '2026-10-19 11:15:00',
        ]);

        self::assertSame('2026-10-19T11:15:00Z', $summary['updatedAt']);
    }
}
