<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="anr_supervisors",
 *   indexes={
 *      @ORM\Index(name="idx_anr_supervisors_anr_id", columns={"anr_id"}),
 *      @ORM\Index(name="idx_anr_supervisors_linked_user_id", columns={"linked_user_id"}),
 *      @ORM\Index(name="idx_anr_supervisors_email", columns={"email"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class AnrSupervisor
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $anr;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    protected $name;

    /**
     * @var string|null
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=true)
     */
    protected $email;

    /**
     * @var User|null
     *
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="linked_user_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $linkedUser;

    /**
     * @var string|null
     *
     * @ORM\Column(name="role_position", type="string", length=255, nullable=true)
     */
    protected $rolePosition;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_active", type="boolean", options={"default":true})
     */
    protected $isActive = true;

    /**
     * @var ArrayCollection|AnrSupervisorRole[]
     *
     * @ORM\OneToMany(targetEntity="AnrSupervisorRole", mappedBy="anrSupervisor", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $roles;

    /**
     * @var ArrayCollection|InstanceRisk[]
     *
     * @ORM\OneToMany(targetEntity="InstanceRisk", mappedBy="riskOwnerSupervisor")
     */
    protected $instanceRisks;

    /**
     * @var ArrayCollection|InstanceRiskOp[]
     *
     * @ORM\OneToMany(targetEntity="InstanceRiskOp", mappedBy="riskOwnerSupervisor")
     */
    protected $operationalInstanceRisks;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->instanceRisks = new ArrayCollection();
        $this->operationalInstanceRisks = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getLinkedUser(): ?User
    {
        return $this->linkedUser;
    }

    public function setLinkedUser(?User $linkedUser): self
    {
        $this->linkedUser = $linkedUser;

        return $this;
    }

    public function getRolePosition(): ?string
    {
        return $this->rolePosition;
    }

    public function setRolePosition(?string $rolePosition): self
    {
        $this->rolePosition = $rolePosition;

        return $this;
    }

    public function isActive(): bool
    {
        return (bool)$this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return ArrayCollection|AnrSupervisorRole[]
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @return ArrayCollection|InstanceRisk[]
     */
    public function getInstanceRisks()
    {
        return $this->instanceRisks;
    }

    /**
     * @return ArrayCollection|InstanceRiskOp[]
     */
    public function getOperationalInstanceRisks()
    {
        return $this->operationalInstanceRisks;
    }

    public function getRolesArray(): array
    {
        return array_values(array_unique(array_map(
            static fn (AnrSupervisorRole $role): string => $role->getRole(),
            $this->roles->toArray()
        )));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRolesArray(), true);
    }

    public function addRole(AnrSupervisorRole $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
            $role->setAnrSupervisor($this);
        }

        return $this;
    }

    public function removeRole(AnrSupervisorRole $role): self
    {
        if ($this->roles->contains($role)) {
            $this->roles->removeElement($role);
        }

        return $this;
    }
}
