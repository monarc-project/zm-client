<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Anr Object Service Factory
 *
 * Class AnrObjectServiceFactory
 * @package MonarcFO\Service
 */
class AnrObjectServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table' => '\MonarcFO\Model\Table\ObjectTable',
        'entity' => '\MonarcFO\Model\Entity\Object',
        'anrObjectCategoryEntity' => 'MonarcFO\Model\Entity\AnrObjectCategory',
        'assetTable' => '\MonarcFO\Model\Table\AssetTable',
        'assetService' => 'MonarcFO\Service\AnrAssetService',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'anrObjectCategoryTable' => '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'amvTable' => '\MonarcFO\Model\Table\AmvTable',
        'categoryTable' => '\MonarcFO\Model\Table\ObjectCategoryTable',
        'instanceTable' => '\MonarcFO\Model\Table\InstanceTable',
        'modelTable' => '\MonarcCore\Model\Table\ModelTable',
        'objectObjectTable' => '\MonarcFO\Model\Table\ObjectObjectTable',
        'rolfTagTable' => '\MonarcFO\Model\Table\RolfTagTable',
        'modelService' => 'MonarcCore\Service\ModelService',
        'objectObjectService' => 'MonarcFO\Service\ObjectObjectService',
        'objectExportService' => 'MonarcFO\Service\ObjectExportService',
        'selfCoreService' => 'MonarcCore\Service\ObjectService',
        'instanceRiskOpTable' => '\MonarcFO\Model\Table\InstanceRiskOpTable',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
    );
}
