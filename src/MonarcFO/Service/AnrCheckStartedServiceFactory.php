<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Check Started Service Factory
 *
 * Class AnrCheckStartedServiceFactory
 * @package MonarcFO\Service
 */
class AnrCheckStartedServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
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
