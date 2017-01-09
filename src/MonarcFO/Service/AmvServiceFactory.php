<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

class AmvServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\AmvTable',
        'entity' => 'MonarcFO\Model\Entity\Amv',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'assetTable' => '\MonarcFO\Model\Table\AssetTable',
        'instanceTable'=> 'MonarcCore\Model\Table\InstanceTable',
        'measureTable' => '\MonarcFO\Model\Table\MeasureTable',
        'threatTable' => '\MonarcFO\Model\Table\ThreatTable',
        'vulnerabilityTable' => '\MonarcFO\Model\Table\VulnerabilityTable',
    );

    public function createService(ServiceLocatorInterface $serviceLocator){

        $class = "\\MonarcCore\\Service\\AmvService";

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
