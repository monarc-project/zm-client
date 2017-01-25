<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Scale Service Model Entity
 *
 * Class ScaleServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class ScaleServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
