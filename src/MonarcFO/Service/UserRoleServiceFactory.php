<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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