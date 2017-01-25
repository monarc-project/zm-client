<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Rolf Tag Service Model Entity
 *
 * Class RolfTagServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class RolfTagServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
