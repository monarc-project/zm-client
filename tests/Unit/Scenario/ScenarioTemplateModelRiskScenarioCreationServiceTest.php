<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\FrontOffice\Scenario\Service\ScenarioTemplateModelRiskScenarioCreationService;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioRiskScenarioTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateEventEvaluationTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateModelRiskScenarioDerivationTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use PHPUnit\Framework\TestCase;

/** @covers \Monarc\FrontOffice\Scenario\Service\ScenarioTemplateModelRiskScenarioCreationService */
final class ScenarioTemplateModelRiskScenarioCreationServiceTest extends TestCase
{
    public function testRetryReusesTheOriginalCreationRequestAcrossSnapshotChanges(): void
    {
        $data = [
            'idempotencyKey' => 'submission-20261022',
            'sourceRevision' => 1,
            'models' => [[
                'modelRecordUuid' => '00000000-0000-4000-8000-000000000001',
                'sourceRevision' => 1,
                'title' => 'Supplier interruption',
                'description' => null,
                'eventRecordUuids' => [],
            ]],
        ];
        $checksum = hash('sha256', json_encode([
            'idempotencyKey' => 'submission-20261022',
            'models' => [[
                'description' => null,
                'eventRecordUuids' => [],
                'modelRecordUuid' => '00000000-0000-4000-8000-000000000001',
                'sourceRevision' => 1,
                'title' => 'Supplier interruption',
            ]],
            'sourceRevision' => 1,
        ], JSON_THROW_ON_ERROR));
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional')->willReturnCallback(
            static fn (callable $operation): mixed => $operation()
        );
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('WHERE anr_id = ? AND idempotency_key = ?'),
            [7, 'submission-20261022']
        )->willReturn(['id' => 31, 'request_checksum' => $checksum]);
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([[
            'risk_scenario_uuid' => '00000000-0000-4000-8000-000000000099',
            'title' => 'Supplier interruption',
            'created_at' => '2026-10-22 10:00:00',
        ]]);
        $connection->expects(self::never())->method('insert');
        $user = $this->createMock(User::class);
        $users = $this->createMock(ConnectedUserService::class);
        $users->expects(self::once())->method('getConnectedUser')->willReturn($user);
        $permissions = $this->createMock(UserAnrTable::class);
        $permissions->expects(self::once())->method('findByAnrAndUser')->willReturn($this->writerLink());

        $result = $this->service($connection, $users, $permissions)->create(
            $this->anr(),
            $data,
            1,
            'correlation-1'
        );

        self::assertTrue($result['replayed']);
        self::assertSame('00000000-0000-4000-8000-000000000099', $result['items'][0]['riskScenarioUuid']);
    }

    private function service(
        Connection $connection,
        ConnectedUserService $users,
        UserAnrTable $permissions
    ): ScenarioTemplateModelRiskScenarioCreationService {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new ScenarioTemplateModelRiskScenarioCreationService(
            new ScenarioTemplateSnapshotTable($entityManager),
            new ScenarioTemplateEventEvaluationTable($entityManager),
            new ScenarioTemplateModelRiskScenarioDerivationTable($entityManager),
            new ScenarioRiskScenarioTable($entityManager),
            new ScenarioAnalysisTable($entityManager),
            $users,
            $permissions
        );
    }

    private function anr(): Anr
    {
        $anr = $this->createMock(Anr::class);
        $anr->method('getId')->willReturn(7);

        return $anr;
    }

    private function writerLink(): UserAnr
    {
        $link = $this->createMock(UserAnr::class);
        $link->method('hasWriteAccess')->willReturn(true);

        return $link;
    }
}
