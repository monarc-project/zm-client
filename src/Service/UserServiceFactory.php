<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to UserService
 * @package Monarc\FrontOffice\Service
 */
class UserServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => '\Monarc\FrontOffice\Model\Table\UserTable',
        'userAnrTable' => '\Monarc\FrontOffice\Model\Table\UserAnrTable',
        'userRoleEntity' => '\Monarc\FrontOffice\Model\Entity\UserRole',
        'userRoleTable' => '\Monarc\FrontOffice\Model\Table\UserRoleTable',
        'roleTable' => '\Monarc\FrontOffice\Model\Table\UserRoleTable',
        'entity' => '\Monarc\FrontOffice\Model\Entity\User',
        'userAnrService' => 'Monarc\FrontOffice\Service\UserAnrService',
        'userRoleService' => 'Monarc\FrontOffice\Service\UserRoleService',
        'anrTable' => '\Monarc\FrontOffice\Model\Table\AnrTable',
        'snapshotCliTable' => '\Monarc\FrontOffice\Model\Table\SnapshotTable',
    ];
}
