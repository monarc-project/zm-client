<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Command;

use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Command\OpenScenarioDueReviewsCommand;
use Monarc\FrontOffice\Scenario\Service\ScenarioReviewService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class OpenScenarioDueReviewsCommandTest extends TestCase
{
    public function testSkipsTheSchedulerWhenScenarioIsDisabled(): void
    {
        $reviews = $this->createMock(ScenarioReviewService::class);
        $reviews->expects(self::never())->method('openDueScheduledCases');
        $command = new OpenScenarioDueReviewsCommand($reviews, new ScenarioCapability(['enabled' => false]));
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Scenario feature is disabled', $tester->getDisplay());
    }
}
