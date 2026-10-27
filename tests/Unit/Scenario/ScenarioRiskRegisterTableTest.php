<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Table\ScenarioRiskRegisterTable;
use Monarc\FrontOffice\Scenario\Validator\ScenarioRiskRegisterQuery;
use PHPUnit\Framework\TestCase;

final class ScenarioRiskRegisterTableTest extends TestCase
{
    public function testProjectsCurrentEvidenceWithOneBoundedRegisterQuerySet(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->willReturn('1');
        $connection->expects(self::exactly(2))->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [[
                'uuid' => '00000000-0000-4000-8000-000000000001',
                'title' => 'Payroll interruption',
                'narrative' => 'The payment service is unavailable.',
                'lifecycle' => 'active',
                'completeness' => 'complete',
                'source_title' => 'Provider outage',
                'event_title' => 'Payment outage',
                'consequence_title' => 'Delayed salary payment',
                'subjects' => 'Payroll process',
                'current_band' => 'high',
                'inherent_band' => 'critical',
                'proposed_residual_band' => 'medium',
                'current_explanation' => 'The highest impact controls the band.',
                'profile_version_uuid' => '00000000-0000-4000-8000-000000000002',
                'profile_version' => 'Operations v2',
                'treatment_state' => 'action_required',
                'treatment_owner' => 'Tenant B',
                'treatment_due_at' => '2026-10-21 09:00:00',
                'recommendation_progress' => '1/2',
                'review_state' => 'active',
                'review_case_uuid' => '00000000-0000-4000-8000-000000000003',
                'trigger_occurred' => 1,
                'acceptance_state' => 'decision_required',
                'updated_at' => '2026-10-20 09:00:00',
            ]],
            [[
                'uuid' => '00000000-0000-4000-8000-000000000002',
                'label' => 'Operations v2',
            ]]
        );
        $connection->expects(self::once())->method('fetchAssociative')->willReturn([
            'total' => 1,
            'treatment_action_required' => 1,
            'active_review' => 1,
            'due_review' => 0,
            'overdue_review' => 0,
            'decision_required' => 1,
            'band_low' => 0,
            'band_medium' => 0,
            'band_high' => 1,
            'band_critical' => 0,
            'no_current_assessment' => 0,
        ]);
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioRiskRegisterTable($entityManager);

        $page = $table->list(78, ScenarioRiskRegisterQuery::validate([]));

        self::assertSame('Provider outage', $page['items'][0]['context']['source']);
        self::assertSame('high', $page['items'][0]['evaluations']['current']);
        self::assertSame('action_required', $page['items'][0]['treatment']['state']);
        self::assertSame('active', $page['items'][0]['review']['state']);
        self::assertTrue($page['items'][0]['review']['triggerOccurred']);
        self::assertSame('decision_required', $page['items'][0]['acceptance']['state']);
        self::assertSame([
            'total' => 1,
            'treatmentActionRequired' => 1,
            'activeReview' => 1,
            'dueReview' => 0,
            'overdueReview' => 0,
            'decisionRequired' => 1,
            'bandLow' => 0,
            'bandMedium' => 0,
            'bandHigh' => 1,
            'bandCritical' => 0,
            'noCurrentAssessment' => 0,
        ], $page['summary']);
    }

    public function testProjectionUsesLatestEvidenceAndDoesNotMultiplyHistoricalReviewScopes(): void
    {
        $connection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioRiskRegisterTable($entityManager);
        $method = new \ReflectionMethod($table, 'projection');
        $projection = $method->invoke($table);

        self::assertStringContainsString("ca.assessment_type = 'current' ORDER BY ca.created_at DESC, ca.id DESC LIMIT 1", $projection);
        self::assertStringContainsString("ra.assessment_type = 'proposed_residual' ORDER BY ra.created_at DESC, ra.id DESC LIMIT 1", $projection);
        self::assertStringContainsString('EXISTS(SELECT 1 FROM scenario_review_cases arc', $projection);
        self::assertStringNotContainsString('scenario_review_case_scopes active_scope', $projection);
    }
}
