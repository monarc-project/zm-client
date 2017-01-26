<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Object Service Factory
 *
 * Class ObjectServiceFactory
 * @package MonarcFO\Service
 */
class ObjectServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ObjectService";

    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\ObjectTable',
        'entity' => '\MonarcFO\Model\Entity\Object',
        'anrObjectCategoryEntity' => 'MonarcFO\Model\Entity\AnrObjectCategory',
        'amvTable' => '\MonarcFO\Model\Table\AmvTable',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => '\MonarcFO\Model\Table\UserAnrTable',
        'anrObjectCategoryTable' => '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'assetTable' => '\MonarcFO\Model\Table\AssetTable',
        'categoryTable' => '\MonarcFO\Model\Table\ObjectCategoryTable',
        'instanceTable' => '\MonarcFO\Model\Table\InstanceTable',
        'instanceRiskOpTable' => '\MonarcFO\Model\Table\InstanceRiskOpTable',
        'objectObjectTable' => '\MonarcFO\Model\Table\ObjectObjectTable',
        'rolfTagTable' => '\MonarcFO\Model\Table\RolfTagTable',
        'assetService' => 'MonarcFO\Service\AssetService',
        'objectObjectService' => 'MonarcFO\Service\ObjectObjectService',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
    ];
}
