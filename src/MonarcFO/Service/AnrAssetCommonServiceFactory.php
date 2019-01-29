<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrAssetCommonService
 * @package MonarcFO\Service
 */
class AnrAssetCommonServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcCore\Model\Entity\Asset',
        'table' => 'MonarcCore\Model\Table\AssetTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'amvTable' => 'MonarcCoreModel\Table\AmvTable',
        'clientity' => 'MonarcFO\Model\Entity\Asset',
        'clitable' => 'MonarcFO\Model\Table\AssetTable',
        'coreServiceAsset' => 'MonarcCore\Service\AssetService',
        'cliServiceAsset' => 'MonarcFO\Service\AnrAssetService',
    ];
}
