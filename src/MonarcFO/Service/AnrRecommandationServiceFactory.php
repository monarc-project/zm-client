<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrRecommandationServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\Recommandation',
        'table'=> 'MonarcFO\Model\Table\RecommandationTable',
        'anrTable'=> 'MonarcFO\Model\Table\AnrTable',
    );
}
