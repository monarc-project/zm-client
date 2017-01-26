<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Measure Service Model Entity
 *
 * Class MeasureServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class MeasureServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
