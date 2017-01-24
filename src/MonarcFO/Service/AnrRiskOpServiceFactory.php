<?php
namespace MonarcFO\Service;

/**
 * Anr Risk Op Service Factory
 *
 * Class AnrRiskOpServiceFactory
 * @package MonarcFO\Service
 */
class AnrRiskOpServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceRiskOp',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'rolfRiskTable' => 'MonarcFO\Model\Table\RolfRiskTable',
        'rolfRiskService' => 'MonarcFO\Service\AnrRolfRiskService',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    );
}
