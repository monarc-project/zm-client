<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Interfaces\PositionedEntityInterface;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(
 *     name="anr_interested_parties",
 *     indexes={
 *         @ORM\Index(name="anr_interested_parties_anr_id_indx", columns={"anr_id"}),
 *         @ORM\Index(name="anr_interested_parties_anr_id_position_indx", columns={"anr_id", "position"})
 *     }
 * )
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class InterestedParty implements PositionedEntityInterface
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @ORM\Column(name="id", type="integer", nullable=false, options={"unsigned": true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected int $id;

    /**
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected Anr $anr;

    /** @ORM\Column(name="stakeholder", type="string", length=255, nullable=false) */
    protected string $stakeholder = '';

    /** @ORM\Column(name="requirement", type="text", nullable=false) */
    protected string $requirement = '';

    /** @ORM\Column(name="position", type="integer", nullable=false, options={"unsigned": true, "default": 0}) */
    protected int $position = 0;

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

    public function getStakeholder(): string
    {
        return $this->stakeholder;
    }

    public function setStakeholder(string $stakeholder): self
    {
        $this->stakeholder = $stakeholder;

        return $this;
    }

    public function getRequirement(): string
    {
        return $this->requirement;
    }

    public function setRequirement(string $requirement): self
    {
        $this->requirement = $requirement;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getImplicitPositionRelationsValues(): array
    {
        return ['anr' => $this->anr];
    }
}
