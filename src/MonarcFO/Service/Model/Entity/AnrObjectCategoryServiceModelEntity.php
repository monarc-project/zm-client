<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Anr Object Category Service Model Entity
 *
 * Class AnrObjectCategoryServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class AnrObjectCategoryServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
