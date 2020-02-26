<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Table;
use Monarc\FrontOffice\Service;

/**
 * Factory class attached to AnrAmvService
 * @package Monarc\FrontOffice\Service
 */
class AnrAmvServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => Amv::class,
        'table' => Table\AmvTable::class,
        'anrTable' => Table\AnrTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'assetTable' => Table\AssetTable::class,
        'threatTable' => Table\ThreatTable::class,
        'vulnerabilityTable' => Table\VulnerabilityTable::class,
        'measureTable' => Table\MeasureTable::class,
        'referentialTable' => Table\ReferentialTable::class,
        'amvTable' => Table\AmvTable::class,
        'instanceTable' => Table\InstanceTable::class,
        'instanceRiskTable' => Table\InstanceRiskTable::class,
        'MonarcObjectTable' => Table\MonarcObjectTable::class,
        'assetService' => Service\AnrAssetService::class,
        'threatService' => Service\AnrThreatService::class,
        'vulnerabilityService' => Service\AnrVulnerabilityService::class,
        'themeTable' => Table\ThemeTable::class,
    ];
}
