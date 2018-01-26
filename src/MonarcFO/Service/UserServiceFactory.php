<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to UserService
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