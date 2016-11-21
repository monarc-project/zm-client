<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrThreatServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(

        'entity'=> 'MonarcFO\Model\Entity\Threat',
        'table'=> 'MonarcFO\Model\Table\ThreatTable',
    );
}
