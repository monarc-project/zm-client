<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Object Object Service Factory
 *
 * Class ObjectObjectServiceFactory
 * @package MonarcFO\Service
 */
class ObjectObjectServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ObjectObjectService";

    protected $ressources = array(
        'table' => '\MonarcFO\Model\Table\ObjectObjectTable',
        'entity' => '\MonarcFO\Model\Entity\ObjectObject',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => '\MonarcFO\Model\Table\UserAnrTable',
        'instanceTable' => '\MonarcFO\Model\Table\InstanceTable',
        'objectTable' => '\MonarcFO\Model\Table\ObjectTable',
        'childTable' => '\MonarcFO\Model\Table\ObjectTable',
        'fatherTable' => '\MonarcFO\Model\Table\ObjectTable',
    );
}
