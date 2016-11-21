<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrAssetServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(

        'entity'=> 'MonarcFO\Model\Entity\Asset',
        'table'=> 'MonarcFO\Model\Table\AssetTable',
    );
}
