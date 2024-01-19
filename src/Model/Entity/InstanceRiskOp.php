<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;

/**
 * @ORM\Table(name="instances_risks_op", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id"}),
 *      @ORM\Index(name="instance_id", columns={"instance_id"}),
 *      @ORM\Index(name="object_id", columns={"object_id"}),
 *      @ORM\Index(name="rolf_risk_id", columns={"rolf_risk_id"})
 * })
 * @ORM\Entity
 */
class InstanceRiskOp extends InstanceRiskOpSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $anr;

    /**
     * @var Object
     *
     * @ORM\ManyToOne(targetEntity="MonarcObject", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="object_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $object;

    /**
     * @var RolfRisk
     *
     * @ORM\ManyToOne(targetEntity="RolfRisk", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $rolfRisk;

    /**
     * @var ArrayCollection|RecommendationRisk[]
     *
     * @ORM\OneToMany(targetEntity="RecommendationRisk", mappedBy="instanceRiskOp", cascade={"persist", "remove"})
     */
    protected $recommendationRisks;

    /**
     * @var InstanceRiskOwner|null
     *
     * @ORM\ManyToOne(targetEntity="InstanceRiskOwner", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="risk_owner_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRiskOwner;

    /**
     * @var string
     *
     * @ORM\Column(name="context", type="string", length=255, nullable=true)
     */
    protected $context;

    public function __construct()
    {
        parent::__construct();

        $this->recommendationRisks = new ArrayCollection();
    }

    /**
     * @param InstanceRiskOp $operationalInstanceRisk
     */
    public static function constructFromObject(
        InstanceRiskOpSuperClass $operationalInstanceRisk
    ): InstanceRiskOpSuperClass {
        return static::constructFromObject($operationalInstanceRisk)
            ->setContext($operationalInstanceRisk->getContext());
    }

    public function getRecommendationRisks()
    {
        return $this->recommendationRisks;
    }

    public function getInstanceRiskOwner(): ?InstanceRiskOwner
    {
        return $this->instanceRiskOwner;
    }

    public function setInstanceRiskOwner(?InstanceRiskOwner $instanceRiskOwner): self
    {
        if ($instanceRiskOwner === null) {
            if ($this->instanceRiskOwner !== null) {
                $this->instanceRiskOwner->removeOperationalInstanceRisk($this);
                $this->instanceRiskOwner = null;
            }
        } else {
            $this->instanceRiskOwner = $instanceRiskOwner;
            $instanceRiskOwner->addOperationalInstanceRisk($this);
        }

        return $this;
    }

    public function getContext(): string
    {
        return (string)$this->context;
    }

    public function setContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }
}
