<?php
namespace MonarcFO\Controller;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractControllerFactory implements FactoryInterface
{
    protected $serviceName;

    public function createService(ServiceLocatorInterface $serviceLocator){

        $class = substr(get_class($this),0,-7);

        if(class_exists($class)){
            $service = $this->getServiceName();
            if (empty($service)) {
                return new $class();
            } elseif (is_array($service)) {
                $sm = $serviceLocator->getServiceLocator();
                $sls = array();
                foreach ($service as $key => $value) {
                    $sls[$key] = $sm->get($value);
                }
                return new $class($sls);
            } else {
                return new $class($serviceLocator->getServiceLocator()->get($service));
            }
        } else {
            return false;
        }
    }

    public function getServiceName(){
        return $this->serviceName;
    }
}
