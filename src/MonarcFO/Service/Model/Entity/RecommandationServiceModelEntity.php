<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Recommandation Service Model Entity
 *
 * Class RecommandationServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class RecommandationServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
