<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Recommandation Measure Service Factory
 *
 * Class AnrRecommandationMeasureServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecommandationMeasureServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\RecommandationMeasure',
        'table' => 'MonarcFO\Model\Table\RecommandationMeasureTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'recommandationTable' => 'MonarcFO\Model\Table\RecommandationTable',
        'measureTable' => 'MonarcFO\Model\Table\MeasureTable',
    ];
}
