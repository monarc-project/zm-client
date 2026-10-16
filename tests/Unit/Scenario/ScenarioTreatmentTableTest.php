<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Table\ScenarioTreatmentTable;
use PHPUnit\Framework\TestCase;

final class ScenarioTreatmentTableTest extends TestCase
{
    public function testListsTreatmentsInPlanOrderWithNewRecordsAtTheBottom(): void
    {
        $queries = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static function (string $query) use (&$queries): array {
                $queries[] = $query;
                if (str_contains($query, 'FROM scenario_treatments t')) {
                    return [[
                        'id' => 10,
                        'uuid' => '00000000-0000-4000-8000-000000000010',
                        'revision' => 1,
                        'treatment_type' => 'modify',
                        'action_text' => 'First planned treatment.',
                        'owner_id' => null,
                        'due_at' => null,
                        'status' => 'planned',
                        'completion_evidence' => null,
                        'cancellation_rationale' => null,
                        'created_at' => '2026-10-16 09:00:00',
                        'updated_at' => '2026-10-16 09:00:00',
                    ]];
                }

                return [];
            }
        );
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioTreatmentTable($entityManager);

        $result = $table->list(7, '00000000-0000-4000-8000-000000000001');

        self::assertSame('First planned treatment.', $result['items'][0]['actionText']);
        self::assertStringContainsString('ORDER BY t.created_at ASC, t.id ASC', $queries[0]);
    }

    public function testProjectsImmutableRecommendationImportanceAndPosition(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 10,
            'uuid' => '00000000-0000-4000-8000-000000000010',
            'revision' => 1,
            'treatment_type' => 'modify',
            'action_text' => 'Apply guidance.',
            'owner_id' => null,
            'due_at' => null,
            'status' => 'planned',
            'completion_evidence' => null,
            'cancellation_rationale' => null,
            'created_at' => '2026-10-16 09:00:00',
            'updated_at' => '2026-10-16 09:00:00',
        ]);
        $connection->method('fetchAllAssociative')->willReturn([[
            'uuid' => '00000000-0000-4000-8000-000000000011',
            'revision' => 1,
            'recommendation_uuid' => '00000000-0000-4000-8000-000000000012',
            'recommendation_snapshot' => json_encode([
                'code' => 'REC-1',
                'description' => 'Harden remote access.',
                'importance' => 3,
                'position' => 2,
                'treatmentImportance' => 2,
                'recommendationSetLabel' => 'Security baseline',
            ], JSON_THROW_ON_ERROR),
            'status' => 'not_started',
            'owner_id' => null,
            'target_at' => null,
            'working_note' => null,
            'completion_evidence' => null,
            'not_applicable_rationale' => null,
            'implemented_by' => null,
            'implemented_at' => null,
        ]]);
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioTreatmentTable($entityManager);

        $result = $table->get(
            7,
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000010'
        );

        self::assertSame(3, $result['recommendations'][0]['importance']);
        self::assertSame(2, $result['recommendations'][0]['position']);
        self::assertSame(2, $result['recommendations'][0]['treatmentImportance']);
    }

    public function testCompletedTreatmentRejectsUnfinishedRecommendationImplementations(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->method('fetchAssociative')->willReturnCallback(
            static function (string $query): array|false {
                if (str_contains($query, 'FROM scenario_treatments t INNER JOIN scenario_risk_scenarios')) {
                    return [
                        'id' => 10,
                        'risk_scenario_id' => 11,
                        'risk_scenario_revision' => 3,
                        'revision' => 2,
                        'treatment_type' => 'modify',
                        'action_text' => 'Apply the control.',
                        'owner_id' => null,
                        'due_at' => null,
                        'status' => 'in_progress',
                        'completion_evidence' => null,
                        'cancellation_rationale' => null,
                    ];
                }

                return false;
            }
        );
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(false, 1, 1);
        $connection->expects(self::never())->method('update');

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioTreatmentTable($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Complete or mark every selected recommendation not applicable');
        $table->updateTreatment(
            7,
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            ['status' => 'completed'],
            3,
            2,
            9,
            'request-1',
            '00000000-0000-4000-8000-000000000003'
        );
    }

    public function testImplementedRecommendationRequiresCompletionEvidenceBeforeMutation(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->method('fetchAssociative')->willReturnCallback(
            static function (string $query): array|false {
                if (str_contains($query, 'FROM scenario_treatments t INNER JOIN scenario_risk_scenarios')) {
                    return [
                        'id' => 10,
                        'risk_scenario_id' => 11,
                        'risk_scenario_revision' => 3,
                        'revision' => 2,
                        'status' => 'in_progress',
                    ];
                }
                if (str_contains($query, 'FROM scenario_treatment_recommendation_implementations i')) {
                    return [
                        'id' => 12,
                        'revision' => 1,
                        'status' => 'in_progress',
                        'owner_id' => null,
                        'target_at' => null,
                        'working_note' => null,
                        'completion_evidence' => null,
                        'not_applicable_rationale' => null,
                        'implemented_by' => null,
                        'implemented_at' => null,
                    ];
                }

                return false;
            }
        );
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects(self::never())->method('update');

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioTreatmentTable($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Implemented recommendations require completion evidence');
        $table->updateImplementation(
            7,
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000004',
            ['status' => 'implemented'],
            3,
            2,
            9,
            'request-2',
            '00000000-0000-4000-8000-000000000005'
        );
    }

    public function testImplementedRecommendationEvidenceCannotBeChanged(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->method('fetchAssociative')->willReturnCallback(
            static function (string $query): array|false {
                if (str_contains($query, 'FROM scenario_treatments t INNER JOIN scenario_risk_scenarios')) {
                    return [
                        'id' => 10,
                        'risk_scenario_id' => 11,
                        'risk_scenario_revision' => 3,
                        'revision' => 2,
                        'status' => 'in_progress',
                    ];
                }
                if (str_contains($query, 'FROM scenario_treatment_recommendation_implementations i')) {
                    return [
                        'id' => 12,
                        'revision' => 1,
                        'status' => 'implemented',
                    ];
                }

                return false;
            }
        );
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects(self::never())->method('update');

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioTreatmentTable($entityManager);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('recommendation evidence is historical and cannot be changed');
        $table->updateImplementation(
            7,
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000004',
            ['workingNote' => 'Attempted historical rewrite.'],
            3,
            2,
            9,
            'request-3',
            '00000000-0000-4000-8000-000000000006'
        );
    }
}
