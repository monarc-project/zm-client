<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\PasswordTokenSuperClass;

/**
 * Password Token
 *
 * @ORM\Table(name="passwords_tokens", indexes={
 *      @ORM\Index(name="user_id", columns={"user_id"})
 * }), uniqueConstraints={@ORM\UniqueConstraint(name="token", columns={"token"})}
 * @ORM\Entity
 */
class PasswordToken extends PasswordTokenSuperClass
{
    /**
     * @var \MonarcCore\Model\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\User", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $user;
}