<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrAssetService
 * @package Monarc\FrontOffice\Service
 */
class AnrAssetServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\Asset',
        'table' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'amvTable' => 'Monarc\FrontOffice\Model\Table\AmvTable',
        'amvEntity' => 'Monarc\FrontOffice\Model\Entity\Amv',
        'threatTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
        'threatEntity' => 'Monarc\FrontOffice\Model\Entity\Threat',
        'themeTable' => 'Monarc\FrontOffice\Model\Table\ThemeTable',
        'themeEntity' => 'Monarc\FrontOffice\Model\Entity\Theme',
        'vulnerabilityTable' => 'Monarc\FrontOffice\Model\Table\VulnerabilityTable',
        'vulnerabilityEntity' => 'Monarc\FrontOffice\Model\Entity\Vulnerability',
        'measureTable' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'measureEntity' => 'Monarc\FrontOffice\Model\Entity\Measure',
        'assetTable' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'referentialCommonTable' => 'Monarc\FrontOffice\Model\Table\ReferentialTable',
        'referentialTable'  => 'Monarc\FrontOffice\Model\Table\ReferentialTable',
        'soaCategoryCommonTable' => 'Monarc\FrontOffice\Model\Table\SoaCategoryTable',
        'soaTable' => 'Monarc\FrontOffice\Model\Table\SoaTable',
    ];
}
