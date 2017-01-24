<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * User Role Service Factory
 *
 * Class UserRoleServiceFactory
 * @package MonarcFO\Service
 */
class UserRoleServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table'=> '\MonarcFO\Model\Table\UserRoleTable',
        'entity'=> '\MonarcFO\Model\Entity\UserRole',
        'userAnrCliTable'=> '\MonarcFO\Model\Table\UserAnrTable',
        'userTable'=> '\MonarcFO\Model\Table\UserTable',
        'userRoleTable'=> '\MonarcCore\Model\Table\UserRoleTable',
        'userRoleEntity'=> '\MonarcCore\Model\Entity\UserRole',
        'userTokenTable'=> '\MonarcCore\Model\Table\UserTokenTable',
    );
}
