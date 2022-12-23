<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHL.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Command;

use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportAnalysesCommand extends Command
{
    protected static $defaultName = 'monarc:import-analyses';

    private InstanceImportService $instanceImportService;

    private AnrTable $anrTable;

    public function __construct(InstanceImportService $instanceImportService, AnrTable $anrTable)
    {
        $this->anrTable = $anrTable;
        $this->instanceImportService = $instanceImportService;

        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('anrId', InputArgument::OPTIONAL, 'Anr ID to import');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasArgument('anrId')) {
            $anrId = (int)$input->getArgument('anrId');
            //$anr = $this->anrTable->findById((int)$input->getArgument('anrId'));
        } else {
            // TODO: fetch a single task of anrs import and import it.
            // we don't import all as the next cron will execute it. Added readme section about this.
            $anrId = 123; // taken from params of the task.
        }

        $output->writeln('The Analysis was successfully imported with anr ID ' . $anrId);

        return 0;
    }
}
