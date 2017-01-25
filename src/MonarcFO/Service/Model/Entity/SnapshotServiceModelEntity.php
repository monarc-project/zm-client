<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Snapshot Service Model Entity
 *
 * Class SnapshotServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class SnapshotServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
