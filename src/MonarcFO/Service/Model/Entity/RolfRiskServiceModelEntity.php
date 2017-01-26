<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Rolf Risk Service Model Entity
 *
 * Class RolfRiskServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class RolfRiskServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
