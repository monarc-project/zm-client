<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\UserRoleSuperClass;

/**
 * @ORM\Table(name="users_roles")
 * @ORM\Entity
 */
class UserRole extends UserRoleSuperClass
{
    public const SUPER_ADMIN_FO = 'superadminfo';
    public const USER_FO = 'userfo';
    public const USER_ROLE_CEO = 'ceo';
    public const USER_ROLE_SYSTEM = 'system';

    public static function getAvailableRoles(): array
    {
        return [static::SUPER_ADMIN_FO, static::USER_FO, static::USER_ROLE_CEO];
    }
}
