<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrRecommandationHistoricServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\RecommandationHistoric',
        'table'=> 'MonarcFO\Model\Table\RecommandationHistoricTable',
    );
}
