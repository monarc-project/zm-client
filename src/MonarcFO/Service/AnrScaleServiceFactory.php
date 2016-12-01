<?php
namespace MonarcFO\Service;

use Zend\ServiceManager\ServiceLocatorInterface;

class AnrScaleServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\ScaleTable',
        'entity' => 'MonarcFO\Model\Entity\Scale',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'AnrCheckStartedService' => 'MonarcFO\Service\AnrCheckStartedService',
        'scaleImpactTypeService' => 'MonarcFO\Service\AnrScaleTypeService',
    );
}
