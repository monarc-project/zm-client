<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Paxxord Token Service Model Entity
 *
 * Class PasswordTokenServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class PasswordTokenServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
