<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrRecommandationMeasureServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\RecommandationMeasure',
        'table'=> 'MonarcFO\Model\Table\RecommandationMeasureTable',
        'anrTable'=> 'MonarcFO\Model\Table\AnrTable',
        'recommandationTable'=> 'MonarcFO\Model\Table\RecommandationTable',
        'measureTable'=> 'MonarcFO\Model\Table\MeasureTable',
    );
}
