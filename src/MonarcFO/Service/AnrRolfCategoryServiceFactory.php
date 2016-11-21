<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrRolfCategoryServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\RolfTag',
        'table'=> 'MonarcFO\Model\Table\RolfTagTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    );
}
