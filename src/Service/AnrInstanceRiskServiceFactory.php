<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use \Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy factory class to instantiate Monarc\Core's InstanceRiskService using Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceRiskServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\InstanceRiskService";

    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\InstanceRisk',
        'amvTable' => 'Monarc\FrontOffice\Model\Table\AmvTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'assetTable' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'scaleTable' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'threatTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
        'vulnerabilityTable' => 'Monarc\FrontOffice\Model\Table\VulnerabilityTable',
        'recommandationTable' => 'Monarc\FrontOffice\Model\Table\RecommandationTable',
        'recommandationRiskTable' => 'Monarc\FrontOffice\Model\Table\RecommandationRiskTable',
    ];
}
