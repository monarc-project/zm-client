<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Command;

use Doctrine\ORM\EntityManagerInterface;
use Monarc\Core\Service\DoctrineProxyGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class GenerateDoctrineProxiesCommand extends Command
{
    protected static $defaultName = 'monarc:doctrine:generate-proxies';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DoctrineProxyGenerator $proxyGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Generate Doctrine proxy classes for the default entity manager.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->proxyGenerator->generate($this->entityManager);
        $output->writeln(sprintf('Generated %d Doctrine proxy class(es).', $count));

        return Command::SUCCESS;
    }
}
