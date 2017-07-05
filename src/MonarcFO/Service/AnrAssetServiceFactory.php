<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrAssetService
 * @package MonarcFO\Service
 */
class AnrAssetServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
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
    ];
}
