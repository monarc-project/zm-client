<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to UserRoleService
 * @package MonarcFO\Service
 */
class UserRoleServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\UserRoleTable',
        'entity' => '\MonarcFO\Model\Entity\UserRole',
        'userAnrCliTable' => '\MonarcFO\Model\Table\UserAnrTable',
        'userTable' => '\MonarcFO\Model\Table\UserTable',
        'userRoleTable' => '\MonarcCore\Model\Table\UserRoleTable',
        'userRoleEntity' => '\MonarcCore\Model\Entity\UserRole',
        'userTokenTable' => '\MonarcCore\Model\Table\UserTokenTable',
    ];
}