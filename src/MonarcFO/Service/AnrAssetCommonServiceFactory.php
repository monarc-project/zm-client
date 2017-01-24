<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Asset Common Service Factory
 *
 * Class AnrAssetCommonServiceFactory
 * @package MonarcFO\Service
 */
class AnrAssetCommonServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        'entity' => 'MonarcCore\Model\Entity\Asset',
        'table' => 'MonarcCore\Model\Table\AssetTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'amvTable' => 'MonarcCoreModel\Table\AmvTable',

        'clientity' => 'MonarcFO\Model\Entity\Asset',
        'clitable' => 'MonarcFO\Model\Table\AssetTable',

        'coreServiceAsset' => 'MonarcCore\Service\AssetService',
		'cliServiceAsset' => 'MonarcFO\Service\AnrAssetService',
    );
}
