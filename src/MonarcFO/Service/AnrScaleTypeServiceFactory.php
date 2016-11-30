<?php
namespace MonarcFO\Service;

use Zend\ServiceManager\ServiceLocatorInterface;

class AnrScaleTypeServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
        'entity' => 'MonarcFO\Model\Entity\ScaleImpactType',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
    );
}
