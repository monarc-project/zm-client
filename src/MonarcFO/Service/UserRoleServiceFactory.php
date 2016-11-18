<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class UserRoleServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'userAnrCliTable'=> '\MonarcFO\Model\Table\UserAnrTable',
        'userRoleTable'=> '\MonarcCore\Model\Table\UserRoleTable',
        'userRoleEntity'=> '\MonarcCore\Model\Entity\UserRole',
        'userTokenTable'=> '\MonarcCore\Model\Table\UserTokenTable',
    );
}
