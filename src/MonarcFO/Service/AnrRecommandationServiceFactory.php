<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Recommandation Service Factory
 *
 * Class AnrRecommandationServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecommandationServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        'entity' => 'MonarcFO\Model\Entity\Recommandation',
        'table' => 'MonarcFO\Model\Table\RecommandationTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    );
}
