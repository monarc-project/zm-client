<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

class ObjectObjectServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table'=>  '\MonarcFO\Model\Table\ObjectObjectTable',
        'entity'=> '\MonarcFO\Model\Entity\ObjectObject',
        'anrTable'=> '\MonarcFO\Model\Table\AnrTable',
        'instanceTable'=> '\MonarcFO\Model\Table\InstanceTable',
        'objectTable'=> '\MonarcFO\Model\Table\ObjectTable',
        'childTable'=> '\MonarcFO\Model\Table\ObjectTable',
        'fatherTable'=> '\MonarcFO\Model\Table\ObjectTable',
    );

    public function createService(ServiceLocatorInterface $serviceLocator){

        $class = "\\MonarcCore\\Service\\ObjectObjectService";

        if(class_exists($class)){
            $ressources = $this->getRessources();
            if (empty($ressources)) {
                $instance = new $class();
            } elseif (is_array($ressources)) {
                $sls = array();
                foreach ($ressources as $key => $value) {
                    $sls[$key] = $serviceLocator->get($value);
                }
                $instance = new $class($sls);
            } else {
                $instance = new $class($serviceLocator->get($ressources));
            }

            $instance->setLanguage($this->getDefaultLanguage($serviceLocator));
            $conf = $serviceLocator->get('Config');
            $instance->setMonarcConf(isset($conf['monarc'])?$conf['monarc']:array());

            return $instance;
        }else{
            return false;
        }
    }
}
