<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="instance_risk_owners",
 *   uniqueConstraints={@ORM\UniqueConstraint(name="instance_risk_owners_anr_id_name", columns={"anr_id", "name"})},
 *   indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class InstanceRiskOwner
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
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * @var InstanceRisk[]|ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="InstanceRisk", mappedBy="instanceRiskOwner")
     */
    protected $instanceRisks;

    /**
     * @var InstanceRiskOp[]|ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="InstanceRiskOp", mappedBy="instanceRiskOwner")
     */
    protected $operationalInstanceRisks;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;

    public function __construct()
    {
        $this->instanceRisks = new ArrayCollection();
        $this->operationalInstanceRisks = new ArrayCollection();
    }

    public function getId()
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

    public function getInstanceRisks()
    {
        return $this->instanceRisks;
    }

    public function addInstanceRisk(InstanceRisk $instanceRisk): self
    {
        if (!$this->instanceRisks->contains($instanceRisk)) {
            $this->instanceRisks->add($instanceRisk);
            $instanceRisk->setInstanceRiskOwner($this);
        }

        return $this;
    }

    public function removeInstanceRisk(InstanceRisk $instanceRisk): self
    {
        if ($this->instanceRisks->contains($instanceRisk)) {
            $this->instanceRisks->removeElement($instanceRisk);
        }

        return $this;
    }

    public function getOperationalInstanceRisks()
    {
        return $this->operationalInstanceRisks;
    }

    public function addOperationalInstanceRisk(InstanceRiskOp $operationalInstanceRisk): self
    {
        if (!$this->operationalInstanceRisks->contains($operationalInstanceRisk)) {
            $this->operationalInstanceRisks->add($operationalInstanceRisk);
            $operationalInstanceRisk->setInstanceRiskOwner($this);
        }

        return $this;
    }


    public function removeOperationalInstanceRisk(InstanceRiskOp $operationalInstanceRisk): self
    {
        if ($this->operationalInstanceRisks->contains($operationalInstanceRisk)) {
            $this->operationalInstanceRisks->removeElement($operationalInstanceRisk);
            $operationalInstanceRisk->setInstanceRiskOwner(null);
        }

        return $this;
    }
}
