<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Object Export Service Factory
 *
 * Class ObjectExportServiceFactory
 * @package MonarcFO\Service
 */
class ObjectExportServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ObjectExportService";

    protected $ressources = array(
        'table' => '\MonarcFO\Model\Table\ObjectTable',
        'entity' => '\MonarcFO\Model\Entity\Object',
        'assetExportService' => 'MonarcFO\Service\AssetExportService',
        'objectObjectService' => 'MonarcFO\Service\ObjectObjectService',
        'categoryTable' => '\MonarcFO\Model\Table\ObjectCategoryTable',
        'assetService' => 'MonarcFO\Service\AnrAssetService',
        'anrObjectCategoryTable' => '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'rolfTagTable' => '\MonarcFO\Model\Table\RolfTagTable',
        'rolfRiskTable' => '\MonarcFO\Model\Table\RolfRiskTable',
    );
}
