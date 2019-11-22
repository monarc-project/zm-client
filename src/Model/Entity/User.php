<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\UserRoleSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;

/**
 * User
 *
 * @ORM\Table(name="users", uniqueConstraints={@ORM\UniqueConstraint(name="email", columns={"email"})})
 * @ORM\Entity
 */
class User extends UserSuperClass
{
    /**
     * @var Anr|null
     *
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"persist"})
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

    /**
     * @return ArrayCollection|UserAnr[]
     */
    public function getUserAnrs(): Collection
    {
        return $this->userAnrs;
    }

    public function getUserAnrByAnrId(int $anrId): ?UserAnr
    {
        foreach ($this->userAnrs as $userAnr) {
            if ($userAnr->getAnr()->getId() === $anrId) {
                return $userAnr;
            }
        }

        return null;
    }

    /**
     * @param array|UserAnr[] $userAnrs
     */
    public function setUserAnrs(array $userAnrs): self
    {
        $this->userAnrs = new ArrayCollection();
        foreach ($userAnrs as $userAnr) {
            $this->addUserAnr($userAnr);
        }

        return $this;
    }

    public function addUserAnr(UserAnr $userAnr): self
    {
        $this->userAnrs->add($userAnr);
        $userAnr->setUser($this);

        return $this;
    }

    protected function createRole(string $role): UserRoleSuperClass
    {
        return new UserRole($this, $role);
    }
}
