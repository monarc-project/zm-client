<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Recommandation Historic Service Model Entity
 *
 * Class RecommandationHistoricServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class RecommandationHistoricServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
