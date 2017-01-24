<?php
namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;
use \Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Anr Object Category Service Factory
 *
 * Class AnrObjectCategoryServiceFactory
 * @package MonarcFO\Service
 */
class AnrObjectCategoryServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ObjectCategoryService";

    protected $ressources = array(
        'table' => '\MonarcFO\Model\Table\ObjectCategoryTable',
        'entity' => '\MonarcFO\Model\Entity\ObjectCategory',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'anrObjectCategoryTable' => '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'objectTable' => '\MonarcFO\Model\Table\ObjectTable',
        'rootTable' => 'MonarcFO\Model\Table\ObjectCategoryTable',
        'parentTable' => 'MonarcFO\Model\Table\ObjectCategoryTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    );
}
