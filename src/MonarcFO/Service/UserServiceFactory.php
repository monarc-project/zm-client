<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class UserServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table'=> '\MonarcFO\Model\Table\UserTable',
        'userAnrTable'=> '\MonarcFO\Model\Table\UserAnrTable',
        'userRoleTable'=> '\MonarcFO\Model\Table\UserRoleTable',
        'entity'=> '\MonarcFO\Model\Entity\User',
        'userAnrService' => 'MonarcFO\Service\UserAnrService',
        'userRoleService' => 'MonarcFO\Service\UserRoleService',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
    );
}
