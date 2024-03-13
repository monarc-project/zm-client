<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="recommandations_risks")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class RecommendationRisk
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
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $anr;

    /**
     * @var Recommendation
     *
     * @ORM\ManyToOne(targetEntity="Recommendation", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="recommandation_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $recommendation;

    /**
     * @var InstanceRisk
     *
     * @ORM\ManyToOne(targetEntity="InstanceRisk", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRisk;

    /**
     * @var InstanceRiskOp
     *
     * @ORM\ManyToOne(targetEntity="InstanceRiskOp", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_risk_op_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRiskOp;

    /**
     * @var Instance
     *
     * @ORM\ManyToOne(targetEntity="Instance", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instance;

    /**
     * @var MonarcObject
     *
     * @ORM\ManyToOne(targetEntity="MonarcObject", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="object_global_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $globalObject;

    /**
     * @var Asset
     *
     * @ORM\ManyToOne(targetEntity="Asset", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $asset;

    /**
     * @var Threat
     *
     * @ORM\ManyToOne(targetEntity="Threat", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="threat_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $threat;

    /**
     * @var Vulnerability
     *
     * @ORM\ManyToOne(targetEntity="Vulnerability", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="vulnerability_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $vulnerability;

    /**
     * @var string
     *
     * @ORM\Column(name="comment_after", type="string", length=255, nullable=false)
     */
    protected $commentAfter = '';

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

    public function getRecommendation(): Recommendation
    {
        return $this->recommendation;
    }

    public function setRecommendation(Recommendation $recommendation): self
    {
        $this->recommendation = $recommendation;
        $recommendation->addRecommendationRisk($this);

        return $this;
    }

    public function getInstanceRisk(): ?InstanceRisk
    {
        return $this->instanceRisk;
    }

    public function setInstanceRisk(?InstanceRisk $instanceRisk): self
    {
        $this->instanceRisk = $instanceRisk;

        return $this;
    }

    public function getInstanceRiskOp(): ?InstanceRiskOp
    {
        return $this->instanceRiskOp;
    }

    public function setInstanceRiskOp(?InstanceRiskOp $instanceRiskOp)
    {
        $this->instanceRiskOp = $instanceRiskOp;

        return $this;
    }

    public function getInstance(): Instance
    {
        return $this->instance;
    }

    public function setInstance(Instance $instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    public function hasGlobalObjectRelation(): bool
    {
        return $this->globalObject !== null;
    }

    public function getGlobalObject(): ?MonarcObject
    {
        return $this->globalObject;
    }

    public function setGlobalObject(?MonarcObject $globalObject): self
    {
        $this->globalObject = $globalObject;

        return $this;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): self
    {
        $this->asset = $asset;

        return $this;
    }

    public function getThreat(): ?Threat
    {
        return $this->threat;
    }

    public function setThreat(?Threat $threat): self
    {
        $this->threat = $threat;

        return $this;
    }

    public function getVulnerability(): ?Vulnerability
    {
        return $this->vulnerability;
    }

    public function setVulnerability(?Vulnerability $vulnerability): self
    {
        $this->vulnerability = $vulnerability;

        return $this;
    }

    public function getCommentAfter(): string
    {
        return (string)$this->commentAfter;
    }

    public function setCommentAfter(string $commentAfter): self
    {
        $this->commentAfter = $commentAfter;

        return $this;
    }
}
