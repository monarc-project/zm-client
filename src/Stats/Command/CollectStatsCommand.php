<?php

namespace Monarc\FrontOffice\Stats\Command;

use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        $output->writeln('Start datetime: ' . date('Y-m-d_H:i:s'));

        $collectedAnrUuids = $this->statsAnrService->collectStats($input->getArguments()['anrIds']);

        $output->writeln('[' . date('Y-m-d_H:i:s') . '] Collected anr UUIDs: ' . implode(', ', $collectedAnrUuids));

        return 0;
    }
}
