<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Instance Risk Service Model Entity
 *
 * Class InstanceRiskServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class InstanceRiskServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
