<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Scenario\Table\ScenarioRiskScenarioTable;
use PHPUnit\Framework\TestCase;

final class ScenarioRiskScenarioTableTest extends TestCase
{
    public function testDeleteDoesNotRemoveAScenarioWhenTheRevisionIsStale(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $connection->expects(self::once())->method('fetchAssociative')->with(
            'SELECT * FROM scenario_risk_scenarios WHERE anr_id = ? AND uuid = ?',
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
}
