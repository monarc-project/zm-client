<?php
namespace MonarcFO\Service;

use Zend\ServiceManager\ServiceLocatorInterface;

class AnrRiskOpServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceRiskOp',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
    );
}
