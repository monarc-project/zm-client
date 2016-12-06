<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

class AnrObjectServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table'=> '\MonarcFO\Model\Table\ObjectTable',
        'entity'=> '\MonarcFO\Model\Entity\Object',
        'anrObjectCategoryEntity' => 'MonarcFO\Model\Entity\AnrObjectCategory',
        'assetTable'=> '\MonarcFO\Model\Table\AssetTable',
        'assetService' => 'MonarcCore\Service\AssetService',
        'anrTable'=> '\MonarcFO\Model\Table\AnrTable',
        'anrObjectCategoryTable'=> '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'amvTable'=> '\MonarcFO\Model\Table\AmvTable',
        'categoryTable'=> '\MonarcFO\Model\Table\ObjectCategoryTable',
        'instanceTable'=> '\MonarcFO\Model\Table\InstanceTable',
        'modelTable'=> '\MonarcCore\Model\Table\ModelTable',
        'objectObjectTable'=> '\MonarcFO\Model\Table\ObjectObjectTable',
        'rolfTagTable'=> '\MonarcFO\Model\Table\RolfTagTable',
        //'modelService'=> 'MonarcFO\Service\ModelService',
        //'objectObjectService'=> 'MonarcFO\Service\ObjectObjectService', // to complete
        'objectExportService' => 'MonarcCore\Service\ObjectExportService',
    );

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
