<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Anr Service Model Entity
 *
 * Class AnrServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class AnrServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
