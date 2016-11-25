<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrAssetCommonServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity' => 'MonarcCore\Model\Entity\Asset',
        'table' => 'MonarcCore\Model\Table\AssetTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'amvTable' => 'MonarcCoreModel\Table\AmvTable',

        'clientity' => 'MonarcFO\Model\Entity\Asset',
        'clitable' => 'MonarcFO\Model\Table\AssetTable',
    );
}
