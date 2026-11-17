<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrSupervisor;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Service\ScenarioAssessmentService;
use Monarc\FrontOffice\Scenario\Service\ScenarioQualitativeCalculationService;
use Monarc\FrontOffice\Scenario\Table\ScenarioAssessmentTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioReferenceTable;
use Monarc\FrontOffice\Scenario\Table\ScenarioTreatmentTable;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table\ReassessmentTriggerTable;
use PHPUnit\Framework\TestCase;

/** @covers \Monarc\FrontOffice\Scenario\Service\ScenarioAssessmentService */
final class ScenarioAssessmentServiceTest extends TestCase
{
    /** @dataProvider approverCases */
    public function testOnlyTheAssignedActiveLinkedApproverCanReachAcceptancePersistence(
        bool $authenticated,
        bool $linked,
        bool $active,
        bool $approver,
        ?int $assignedId,
        bool $allowed
    ): void {
        $db = $this->createMock(Connection::class);
        $em = $this->createMock(EntityManager::class);
        $em->method('getConnection')->willReturn($db);
        $anr = $this->createMock(Anr::class);
        $anr->method('getId')->willReturn(7);
        $actor = $this->createMock(User::class);
        $actor->method('getId')->willReturn(9);
        $users = $this->createMock(ConnectedUserService::class);
        $users->method('getConnectedUser')->willReturn($authenticated ? $actor : null);
        $supervisor = $this->createMock(AnrSupervisor::class);
        $supervisor->method('getId')->willReturn(11);
        $supervisor->method('isActive')->willReturn($active);
        $supervisor->method('hasRole')->with(AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER)
            ->willReturn($approver);
        $assigned = $assignedId === null ? null : $this->createMock(AnrSupervisor::class);
        $assigned?->method('getId')->willReturn($assignedId);
        $supervisors = $this->createMock(AnrSupervisorService::class);
        $supervisors->expects($authenticated ? self::once() : self::never())
            ->method('getResidualRiskApproverSupervisor')->with($anr, $assignedId)->willReturn($assigned);
        $supervisors->expects($authenticated ? self::once() : self::never())
            ->method('findLinkedSupervisor')->with($anr, $actor)->willReturn($linked ? $supervisor : null);
        $db->expects($allowed ? self::once() : self::never())->method('transactional')
            ->willReturnCallback(static fn (callable $operation) => $operation($db));
        // An authorized actor reaches the table; a stale/missing risk still cannot be accepted.
        $db->expects($allowed ? self::exactly(2) : self::never())->method('fetchAssociative')->willReturn(false);
        $db->expects(self::never())->method('insert');
        $service = new ScenarioAssessmentService(
            new ScenarioAssessmentTable($em),
            new ScenarioReferenceTable($em),
            new ScenarioTreatmentTable($em),
            new ScenarioQualitativeCalculationService(),
            $this->createMock(ReassessmentTriggerTable::class),
            $users,
            $supervisors
        );
        if (!$allowed) {
            $this->expectException(ActionForbiddenException::class);
        }
        self::assertNull($service->acceptResidualRisk($anr, 'risk', [
            'assignedSupervisorId' => $assignedId,
            'assessmentUuid' => 'assessment',
            'decision' => 'accepted',
            'reason' => 'Approved by the assigned supervisor.',
        ], 1, 'approver-check'));
    }

    public static function approverCases(): array
    {
        return [
            'assigned active approver' => [true, true, true, true, 11, true],
            'anonymous' => [false, true, true, true, 11, false],
            'unlinked' => [true, false, true, true, 11, false],
            'inactive' => [true, true, false, true, 11, false],
            'wrong role' => [true, true, true, false, 11, false],
            'different assigned supervisor' => [true, true, true, true, 12, false],
            'no assigned supervisor' => [true, true, true, true, null, false],
        ];
    }
}
