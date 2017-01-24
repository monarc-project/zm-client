<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * User Anr Service Factory
 *
 * Class UserAnrServiceFactory
 * @package MonarcFO\Service
 */
class UserAnrServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table' => '\MonarcFO\Model\Table\UserAnrTable',
        'entity' => '\MonarcFO\Model\Entity\UserAnr',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'userTable' => '\MonarcFO\Model\Table\UserTable',
    );
}