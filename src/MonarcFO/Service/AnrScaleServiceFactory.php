<?php
namespace MonarcFO\Service;

/**
 * Anr Scale Service Factory
 * Class AnrScaleServiceFactory
 * @package MonarcFO\Service
 */
class AnrScaleServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\ScaleTable',
        'entity' => 'MonarcFO\Model\Entity\Scale',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'AnrCheckStartedService' => 'MonarcFO\Service\AnrCheckStartedService',
        'scaleImpactTypeService' => 'MonarcFO\Service\AnrScaleTypeService',
    );
}
