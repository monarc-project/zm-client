<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use Monarc\Core\Entity\InstanceRiskOpSuperClass;

/**
 * @ORM\Table(name="instances_risks_op", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id"}),
 *      @ORM\Index(name="instance_id", columns={"instance_id"}),
 *      @ORM\Index(name="object_id", columns={"object_id"}),
 *      @ORM\Index(name="risk_source_id", columns={"risk_source_id"}),
 *      @ORM\Index(name="rolf_risk_id", columns={"rolf_risk_id"}),
 *      @ORM\Index(name="op_risk_owner_supervisor_id", columns={"risk_owner_supervisor_id"}),
 *      @ORM\Index(name="op_residual_acceptance_approver_supervisor_id", columns={"residual_acceptance_approver_supervisor_id"}),
 *      @ORM\Index(name="op_residual_risk_decided_by_supervisor_id", columns={"residual_risk_decided_by_supervisor_id"}),
 *      @ORM\Index(name="op_residual_risk_decided_by_user_id", columns={"residual_risk_decided_by_user_id"})
 * })
 * @ORM\Entity
 */
class InstanceRiskOp extends InstanceRiskOpSuperClass
{
    /**
     * @var Object
     *
     * @ORM\ManyToOne(targetEntity="MonarcObject")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="object_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $object;

    /**
     * @var RolfRisk
     *
     * @ORM\ManyToOne(targetEntity="RolfRisk")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $rolfRisk;

    /**
     * @var ArrayCollection|RecommendationRisk[]
     *
     * @ORM\OneToMany(targetEntity="RecommendationRisk", mappedBy="instanceRiskOp", cascade={"remove"})
     */
    protected $recommendationRisks;

    /**
     * @var InstanceRiskOwner|null
     *
     * @ORM\ManyToOne(targetEntity="InstanceRiskOwner")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="risk_owner_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $instanceRiskOwner;

    /**
     * @var AnrSupervisor|null
     *
     * @ORM\ManyToOne(targetEntity="AnrSupervisor", inversedBy="operationalInstanceRisks")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="risk_owner_supervisor_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     * })
     */
    protected $riskOwnerSupervisor;

    /**
     * @var string
     *
     * @ORM\Column(name="context", type="string", length=255, nullable=true)
     */
    protected $context;

