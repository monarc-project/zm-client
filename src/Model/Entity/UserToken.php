<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\UserTokenSuperClass;

/**
 * @ORM\Table(name="user_tokens", indexes={
 *      @ORM\Index(name="user_id", columns={"user_id"})
 * })
 * @ORM\Entity
 */
class UserToken extends UserTokenSuperClass
{
}
