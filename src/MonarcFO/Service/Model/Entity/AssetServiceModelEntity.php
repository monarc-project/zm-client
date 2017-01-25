<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Asset Service Model Entity
 *
 * Class AssetServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class AssetServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
