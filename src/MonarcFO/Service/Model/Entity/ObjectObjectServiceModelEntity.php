<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Object Object Service Model Entity
 *
 * Class ObjectObjectServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class ObjectObjectServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
