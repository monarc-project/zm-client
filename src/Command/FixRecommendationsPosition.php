<?php

namespace Monarc\FrontOffice\Command;

use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\RecommendationTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixRecommendationsPosition extends Command
{
    protected static $defaultName = 'monarc:fix-recommendations';

    /** @var AnrTable */
    private $anrTable;

    /** @var RecommendationTable */
    private $recommendationTable;

    public function __construct(AnrTable $anrTable, RecommendationTable $recommendationTable)
    {
        $this->anrTable = $anrTable;
        $this->recommendationTable = $recommendationTable;

        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('anrId', InputArgument::OPTIONAL, 'Anr ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $anrs = $input->getArgument('anrId')
            ? [$this->anrTable->findById($input->getArgument('anrId'))]
            : $this->anrTable->findAll();

        $updatedCount = [];
        foreach ($anrs as $anr) {
            $recommendationsWithEmptyPosition = $this->recommendationTable->findByAnrWithEmptyPosition($anr);
            $updatedCount[$anr->getId()] = 0;
            if (!empty($recommendationsWithEmptyPosition)) {
                $maxPosition = $this->recommendationTable->findMaxPosition(['anr' => $anr]);
                foreach ($recommendationsWithEmptyPosition as $recommendationWithEmptyPosition) {
                    $recommendationWithEmptyPosition->setPosition(++$maxPosition);

                    $this->recommendationTable->save($recommendationWithEmptyPosition);
                    $updatedCount[$anr->getId()]++;
                }
            }
        }

        $this->recommendationTable->flush();

        foreach ($updatedCount as $anrId => $count) {
            $output->writeln(['Anr ID: ' . $anrId . ', updated: ' . $count]);
        }

        return 0;
    }
}
