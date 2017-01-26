<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Recommandation Risk Service Factory
 *
 * Class AnrRecommandationRiskServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecommandationRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\RecommandationRisk',
        'table' => 'MonarcFO\Model\Table\RecommandationRiskTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'recommandationTable' => 'MonarcFO\Model\Table\RecommandationTable',
        'recommandationMeasureTable' => 'MonarcFO\Model\Table\RecommandationMeasureTable',
        'recommandationHistoricTable' => 'MonarcFO\Model\Table\RecommandationHistoricTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'recommandationHistoricEntity' => 'MonarcFO\Model\Entity\RecommandationHistoric',
        'anrService' => 'MonarcFO\Service\AnrService',
        'anrInstanceService' => 'MonarcFO\Service\AnrInstanceService',
        'instanceTable' => '\MonarcFO\Model\Table\InstanceTable',
        'objectTable' => '\MonarcFO\Model\Table\ObjectTable',
    ];
}
