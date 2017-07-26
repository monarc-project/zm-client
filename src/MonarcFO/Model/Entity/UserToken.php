<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Model\Entity;

use MonarcCore\Model\Entity\UserTokenSuperClass;
use Doctrine\ORM\Mapping as ORM;

/**
 * User Token
 *
 * @ORM\Table(name="user_tokens", indexes={
 *      @ORM\Index(name="user_id", columns={"user_id"})
 * })
 * @ORM\Entity
 */
class UserToken extends UserTokenSuperClass
{
}