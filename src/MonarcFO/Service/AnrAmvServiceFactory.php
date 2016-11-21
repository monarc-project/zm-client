<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrAmvServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(

        'entity'=> 'MonarcFO\Model\Entity\Amv',
        'table'=> 'MonarcFO\Model\Table\AmvTable',
    );
}
