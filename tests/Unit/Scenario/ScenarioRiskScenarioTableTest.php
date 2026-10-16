<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Table\ScenarioRiskScenarioTable;
use PHPUnit\Framework\TestCase;

final class ScenarioRiskScenarioTableTest extends TestCase
{
    public function testCompletenessForOneRiskScenarioDoesNotRepeatOtherRiskScenarioWarnings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->with(
            'SELECT * FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ? ORDER BY created_at ASC, uuid ASC',
            [7, 'a0b1c2d3-e4f5-6789-abcd-0123456789ab']
        )->willReturn([
            ['id' => 17, 'uuid' => 'a0b1c2d3-e4f5-6789-abcd-0123456789ab'],
        ]);
        $connection->method('fetchOne')->willReturn(1);

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioRiskScenarioTable($entityManager);

        $completeness = $table->completeness(7, 'a0b1c2d3-e4f5-6789-abcd-0123456789ab');

        self::assertTrue($completeness['complete']);
        self::assertSame([], $completeness['warnings']);
    }

    public function testCreateGeneratesInternalProvenanceWithoutAcceptingUserSuppliedLineage(): void
    {
        $connection = $this->createMock(Connection::class);
        $created = [];
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->method('insert')->willReturnCallback(
            static function (string $table, array $row) use (&$created): void {
                if ($table === 'scenario_risk_scenarios') {
                    $created = $row + [
                        'id' => 17,
                        'revision' => 1,
                        'created_at' => '2026-10-09 10:00:00',
                        'updated_at' => '2026-10-09 10:00:00',
                    ];
                }
            }
        );
        $connection->method('fetchAssociative')->willReturnCallback(
            static function (string $query) use (&$created) {
                if (str_contains($query, 'FROM scenario_risk_scenarios')) {
                    return $created;
                }

                return false;
            }
        );
        $connection->method('fetchAllAssociative')->willReturn([]);

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioRiskScenarioTable($entityManager);

        $scenario = $table->createScenario(7, 3, 10, [
            'title' => 'Supplier outage',
            'description' => 'Observed during the payroll review.',
            // A caller cannot set this field because the controller rejects it.
            'provenance' => 'untrusted-input',
        ], 'request-123');

        self::assertSame('Observed during the payroll review.', $scenario['description']);
        self::assertMatchesRegularExpression(
            '/^risk_scenario:[0-9a-f-]{36}$/',
            (string) $created['provenance']
        );
        self::assertNotSame('untrusted-input', $created['provenance']);
    }

    public function testDescriptionOnlyUpdateDoesNotNullTheRequiredTitle(): void
    {
        $connection = $this->createMock(Connection::class);
        $scenario = [
            'id' => 17,
            'uuid' => 'a0b1c2d3-e4f5-6789-abcd-0123456789ab',
            'anr_id' => 7,
            'title' => 'Supplier outage',
            'source_snapshot_uuid' => null,
            'source_instance_record_uuid' => null,
            'description' => 'Original context.',
            'revision' => 3,
            'created_at' => '2026-10-09 10:00:00',
            'updated_at' => '2026-10-09 10:00:00',
        ];
        $updates = [];
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->method('fetchAssociative')->willReturnCallback(
            static function (string $query) use (&$scenario, &$updates) {
                if (str_contains($query, 'FROM scenario_risk_scenarios')) {
                    return array_merge($scenario, $updates);
                }

                return false;
            }
        );
        $connection->method('fetchAllAssociative')->willReturn([]);
        $connection->method('update')->willReturnCallback(
            static function (string $table, array $fields) use (&$updates): int {
                if ($table === 'scenario_risk_scenarios') {
                    $updates = $fields;
                }

                return 1;
            }
        );

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioRiskScenarioTable($entityManager);

        $updated = $table->updateScenario(
            7,
            $scenario['uuid'],
            10,
            ['title' => null, 'description' => 'Updated context.'],
            3,
            'request-456'
        );

        self::assertSame('Updated context.', $updated['description']);
        self::assertSame('Supplier outage', $updated['title']);
        self::assertArrayNotHasKey('title', $updates);
    }

    public function testSummaryAddendumUpdatePreservesOtherEditableScenarioFields(): void
    {
        $connection = $this->createMock(Connection::class);
        $scenario = [
            'id' => 17,
            'uuid' => 'a0b1c2d3-e4f5-6789-abcd-0123456789ab',
            'anr_id' => 7,
            'title' => 'Supplier outage',
            'source_snapshot_uuid' => null,
            'source_instance_record_uuid' => null,
            'description' => 'Original context.',
            'summary_addendum' => null,
            'revision' => 3,
            'created_at' => '2026-10-09 10:00:00',
            'updated_at' => '2026-10-09 10:00:00',
        ];
        $updates = [];
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->method('fetchAssociative')->willReturnCallback(
            static function (string $query) use (&$scenario, &$updates) {
                if (str_contains($query, 'FROM scenario_risk_scenarios')) {
                    return array_merge($scenario, $updates);
                }

                return false;
            }
        );
        $connection->method('fetchAllAssociative')->willReturn([]);
        $connection->method('update')->willReturnCallback(
            static function (string $table, array $fields) use (&$updates): int {
                if ($table === 'scenario_risk_scenarios') {
                    $updates = $fields;
                }

                return 1;
            }
        );

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioRiskScenarioTable($entityManager);

        $updated = $table->updateScenario(
            7,
            $scenario['uuid'],
            10,
            ['summaryAddendum' => 'Priority during month-end processing.'],
            3,
            'request-789'
        );

        self::assertSame('Priority during month-end processing.', $updated['summaryAddendum']);
        self::assertSame('Original context.', $updated['description']);
        self::assertArrayNotHasKey('title', $updates);
        self::assertArrayNotHasKey('description', $updates);
    }

    public function testDeleteDoesNotRemoveAScenarioWhenTheRevisionIsStale(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->expects(self::once())->method('fetchAssociative')->with(
            'SELECT * FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ? FOR UPDATE',
            [7, 'a0b1c2d3-e4f5-6789-abcd-0123456789ab']
        )->willReturn(['revision' => 3]);
        $connection->expects(self::never())->method('delete');

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioRiskScenarioTable($entityManager);

        self::assertFalse($table->deleteScenario(
            7,
            'a0b1c2d3-e4f5-6789-abcd-0123456789ab',
            10,
            2,
            'request-123'
        ));
    }

    public function testDetailResolvesLabelsForLegacyLinksWithoutSnapshots(): void
    {
        $connection = $this->createMock(Connection::class);
        $scenario = [
            'id' => 17,
            'uuid' => 'a0b1c2d3-e4f5-6789-abcd-0123456789ab',
            'anr_id' => 7,
            'title' => 'Supplier outage',
            'source_snapshot_uuid' => null,
            'source_instance_record_uuid' => null,
            'description' => null,
            'summary_addendum' => null,
            'revision' => 3,
            'created_at' => '2026-10-09 10:00:00',
            'updated_at' => '2026-10-09 10:00:00',
        ];
        $connection->method('fetchAssociative')->willReturnCallback(
            static function (string $query) use ($scenario) {
                if (str_contains($query, 'FROM scenario_risk_scenarios')) {
                    return $scenario;
                }
                if (str_contains($query, 'FROM objects object')) {
                    return ['label' => 'Building'];
                }
                if (str_contains($query, 'FROM measures measure')) {
                    return [
                        'control_label' => 'Access control',
                        'referential_label' => 'ISO/IEC 27002:2022',
                    ];
                }

                return false;
            }
        );
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static function (string $query): array {
                if (str_contains($query, 'FROM scenario_subject_links')) {
                    return [[
                        'uuid' => 'subject-link',
                        'revision' => 1,
                        'target_type' => 'risk_scenario',
                        'target_uuid' => 'a0b1c2d3-e4f5-6789-abcd-0123456789ab',
                        'relationship_intent' => 'existing',
                        'description' => null,
                        'subject_type' => 'information',
                        'subject_reference_uuid' => 'information-uuid',
                        'reference_snapshot' => null,
                    ]];
                }
                if (str_contains($query, 'FROM scenario_control_links')) {
                    return [[
                        'uuid' => 'control-link',
                        'revision' => 1,
                        'target_type' => 'risk_scenario',
                        'target_uuid' => 'a0b1c2d3-e4f5-6789-abcd-0123456789ab',
                        'relationship_intent' => 'existing',
                        'description' => null,
                        'control_reference_uuid' => 'control-uuid',
                        'reference_snapshot' => null,
                    ]];
                }

                return [];
            }
        );
        $connection->method('fetchOne')->willReturn(2);

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $table = new ScenarioRiskScenarioTable($entityManager);

        $detail = $table->detail(7, $scenario['uuid']);

        self::assertSame('Building', $detail['subjectLinks'][0]['referenceSnapshot']['label']);
        self::assertSame(
            'Access control — ISO/IEC 27002:2022',
            $detail['controlLinks'][0]['referenceSnapshot']['label']
        );
    }
}
