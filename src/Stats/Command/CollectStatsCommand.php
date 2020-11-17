<?php

namespace Monarc\FrontOffice\Stats\Command;

use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class CollectStatsCommand extends Command
{
    protected static $defaultName = 'monarc:collect-stats';

    /** @var StatsAnrService */
    private $statsAnrService;

    public function __construct(StatsAnrService $statsAnrService)
    {
        $this->statsAnrService = $statsAnrService;

        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('anrIds', InputArgument::OPTIONAL, 'Anr IDs list, comma separated', []);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('[' . date('Y-m-d H:i:s') . '] Start process.');

        try {
            $collectedAnrUuids = $this->statsAnrService->collectStats($input->getArguments()['anrIds']);
        } catch (Throwable $e) {
            $output->writeln('[' . date('Y-m-d H:i:s') . '] An error occurred: ' . $e->getMessage());

            return 1;
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] Collected anr UUIDs: ' . implode(', ', $collectedAnrUuids));

        return 0;
    }
}
