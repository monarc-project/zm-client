<?php

namespace Monarc\FrontOffice\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Monarc\FrontOffice\Model\Entity\StatsAnr;
// use Monarc\FrontOffice\Model\Table\StatsAnrTable;
// use Monarc\FrontOffice\Model\Entity\Anr;

class CollectStatsCommand extends Command
{
    protected static $defaultName = 'monarc:collect-stats';

    /** @var StatsAnrTable */
    // private $statsAnrTable;

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return 0;
    }
}
