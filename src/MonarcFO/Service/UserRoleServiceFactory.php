<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class UserRoleServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'userCliTable'=> '\MonarcFO\Model\Table\UserTable',
        'userRoleTable'=> '\MonarcCore\Model\Table\UserRoleTable',
        'userRoleEntity'=> '\MonarcCore\Model\Entity\UserRole',
        'userTokenTable'=> '\MonarcCore\Model\Table\UserTokenTable',
    );
}
