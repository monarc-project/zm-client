<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr REcommandation Historic Service Factory
 *
 * Class AnrRecommandationHistoricServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecommandationHistoricServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\RecommandationHistoric',
        'table' => 'MonarcFO\Model\Table\RecommandationHistoricTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
