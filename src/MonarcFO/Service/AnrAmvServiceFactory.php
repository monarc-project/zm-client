<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Amv Service Factory
 *
 * Class AnrAmvServiceFactory
 * @package MonarcFO\Service
 */
class AnrAmvServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\Amv',
        'table'=> 'MonarcFO\Model\Table\AmvTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'assetTable' => 'MonarcFO\Model\Table\AssetTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'vulnerabilityTable' => 'MonarcFO\Model\Table\VulnerabilityTable',
        'measureTable' => 'MonarcFO\Model\Table\MeasureTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
    );
}
