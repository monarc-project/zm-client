<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrCheckStartedServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\Anr',
        'table'=> 'MonarcFO\Model\Table\AnrTable',
        'modelTable' => 'MonarcCore\Model\Table\ModelTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'instanceConsequenceTable' => 'MonarcFO\Model\Table\InstanceConsequenceTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'instanceRiskOpTable' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
    );
}
