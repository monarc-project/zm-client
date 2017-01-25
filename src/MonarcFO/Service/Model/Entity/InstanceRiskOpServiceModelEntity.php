<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Instance Risk Op Service Model Entity
 *
 * Class InstanceRiskOpServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class InstanceRiskOpServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
