<?php
namespace MonarcFO\Service;

/**
 * Class AnrRiskServiceFactory
 * @package MonarcFO\Service
 */
class AnrRiskServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceRisk',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'vulnerabilityTable' => 'MonarcFO\Model\Table\VulnerabilityTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'translateService' => 'MonarcCore\Service\TranslateService'
    );
}
