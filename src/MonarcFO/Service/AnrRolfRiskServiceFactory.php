<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrRolfRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\RolfRisk',
        'table'=> 'MonarcFO\Model\Table\RolfRiskTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    	'categoryTable' => 'MonarcFO\Model\Table\RolfCategoryTable',
    	'tagTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'rolfTagTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'instanceRiskOpTable' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    );
}
