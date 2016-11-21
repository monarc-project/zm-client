<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class UserServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table'=> '\MonarcFO\Model\Table\UserTable',
        'userRoleTable'=> '\MonarcFO\Model\Table\UserRoleTable',
        'entity'=> '\MonarcFO\Model\Entity\User',
        'userRoleService' => 'MonarcFO\Service\UserRoleService',
    );
}
