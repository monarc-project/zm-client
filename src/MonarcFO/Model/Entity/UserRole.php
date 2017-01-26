<?php
namespace MonarcFO\Model\Entity;

use MonarcCore\Model\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * User Role
 *
 * @ORM\Table(name="users_roles")
 * @ORM\Entity
 */
class UserRole extends AbstractEntity
{
    const SUPER_ADMIN_FO = 'superadminfo';

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var \MonarcFO\Model\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\User", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * })
     */
    protected $user;

    /**
     * @var string
     *
     * @ORM\Column(name="role", type="string", length=255, nullable=false)
     */
    protected $role;

    /**
     * @var string
     *
     * @ORM\Column(name="creator", type="string", length=255, nullable=true)
     */
    protected $creator;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    protected $createdAt;

    /**
     * @return \MonarcFO\Model\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param \MonarcFO\Model\Entity\User $user
     * @return UserAnr
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Role
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @param Role $role
     * @return UserRole
     */
    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }
}