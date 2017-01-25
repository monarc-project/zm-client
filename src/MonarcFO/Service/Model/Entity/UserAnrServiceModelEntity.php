<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * User Anr Service Model Entity
 *
 * Class UserAnrServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class UserAnrServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
