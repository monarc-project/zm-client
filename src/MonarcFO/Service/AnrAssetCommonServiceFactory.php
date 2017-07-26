<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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
