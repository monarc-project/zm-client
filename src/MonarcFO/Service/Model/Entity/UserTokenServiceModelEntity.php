<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * User Token Service Model Entity
 *
 * Class UserTokenServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class UserTokenServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
