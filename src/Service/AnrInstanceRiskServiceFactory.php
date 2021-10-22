<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Table;

/**
 * Proxy factory class to instantiate Monarc\Core's InstanceRiskService using Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceRiskServiceFactory extends AbstractServiceFactory
{
    protected $class = AnrInstanceRiskService::class;

    protected $ressources = [
        'table' => Table\InstanceRiskTable::class,
        'entity' => InstanceRisk::class,
        'amvTable' => Table\AmvTable::class,
        'anrTable' => Table\AnrTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'assetTable' => Table\AssetTable::class,
        'instanceTable' => Table\InstanceTable::class,
        'instanceRiskOwnerTable' =>  Table\InstanceRiskOwnerTable::class,
        'monarcObjectTable' => Table\MonarcObjectTable::class,
        'scaleTable' => Table\ScaleTable::class,
        'threatTable' => Table\ThreatTable::class,
        'vulnerabilityTable' => Table\VulnerabilityTable::class,
        'recommendationTable' => Table\RecommandationTable::class,
        'recommendationRiskTable' => Table\RecommandationRiskTable::class,
        'translateService' => TranslateService::class,
    ];
}
