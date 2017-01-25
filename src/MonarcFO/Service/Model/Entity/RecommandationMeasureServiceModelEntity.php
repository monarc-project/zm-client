<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Recommandation Measure Service Model Entity
 *
 * Class RecommandationMeasureServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class RecommandationMeasureServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
