<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Object Category Service Model Entity
 *
 * Class ObjectCategoryServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class ObjectCategoryServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
