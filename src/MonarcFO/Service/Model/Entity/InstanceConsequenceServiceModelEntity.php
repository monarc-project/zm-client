<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Instance Consequence Service Model Entity
 *
 * Class InstanceConsequenceServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class InstanceConsequenceServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
