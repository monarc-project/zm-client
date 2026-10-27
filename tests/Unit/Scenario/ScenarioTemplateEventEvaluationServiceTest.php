<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\FrontOffice\Scenario\Service\ScenarioTemplateEventEvaluationService;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateEventEvaluationTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateModelRiskScenarioDerivationTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTemplateSnapshotTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use PHPUnit\Framework\TestCase;

/** @covers \Monarc\FrontOffice\Scenario\Service\ScenarioTemplateEventEvaluationService */
final class ScenarioTemplateEventEvaluationServiceTest extends TestCase
{
    public function testNoTemplateIsAnHonestEmptyStateForAnAuthorisedWriter(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->willReturn(false);
        $user = $this->createMock(User::class);
        $anr = $this->anr();
        $users = $this->createMock(ConnectedUserService::class);
        $users->expects(self::once())->method('getConnectedUser')->willReturn($user);
        $permissions = $this->createMock(UserAnrTable::class);
        $permissions->expects(self::once())->method('findByAnrAndUser')->with($anr, $user)->willReturn(
            $this->writerLink()
        );

        $result = $this->service($connection, $users, $permissions)->list($anr);

        self::assertSame('no_template', $result['compatibility']);
        self::assertTrue($result['canEdit']);
        self::assertSame([], $result['groups']);
    }

    public function testReaderCannotSaveAnEvaluationBeforeAnySnapshotLookup(): void
    {
        $connection = $this->createMock(Connection::class);
        $user = $this->createMock(User::class);
        $anr = $this->anr();
        $users = $this->createMock(ConnectedUserService::class);
        $users->expects(self::exactly(2))->method('getConnectedUser')->willReturn($user);
        $permissions = $this->createMock(UserAnrTable::class);
        $reader = $this->createMock(UserAnr::class);
        $reader->method('hasWriteAccess')->willReturn(false);
        $permissions->expects(self::once())->method('findByAnrAndUser')->with($anr, $user)->willReturn($reader);

        $this->expectException(ActionForbiddenException::class);
        $this->service($connection, $users, $permissions)->save(
            $anr,
            '00000000-0000-4000-8000-000000000013',
            ['state' => 'relevant'],
            0,
            'request-1'
        );
    }

    private function service(
        Connection $connection,
        ConnectedUserService $users,
        UserAnrTable $permissions
    ): ScenarioTemplateEventEvaluationService {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new ScenarioTemplateEventEvaluationService(
            new ScenarioTemplateSnapshotTable($entityManager),
            new ScenarioTemplateEventEvaluationTable($entityManager),
            new ScenarioTemplateModelRiskScenarioDerivationTable($entityManager),
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
