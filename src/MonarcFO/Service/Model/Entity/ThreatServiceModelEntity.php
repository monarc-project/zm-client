<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Threat Service Model Entity
 *
 * Class ThreatServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class ThreatServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
