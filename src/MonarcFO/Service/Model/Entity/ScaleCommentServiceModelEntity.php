<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Scale Comment Service Model Entity
 *
 * Class ScaleCommentServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class ScaleCommentServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
