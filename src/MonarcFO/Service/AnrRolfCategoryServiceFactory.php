<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Rolf Category Service Factory
 *
 * Class AnrRolfCategoryServiceFactory
 * @package MonarcFO\Service
 */
class AnrRolfCategoryServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\RolfCategory',
        'table'=> 'MonarcFO\Model\Table\RolfCategoryTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    );
}
