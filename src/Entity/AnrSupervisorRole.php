<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="anr_supervisor_roles",
 *   uniqueConstraints={@ORM\UniqueConstraint(name="idx_anr_supervisor_roles_unique", columns={"anr_supervisor_id", "role"})},
 *   indexes={
 *      @ORM\Index(name="idx_supervisor_role_supervisor_id", columns={"anr_supervisor_id"}),
 *      @ORM\Index(name="idx_supervisor_role_role", columns={"role"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class AnrSupervisorRole
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    public const ROLE_RISK_OWNER = 'risk_owner';
    public const ROLE_RESIDUAL_RISK_APPROVER = 'residual_risk_approver';

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var AnrSupervisor
     *
     * @ORM\ManyToOne(targetEntity="AnrSupervisor", inversedBy="roles")
     * @ORM\JoinColumn(name="anr_supervisor_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $anrSupervisor;

    /**
     * @var string
     *
     * @ORM\Column(name="role", type="string", length=100, nullable=false)
     */
    protected $role;

    public function getId(): int
    {
        return $this->id;
    }

    public function getAnrSupervisor(): AnrSupervisor
    {
        return $this->anrSupervisor;
    }

    public function setAnrSupervisor(AnrSupervisor $anrSupervisor): self
    {
        $this->anrSupervisor = $anrSupervisor;
        $anrSupervisor->addRole($this);

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public static function getAvailableRoles(): array
    {
        return [
            self::ROLE_RISK_OWNER,
            self::ROLE_RESIDUAL_RISK_APPROVER,
        ];
    }
}
