<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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