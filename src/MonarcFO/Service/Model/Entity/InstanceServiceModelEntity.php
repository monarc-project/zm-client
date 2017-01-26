<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Instance Service Model Entity
 *
 * Class InstanceServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class InstanceServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
