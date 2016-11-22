<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrRolfCategoryServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\RolfCategory',
        'table'=> 'MonarcFO\Model\Table\RolfCategoryTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    );
}