    /**
     * @var RiskSource|null
     *
     * @ORM\ManyToOne(targetEntity="RiskSource")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="risk_source_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     * })
     */
    protected $riskSource;
    
    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_review_date", type="date", nullable=true)
     */
    protected $lastReviewDate;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="next_reassessment_date", type="date", nullable=true)
     */
    protected $nextReassessmentDate;

    /**
     * @var ArrayCollection|ReassessmentTrigger[]
     *
     * @ORM\ManyToMany(targetEntity="ReassessmentTrigger")
     * @ORM\JoinTable(name="instances_risks_op_reassessment_triggers",
     *     joinColumns={@ORM\JoinColumn(name="instance_risk_op_id", referencedColumnName="id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="reassessment_trigger_id", referencedColumnName="id", onDelete="CASCADE")}
     * )
     */
    protected $reassessmentTriggers;

    /**
     * @var string|null
     *
     * @ORM\Column(name="review_frequency", type="string", length=50, nullable=true)
     */
    protected $reviewFrequency;


    /**
     * @var string|null
     *
     * @ORM\Column(name="residual_risk_decision", type="string", length=20, nullable=true)
     */
    protected $residualRiskDecision;

    /**
     * @var bool
     *
     * @ORM\Column(name="residual_acceptance_use_risk_owner", type="boolean", options={"default": false})
     */
    protected $residualAcceptanceUseRiskOwner = false;

    /**
     * @var AnrSupervisor|null
     *
     * @ORM\ManyToOne(targetEntity="AnrSupervisor")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="residual_acceptance_approver_supervisor_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     * })
     */
    protected $residualAcceptanceApproverSupervisor;

    /**
     * @var string|null
     *
     * @ORM\Column(name="residual_acceptance_performed_by_name", type="string", length=255, nullable=true)
     */
    protected $residualAcceptancePerformedByName;

    /**
     * @var string|null
     *
     * @ORM\Column(name="residual_acceptance_performed_by_email", type="string", length=255, nullable=true)
     */
    protected $residualAcceptancePerformedByEmail;

    /**
     * @var bool
     *
     * @ORM\Column(name="residual_acceptance_performed_on_behalf", type="boolean", options={"default": false})
     */
    protected $residualAcceptancePerformedOnBehalf = false;

    /**
     * @var AnrSupervisor|null
     *
     * @ORM\ManyToOne(targetEntity="AnrSupervisor")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="residual_risk_decided_by_supervisor_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     * })
     */
    protected $residualRiskDecidedBySupervisor;

    /**
     * @var User|null
     *
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="residual_risk_decided_by_user_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     * })
     */
    protected $residualRiskDecidedByUser;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="residual_risk_decided_at", type="datetime", nullable=true)
     */
    protected $residualRiskDecidedAt;

    /**
     * @var string|null
     *
     * @ORM\Column(name="residual_risk_justification", type="text", nullable=true)
     */
    protected $residualRiskJustification;

    public function __construct()
    {
        parent::__construct();

        $this->recommendationRisks = new ArrayCollection();
        $this->reassessmentTriggers = new ArrayCollection();
    }

    public static function constructFromObject(
        InstanceRiskOpSuperClass $sourceOperationalInstanceRisk
    ): InstanceRiskOpSuperClass {
        /** @var InstanceRiskOp $operationalInstanceRisk */
        $operationalInstanceRisk = parent::constructFromObject($sourceOperationalInstanceRisk);

        if ($sourceOperationalInstanceRisk instanceof self) {
            $operationalInstanceRisk
                ->setContext($sourceOperationalInstanceRisk->getContext())
                ->setRiskSource($sourceOperationalInstanceRisk->getRiskSource())
                ->setRiskOwnerSupervisor($sourceOperationalInstanceRisk->getRiskOwnerSupervisor())
                ->setLastReviewDate($sourceOperationalInstanceRisk->getLastReviewDate())
                ->setNextReassessmentDate($sourceOperationalInstanceRisk->getNextReassessmentDate())
                ->setReassessmentTriggers($sourceOperationalInstanceRisk->getReassessmentTriggers()->toArray())
                ->setReviewFrequency($sourceOperationalInstanceRisk->getReviewFrequency())
                ->setResidualRiskDecision($sourceOperationalInstanceRisk->getResidualRiskDecision())
                ->setResidualAcceptanceUseRiskOwner($sourceOperationalInstanceRisk->isResidualAcceptanceUseRiskOwner())
                ->setResidualAcceptanceApproverSupervisor(
                    $sourceOperationalInstanceRisk->getResidualAcceptanceApproverSupervisor()
                )
                ->setResidualAcceptancePerformedByName(
                    $sourceOperationalInstanceRisk->getResidualAcceptancePerformedByName()
                )
                ->setResidualAcceptancePerformedByEmail(
                    $sourceOperationalInstanceRisk->getResidualAcceptancePerformedByEmail()
                )
                ->setResidualAcceptancePerformedOnBehalf(
                    $sourceOperationalInstanceRisk->isResidualAcceptancePerformedOnBehalf()
                )
                ->setResidualRiskDecidedBySupervisor($sourceOperationalInstanceRisk->getResidualRiskDecidedBySupervisor())
                ->setResidualRiskDecidedByUser($sourceOperationalInstanceRisk->getResidualRiskDecidedByUser())
                ->setResidualRiskDecidedAt($sourceOperationalInstanceRisk->getResidualRiskDecidedAt())
                ->setResidualRiskJustification($sourceOperationalInstanceRisk->getResidualRiskJustification());
        }

        return $operationalInstanceRisk;
    }

    public function getRecommendationRisks()
    {
        return $this->recommendationRisks;
    }

    public function addRecommendationRisk(RecommendationRisk $recommendationRisk): self
    {
        if (!$this->recommendationRisks->contains($recommendationRisk)) {
            $this->recommendationRisks->add($recommendationRisk);
        }

        return $this;
    }

    public function removeRecommendationRisk(RecommendationRisk $recommendationRisk): self
    {
        if ($this->recommendationRisks->contains($recommendationRisk)) {
            $this->recommendationRisks->removeElement($recommendationRisk);
        }

        return $this;
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

    public function getRiskOwnerSupervisor(): ?AnrSupervisor
    {
        return $this->riskOwnerSupervisor;
    }

    public function setRiskOwnerSupervisor(?AnrSupervisor $riskOwnerSupervisor): self
    {
        $this->riskOwnerSupervisor = $riskOwnerSupervisor;

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

    public function getRiskSource(): ?RiskSource
    {
        return $this->riskSource;
    }

    public function setRiskSource(?RiskSource $riskSource): self
    {
        $this->riskSource = $riskSource;

        return $this;
    }

    
    public function getLastReviewDate(): ?DateTime
    {
        return $this->lastReviewDate;
    }

    public function setLastReviewDate(?DateTime $lastReviewDate): self
    {
        $this->lastReviewDate = $lastReviewDate;

        return $this;
    }

    public function getNextReassessmentDate(): ?DateTime
    {
        return $this->nextReassessmentDate;
    }

    public function setNextReassessmentDate(?DateTime $nextReassessmentDate): self
    {
        $this->nextReassessmentDate = $nextReassessmentDate;

        return $this;
    }

    /** @return ArrayCollection|ReassessmentTrigger[] */
    public function getReassessmentTriggers()
    {
        return $this->reassessmentTriggers;
    }

    /** @param ReassessmentTrigger[] $reassessmentTriggers */
    public function setReassessmentTriggers(array $reassessmentTriggers): self
    {
        $this->reassessmentTriggers = new ArrayCollection($reassessmentTriggers);

        return $this;
    }

    public function getReviewFrequency(): ?string
    {
        return $this->reviewFrequency;
    }

    public function setReviewFrequency(?string $reviewFrequency): self
    {
        $this->reviewFrequency = $reviewFrequency;

        return $this;
    }

    public function getResidualRiskDecision(): ?string
    {
        return $this->residualRiskDecision;
    }

    public function setResidualRiskDecision(?string $residualRiskDecision): self
    {
        $this->residualRiskDecision = $residualRiskDecision;

        return $this;
    }

    public function isResidualAcceptanceUseRiskOwner(): bool
    {
        return $this->residualAcceptanceUseRiskOwner;
    }

    public function setResidualAcceptanceUseRiskOwner(bool $residualAcceptanceUseRiskOwner): self
    {
        $this->residualAcceptanceUseRiskOwner = $residualAcceptanceUseRiskOwner;

        return $this;
    }

    public function getResidualAcceptanceApproverSupervisor(): ?AnrSupervisor
    {
        return $this->residualAcceptanceApproverSupervisor;
    }

    public function setResidualAcceptanceApproverSupervisor(
        ?AnrSupervisor $residualAcceptanceApproverSupervisor
    ): self {
        $this->residualAcceptanceApproverSupervisor = $residualAcceptanceApproverSupervisor;

        return $this;
    }

    public function getResidualAcceptancePerformedByName(): ?string
    {
        return $this->residualAcceptancePerformedByName;
    }

    public function setResidualAcceptancePerformedByName(?string $residualAcceptancePerformedByName): self
    {
        $this->residualAcceptancePerformedByName = $residualAcceptancePerformedByName;

        return $this;
    }

    public function getResidualAcceptancePerformedByEmail(): ?string
    {
        return $this->residualAcceptancePerformedByEmail;
    }

    public function setResidualAcceptancePerformedByEmail(?string $residualAcceptancePerformedByEmail): self
    {
        $this->residualAcceptancePerformedByEmail = $residualAcceptancePerformedByEmail;

        return $this;
    }

    public function isResidualAcceptancePerformedOnBehalf(): bool
    {
        return $this->residualAcceptancePerformedOnBehalf;
    }

    public function setResidualAcceptancePerformedOnBehalf(bool $residualAcceptancePerformedOnBehalf): self
    {
        $this->residualAcceptancePerformedOnBehalf = $residualAcceptancePerformedOnBehalf;

        return $this;
    }

    public function getResidualRiskDecidedBySupervisor(): ?AnrSupervisor
    {
        return $this->residualRiskDecidedBySupervisor;
    }

    public function setResidualRiskDecidedBySupervisor(?AnrSupervisor $residualRiskDecidedBySupervisor): self
    {
        $this->residualRiskDecidedBySupervisor = $residualRiskDecidedBySupervisor;

        return $this;
    }

    public function getResidualRiskDecidedByUser(): ?User
    {
        return $this->residualRiskDecidedByUser;
    }

    public function setResidualRiskDecidedByUser(?User $residualRiskDecidedByUser): self
    {
        $this->residualRiskDecidedByUser = $residualRiskDecidedByUser;

        return $this;
    }

    public function getResidualRiskDecidedAt(): ?DateTime
    {
        return $this->residualRiskDecidedAt;
    }

    public function setResidualRiskDecidedAt(?DateTime $residualRiskDecidedAt): self
    {
        $this->residualRiskDecidedAt = $residualRiskDecidedAt;

        return $this;
    }

    public function getResidualRiskJustification(): ?string
    {
        return $this->residualRiskJustification;
    }

    public function setResidualRiskJustification(?string $residualRiskJustification): self
    {
        $this->residualRiskJustification = $residualRiskJustification;

        return $this;
    }
}
