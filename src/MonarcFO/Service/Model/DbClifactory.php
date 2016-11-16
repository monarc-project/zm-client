<?php

namespace MonarcFO\Service\Model;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class DbCliFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator){
        try{
            $serviceLocator->get('doctrine.entitymanager.orm_cli')->getConnection()->connect();
            return new \MonarcFO\Model\Db($serviceLocator->get('doctrine.entitymanager.orm_cli'));
        }catch(\Exception $e){
            return new \MonarcFO\Model\Db($serviceLocator->get('doctrine.entitymanager.orm_default'));
        }
    }
}
