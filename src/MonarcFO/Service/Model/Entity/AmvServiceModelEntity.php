<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Amv Service Model Entity
 *
 * Class AmvServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class AmvServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
