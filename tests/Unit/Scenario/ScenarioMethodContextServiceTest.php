<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\ReassessmentTriggerService as CoreReassessmentTriggerService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\ReassessmentTrigger;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Model\Table\QuestionTable;
use Monarc\FrontOffice\Scenario\Service\ScenarioMethodContextService;
use Monarc\FrontOffice\Scenario\Service\ScenarioReviewService;
use Monarc\FrontOffice\Scenario\Table\ScenarioAnalysisTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioAssessmentTable;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\ReassessmentTriggerTable;
use PHPUnit\Framework\TestCase;

final class ScenarioMethodContextServiceTest extends TestCase
{
    public function testCreatesAnAnalysisLocalFreeTextTrendQuestion(): void
    {
        $anr = $this->createMock(Anr::class);
        $anr->method('getId')->willReturn(78);
        $anr->method('getLanguage')->willReturn(1);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(8);
        $user->method('getEmail')->willReturn('writer@example.test');

        $connectedUserService = $this->createMock(ConnectedUserService::class);
        $connectedUserService->method('getConnectedUser')->willReturn($user);

        $questionTable = $this->createMock(QuestionTable::class);
        $questionTable->expects(self::once())->method('findMaxPositionByAnr')->with($anr)->willReturn(3);
        $questionTable->expects(self::once())->method('saveEntity')->with(self::callback(
            static function (Question $question) use ($anr): bool {
                return $question->getAnr() === $anr
                    && $question->getLabel(1) === 'Which supplier trends require follow-up?'
                    && $question->getType() === 1
                    && !$question->isMultiChoice()
                    && $question->getPosition() === 4
                    && $question->getResponse() === '';
            }
        ))->willReturnCallback(static function (Question $question): void {
            (new \ReflectionProperty($question, 'id'))->setValue($question, 24);
        });

        $service = $this->service($questionTable, $connectedUserService);
        $result = $service->createTrendQuestion(
            $anr,
            ['label' => 'Which supplier trends require follow-up?'],
            'correlation-id'
        );

        self::assertSame(24, $result['id']);
        self::assertSame('Which supplier trends require follow-up?', $result['label']);
        self::assertSame(1, $result['type']);
        self::assertFalse($result['multiple']);
    }

    public function testPositionOnlyUpdateRetainsTriggerTextAndStatus(): void
    {
        $anr = $this->createMock(Anr::class);
        $anr->method('getId')->willReturn(78);

        $trigger = (new ReassessmentTrigger())
            ->setAnr($anr)
            ->setTriggerType('Periodic review')
            ->setDescription('Review the risk assessment annually.')
            ->setMonitoringApproach('Monitor the review plan.')
            ->setIsActive(true)
            ->setPosition(2);
        (new \ReflectionProperty($trigger, 'id'))->setValue($trigger, 12);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(8);
        $user->method('getEmail')->willReturn('writer@example.test');

        $connectedUserService = $this->createMock(ConnectedUserService::class);
        $connectedUserService->method('getConnectedUser')->willReturn($user);

        $triggerTable = $this->createMock(ReassessmentTriggerTable::class);
        $triggerTable->method('findByIdAndAnr')->with(12, $anr)->willReturn($trigger);
        $triggerTable->method('findMaxPosition')->with(['anr' => $anr])->willReturn(2);
        $triggerTable->expects(self::once())->method('incrementPositions')->with(
            1,
            1,
            1,
            ['anr' => $anr],
            'writer@example.test'
        );
        $triggerTable->expects(self::once())->method('save')->with($trigger);

        $service = $this->service(
            $this->createMock(QuestionTable::class),
            $connectedUserService,
            $triggerTable
        );

        $result = $service->updateReassessmentTrigger($anr, 12, ['position' => 1], 'correlation-id');

        self::assertSame(1, $result['position']);
        self::assertSame('Periodic review', $result['triggerType']);
        self::assertSame('Review the risk assessment annually.', $result['description']);
        self::assertSame('Monitor the review plan.', $result['monitoringApproach']);
        self::assertTrue($result['isActive']);
    }

    private function service(
        QuestionTable $questionTable,
        ConnectedUserService $connectedUserService,
        ?ReassessmentTriggerTable $triggerTable = null
    ): ScenarioMethodContextService {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new ScenarioMethodContextService(
            $questionTable,
            $triggerTable ?? $this->createMock(ReassessmentTriggerTable::class),
            $this->createMock(AnrTable::class),
            new ScenarioAnalysisTable($entityManager),
            new ScenarioAssessmentTable($entityManager),
            $this->createMock(ScenarioReviewService::class),
            $this->createMock(CoreReassessmentTriggerService::class),
            $connectedUserService
        );
    }
}
