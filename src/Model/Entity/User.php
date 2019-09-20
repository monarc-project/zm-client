<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
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
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="UserAnr", orphanRemoval=true, mappedBy="user")
     */
    protected $anrs;

    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->anrs = new ArrayCollection();
        if (!empty($data['anrs'])) {
            $this->setAnrs($data['anrs']);
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

    public function getAnrs(): array
    {
        return $this->anrs->toArray();
    }

    public function setAnrs(array $anrs): self
    {
        $this->anrs = new ArrayCollection();
        foreach ($anrs as $anr) {
            // TODO: after refactoring we can use: $this->anrs->add(new UserAnr($anr));
            $userAnr = (new UserAnr())
                ->setAnr($anr['anr'])
                ->setUser($this)
                ->setRwd($anr['rwd']);

            $this->anrs->add($userAnr);
        }

        return $this;
    }

    protected function createRole(string $role): UserRoleSuperClass
    {
        return new UserRole($this, $role);
    }
}
