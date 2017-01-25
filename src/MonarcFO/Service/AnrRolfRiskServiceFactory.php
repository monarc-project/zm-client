<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr ROlf Risk Service Factory
 *
 * Class AnrRolfRiskServiceFactory
 * @package MonarcFO\Service
 */
class AnrRolfRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\RolfRisk',
        'table' => 'MonarcFO\Model\Table\RolfRiskTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'categoryTable' => 'MonarcFO\Model\Table\RolfCategoryTable',
        'tagTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'rolfTagTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceRiskOpTable' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
    ];
}
