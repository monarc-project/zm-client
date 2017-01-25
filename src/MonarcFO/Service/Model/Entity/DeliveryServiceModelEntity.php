<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Delivery Service Model Entity
 *
 * Class DeliveryServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class DeliveryServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
