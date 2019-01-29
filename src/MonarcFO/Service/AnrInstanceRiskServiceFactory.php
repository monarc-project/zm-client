<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy factory class to instantiate MonarcCore's InstanceRiskService using MonarcFO's services
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
        'MonarcObjectTable' => 'MonarcFO\Model\Table\MonarcObjectTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'vulnerabilityTable' => 'MonarcFO\Model\Table\VulnerabilityTable',
        'recommandationTable' => 'MonarcFO\Model\Table\RecommandationTable',
    ];
}
