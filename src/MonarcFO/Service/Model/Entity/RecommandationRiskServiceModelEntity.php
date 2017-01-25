<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Recommandation Risk Service Model Entity
 *
 * Class RecommandationRiskServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class RecommandationRiskServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
