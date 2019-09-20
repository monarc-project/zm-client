<?php

namespace Monarc\FrontOffice\Service\Model;

use Interop\Container\ContainerInterface;
use Monarc\FrontOffice\Model\DbCli;
use Zend\ServiceManager\Factory\FactoryInterface;

class DbCliFactory  implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new DbCli($container->get('doctrine.entitymanager.orm_cli'));
    }
}
