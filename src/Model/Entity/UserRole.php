<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\UserRoleSuperClass;

/**
 * User Role
 *
 * @ORM\Table(name="users_roles")
 * @ORM\Entity
 */
class UserRole extends UserRoleSuperClass
{
}