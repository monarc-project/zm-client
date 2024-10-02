<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\UserRoleSuperClass;
use Monarc\Core\Entity\UserSuperClass;

/**
 * @ORM\Table(name="users", uniqueConstraints={@ORM\UniqueConstraint(name="email", columns={"email"})})
 * @ORM\Entity
 */
class User extends UserSuperClass
{
    /**
     * @var Anr|null
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="current_anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $currentAnr;

    /**
     * @var ArrayCollection|UserAnr[]
     *
     * @ORM\OneToMany(targetEntity="UserAnr", orphanRemoval=true, mappedBy="user", cascade={"persist"})
     */
    protected $userAnrs;

    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->userAnrs = new ArrayCollection();
        if (!empty($data['userAnrs'])) {
            $this->setUserAnrs($data['userAnrs']);
        }
        if (isset($data['currentAnr'])) {
            $this->currentAnr = $data['currentAnr'];
        }
    }

    public function getCurrentAnr(): ?Anr
    {
        return $this->currentAnr;
    }

    public function setCurrentAnr(Anr $currentAnr): self
    {
        $this->currentAnr = $currentAnr;

        return $this;
    }

    public function getUserAnrs()
    {
        return $this->userAnrs;
    }

    /**
     * @param array|UserAnr[] $userAnrs
     */
    public function setUserAnrs(array $userAnrs): self
    {
        foreach ($userAnrs as $userAnr) {
            if ($userAnr instanceof UserAnr) {
                $this->addUserAnr($userAnr);
            }
        }

        return $this;
    }

    public function addUserAnr(UserAnr $userAnr): self
    {
        if (!$this->userAnrs->contains($userAnr)) {
            $this->userAnrs->add($userAnr);
            $userAnr->setUser($this);
        }

        return $this;
    }

    public function removeUserAnr(UserAnr $userAnr): self
    {
        if ($this->userAnrs->contains($userAnr)) {
            $this->userAnrs->removeElement($userAnr);
        }

        return $this;
    }

    protected function createRole(string $role): UserRoleSuperClass
    {
        return new UserRole($this, $role);
    }
}
