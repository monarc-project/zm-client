<?php
namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;
use \Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Anr Instance Risk Op Service Factory
 *
 * Class AnrInstanceRiskOpServiceFactory
 * @package MonarcFO\Service
 */
class AnrInstanceRiskOpServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\InstanceRiskOpService";

    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceRiskOp',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'rolfRiskTable' => 'MonarcFO\Model\Table\RolfRiskTable',
        'rolfTagTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
    );
}
