<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * User Service Factory
 *
 * Class UserServiceFactory
 * @package MonarcFO\Service
 */
class UserServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\UserTable',
        'userAnrTable' => '\MonarcFO\Model\Table\UserAnrTable',
        'userRoleEntity' => '\MonarcFO\Model\Entity\UserRole',
        'userRoleTable' => '\MonarcFO\Model\Table\UserRoleTable',
        'roleTable' => '\MonarcFO\Model\Table\UserRoleTable',
        'entity' => '\MonarcFO\Model\Entity\User',
        'userAnrService' => 'MonarcFO\Service\UserAnrService',
        'userRoleService' => 'MonarcFO\Service\UserRoleService',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'snapshotCliTable' => '\MonarcFO\Model\Table\SnapshotTable',
    ];
}