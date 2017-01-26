<?php
namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Instance Risk Service Factory
 *
 * Class AnrInstanceRiskServiceFactory
 * @package MonarcFO\Service
 */
class AnrInstanceRiskServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\InstanceRiskService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceRisk',
        'amvTable' => 'MonarcFO\Model\Table\AmvTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'assetTable' => 'MonarcFO\Model\Table\AssetTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'vulnerabilityTable' => 'MonarcFO\Model\Table\VulnerabilityTable',
    ];
}
