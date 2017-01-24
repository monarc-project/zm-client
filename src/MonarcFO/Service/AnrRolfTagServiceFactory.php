<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr ROlf Tag Service Factory
 *
 * Class AnrRolfTagServiceFactory
 * @package MonarcFO\Service
 */
class AnrRolfTagServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\RolfTag',
        'table'=> 'MonarcFO\Model\Table\RolfTagTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    );
}
