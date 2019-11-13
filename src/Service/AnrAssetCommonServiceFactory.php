<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrAssetCommonService
 * @package Monarc\FrontOffice\Service
 */
class AnrAssetCommonServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\Core\Model\Entity\Asset',
        'table' => 'Monarc\Core\Model\Table\AssetTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'amvTable' => 'Monarc\CoreModel\Table\AmvTable',
        'clientity' => 'Monarc\FrontOffice\Model\Entity\Asset',
        'clitable' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'coreServiceAsset' => 'Monarc\Core\Service\AssetService',
        'cliServiceAsset' => 'Monarc\FrontOffice\Service\AnrAssetService',
    ];
}
