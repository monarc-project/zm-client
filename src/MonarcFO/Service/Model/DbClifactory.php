<?php
namespace MonarcFO\Service\Model;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Db Cli Factory
 *
 * Class DbCliFactory
 * @package MonarcFO\Service\Model
 */
class DbCliFactory implements FactoryInterface
{
    /**
     * Create Service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return \MonarcCore\Model\Db
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        try {
            $serviceLocator->get('doctrine.entitymanager.orm_cli')->getConnection()->connect();
            return new \MonarcCore\Model\Db($serviceLocator->get('doctrine.entitymanager.orm_cli'));
        } catch (\Exception $e) {
            return new \MonarcCore\Model\Db($serviceLocator->get('doctrine.entitymanager.orm_default'));
        }
    }
}
