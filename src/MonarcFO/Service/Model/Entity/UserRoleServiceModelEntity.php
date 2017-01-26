<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * User Role Service Model Entity
 *
 * Class UserRoleServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class UserRoleServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
