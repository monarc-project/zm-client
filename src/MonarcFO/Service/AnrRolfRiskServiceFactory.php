<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrRolfRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\RolfRisk',
        'table'=> 'MonarcFO\Model\Table\RolfRiskTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    );
}
