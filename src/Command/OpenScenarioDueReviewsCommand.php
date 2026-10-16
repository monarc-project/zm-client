<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Command;

use Monarc\FrontOffice\Scenario\Service\ScenarioReviewService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Opens each due Scenario scheduled review once; safe for a frequent cron invocation. */
final class OpenScenarioDueReviewsCommand extends Command
{
    protected static $defaultName = 'monarc:scenario-open-due-reviews';

    public function __construct(private ScenarioReviewService $reviews)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Open due Scenario scheduled-review cases idempotently.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->reviews->openDueScheduledCases();
        if ($result['skipped']) {
            $output->writeln('Scenario review scheduler is already running.');

            return Command::SUCCESS;
        }
        $output->writeln('Opened ' . $result['opened'] . ' due Scenario review case(s).');

        return Command::SUCCESS;
    }
}
