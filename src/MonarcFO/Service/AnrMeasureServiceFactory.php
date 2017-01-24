<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Measure Service Factory
 *
 * Class AnrMeasureServiceFactory
 * @package MonarcFO\Service
 */
class AnrMeasureServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity' => 'MonarcFO\Model\Entity\Measure',
        'table' => 'MonarcFO\Model\Table\MeasureTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    );
}
