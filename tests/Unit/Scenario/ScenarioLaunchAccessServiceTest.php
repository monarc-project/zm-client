<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\Core\Scenario\Contract\AnalysisType;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrSupervisor;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\FrontOffice\Scenario\Service\ScenarioLaunchAccessService;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use PHPUnit\Framework\TestCase;

/** @covers \Monarc\FrontOffice\Scenario\Service\ScenarioLaunchAccessService */
final class ScenarioLaunchAccessServiceTest extends TestCase
{
    public function testAllowsAnActiveLinkedSupervisorToOpenAScenarioAnalysis(): void
    {
        $anr = $this->scenarioAnr();
        $user = $this->createMock(User::class);
        $anrTable = $this->createMock(AnrTable::class);
        $userAnrTable = $this->createMock(UserAnrTable::class);
        $supervisors = $this->createMock(AnrSupervisorService::class);
        $anrTable->expects(self::once())->method('findById')->with(78, false)->willReturn($anr);
        $userAnrTable->expects(self::once())->method('findByAnrAndUser')->with($anr, $user)->willReturn(null);
        $supervisors->expects(self::once())->method('findLinkedSupervisor')->with($anr, $user)
            ->willReturn($this->createMock(AnrSupervisor::class));

        self::assertTrue((new ScenarioLaunchAccessService($anrTable, $userAnrTable, $supervisors))
            ->canOpen($user, 78));
    }

    public function testAllowsAUserWithExplicitAnalysisAccess(): void
    {
        $anr = $this->scenarioAnr();
        $user = $this->createMock(User::class);
        $anrTable = $this->createMock(AnrTable::class);
        $userAnrTable = $this->createMock(UserAnrTable::class);
        $supervisors = $this->createMock(AnrSupervisorService::class);
        $anrTable->expects(self::once())->method('findById')->with(78, false)->willReturn($anr);
        $userAnrTable->expects(self::once())->method('findByAnrAndUser')->with($anr, $user)
            ->willReturn($this->createMock(UserAnr::class));
        $supervisors->expects(self::never())->method('findLinkedSupervisor');

        self::assertTrue((new ScenarioLaunchAccessService($anrTable, $userAnrTable, $supervisors))
            ->canOpen($user, 78));
    }

    public function testDeniesAnAssetAnalysisBeforeCheckingAccess(): void
    {
        $anr = $this->createMock(Anr::class);
        $anr->method('getAnalysisType')->willReturn(AnalysisType::ASSET);
        $user = $this->createMock(User::class);
        $anrTable = $this->createMock(AnrTable::class);
        $userAnrTable = $this->createMock(UserAnrTable::class);
        $supervisors = $this->createMock(AnrSupervisorService::class);
        $anrTable->expects(self::once())->method('findById')->with(78, false)->willReturn($anr);
        $userAnrTable->expects(self::never())->method('findByAnrAndUser');
        $supervisors->expects(self::never())->method('findLinkedSupervisor');

        self::assertFalse((new ScenarioLaunchAccessService($anrTable, $userAnrTable, $supervisors))
            ->canOpen($user, 78));
    }

    private function scenarioAnr(): Anr
    {
        $anr = $this->createMock(Anr::class);
        $anr->method('getAnalysisType')->willReturn(AnalysisType::SCENARIO);

        return $anr;
    }
}
