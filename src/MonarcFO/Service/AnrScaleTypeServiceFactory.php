<?php
namespace MonarcFO\Service;

/**
 * Anr Scale Type Service Factory
 *
 * Class AnrScaleTypeServiceFactory
 * @package MonarcFO\Service
 */
class AnrScaleTypeServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
        'entity' => 'MonarcFO\Model\Entity\ScaleImpactType',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceConsequenceService' => 'MonarcFO\Service\AnrInstanceConsequenceService'
    );
}
