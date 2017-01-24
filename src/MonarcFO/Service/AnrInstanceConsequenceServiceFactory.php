<?php
namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;
use \Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Anr Instance Consequence Service Factory
 *
 * Class AnrInstanceConsequenceServiceFactory
 * @package MonarcFO\Service
 */
class AnrInstanceConsequenceServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
    	'table' => 'MonarcFO\Model\Table\InstanceConsequenceTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceConsequence',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'scaleImpactTypeTable' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
    );

    /**
     * Create Service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return bool
     */
    public function createService(ServiceLocatorInterface $serviceLocator){

        $class = "\\MonarcCore\\Service\\InstanceConsequenceService";

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
