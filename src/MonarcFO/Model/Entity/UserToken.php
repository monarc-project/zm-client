<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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