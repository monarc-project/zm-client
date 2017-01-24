<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Object Service Factory
 *
 * Class ObjectServiceFactory
 * @package MonarcFO\Service
 */
class ObjectServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table'=> '\MonarcFO\Model\Table\ObjectTable',
        'entity'=> '\MonarcFO\Model\Entity\Object',

        'anrObjectCategoryEntity' => 'MonarcFO\Model\Entity\AnrObjectCategory',

        'amvTable'=> '\MonarcFO\Model\Table\AmvTable',
        'anrTable'=> '\MonarcFO\Model\Table\AnrTable',
        'userAnrTable'=> '\MonarcFO\Model\Table\UserAnrTable',
        'anrObjectCategoryTable'=> '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'assetTable'=> '\MonarcFO\Model\Table\AssetTable',
        'categoryTable'=> '\MonarcFO\Model\Table\ObjectCategoryTable',
        'instanceTable'=> '\MonarcFO\Model\Table\InstanceTable',
        'instanceRiskOpTable'=> '\MonarcFO\Model\Table\InstanceRiskOpTable',
        'objectObjectTable'=> '\MonarcFO\Model\Table\ObjectObjectTable',
        'rolfTagTable'=> '\MonarcFO\Model\Table\RolfTagTable',

        'assetService' => 'MonarcFO\Service\AssetService',
        'objectObjectService'=> 'MonarcFO\Service\ObjectObjectService',
        'instanceRiskOpService'=> 'MonarcFO\Service\AnrInstanceRiskOpService',
    );

    /**
     * Create Service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return bool
     */
    public function createService(ServiceLocatorInterface $serviceLocator){

        $class = "\\MonarcCore\\Service\\ObjectService";

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
