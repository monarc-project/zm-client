<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Rolf Category Service Model Entity
 *
 * Class RolfCategoryServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class RolfCategoryServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
