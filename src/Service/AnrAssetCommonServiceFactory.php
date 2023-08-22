<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table;
use Monarc\Core\Table\AmvTable;

/**
 * Factory class attached to AnrAssetCommonService
 * @package Monarc\FrontOffice\Service
 */
class AnrAssetCommonServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\Core\Model\Entity\Asset',
        'table' => Table\AssetTable::class,
        'anrTable' => Table\AnrTable::class,
        'amvTable' => Table\AmvTable::class,
        'clientity' => 'Monarc\FrontOffice\Model\Entity\Asset',
        'clitable' => Table\AssetTable::class,
        'coreServiceAsset' => 'Monarc\Core\Service\AssetService',
        'cliServiceAsset' => 'Monarc\FrontOffice\Service\AnrAssetService',
    ];
}
