<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrAmvService
 * @package Monarc\FrontOffice\Service
 */
class AnrAmvServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\Amv',
        'table' => 'Monarc\FrontOffice\Model\Table\AmvTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'assetTable' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'threatTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
        'vulnerabilityTable' => 'Monarc\FrontOffice\Model\Table\VulnerabilityTable',
        'measureTable' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'referentialTable' => 'Monarc\FrontOffice\Model\Table\ReferentialTable',
        'amvTable' => 'Monarc\FrontOffice\Model\Table\AmvTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'assetService' => 'Monarc\FrontOffice\Service\AnrAssetService',
        'threatService' => 'Monarc\FrontOffice\Service\AnrThreatService',
        'themeService' => 'Monarc\FrontOffice\Service\AnrThemeService',
        'vulnerabilityService' => 'Monarc\FrontOffice\Service\AnrVulnerabilityService',
    ];
}
