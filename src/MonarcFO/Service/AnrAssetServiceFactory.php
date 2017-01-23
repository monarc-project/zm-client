<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrAssetServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity' => 'MonarcFO\Model\Entity\Asset',
        'table' => 'MonarcFO\Model\Table\AssetTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',

        'amvTable' => 'MonarcFO\Model\Table\AmvTable',
		'amvEntity' => 'MonarcFO\Model\Entity\Amv',
		'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
		'threatEntity' => 'MonarcFO\Model\Entity\Threat',
		'themeTable' => 'MonarcFO\Model\Table\ThemeTable',
		'themeEntity' => 'MonarcFO\Model\Entity\Theme',
		'vulnerabilityTable' => 'MonarcFO\Model\Table\VulnerabilityTable',
		'vulnerabilityEntity' => 'MonarcFO\Model\Entity\Vulnerability',
		'measureTable' => 'MonarcFO\Model\Table\MeasureTable',
		'measureEntity' => 'MonarcFO\Model\Entity\Measure',
		'assetTable' => 'MonarcFO\Model\Table\AssetTable',
		'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
		'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
		'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
    );
}
