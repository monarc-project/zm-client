<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Library Service Factory
 *
 * Class AnrLibraryServiceFactory
 * @package MonarcFO\Service
 */
class AnrLibraryServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\AnrObjectService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\ObjectTable',
        'entity' => 'MonarcFO\Model\Entity\Object',
        'objectObjectTable' => 'MonarcFO\Model\Table\ObjectObjectTable',
        'objectService' => 'MonarcFO\Service\ObjectService',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
