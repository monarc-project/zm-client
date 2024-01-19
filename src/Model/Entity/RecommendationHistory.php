<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="recommandations_historics")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class RecommendationHistory
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
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=False)
     * })
     */
    protected $anr;

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
     * @var int
     *
     * @ORM\Column(name="final", type="smallint", options={"unsigned":false, "default":1})
     */
    protected $isFinal = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="impl_comment", type="string", length=255, nullable=true)
     */
    protected $implComment;

    /**
     * @var string
     *
     * @ORM\Column(name="reco_code", type="string", length=100, nullable=true)
     */
    protected $recoCode;

    /**
     * @var string
     *
     * @ORM\Column(name="reco_description", type="string", length=255, nullable=true)
     */
    protected $recoDescription;

    /**
     * @var int
     *
     * @ORM\Column(name="reco_importance", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $recoImportance = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="reco_comment", type="string", length=255, nullable=true)
     */
    protected $recoComment;

    /**
     * @var string
     *
     * @ORM\Column(name="reco_responsable", type="string", length=255, nullable=true)
     */
    protected $recoResponsable;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="reco_duedate", type="datetime", nullable=true)
     */
    protected $recoDueDate;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_instance", type="string", length=255, nullable=true)
     */
    protected $riskInstance;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_instance_context", type="string", length=255, nullable=true)
     */
    protected $riskInstanceContext;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_asset", type="string", length=255, nullable=true)
     */
    protected $riskAsset;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_threat", type="string", length=255, nullable=true)
     */
    protected $riskThreat;

    /**
     * @var int
     *
     * @ORM\Column(name="risk_threat_val", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskThreatVal = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_vul", type="string", length=255, nullable=true)
     */
    protected $riskVul;

    /**
     * @var int
     *
     * @ORM\Column(name="risk_vul_val_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskVulValBefore = -1;

    /**
     * @var int
     *
     * @ORM\Column(name="risk_vul_val_after", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskVulValAfter = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_op_description", type="string", length=255, nullable=true)
     */
    protected $riskOpDescription;

    /**
     * @var int
     *
     * @ORM\Column(name="net_prob_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $netProbBefore = -1;

    /**
     * @var int
     *
     * @ORM\Column(name="risk_kind_of_measure", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskKindOfMeasure = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_comment_before", type="string", length=255, nullable=true)
     */
    protected $riskCommentBefore;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_comment_after", type="string", length=255, nullable=true)
     */
    protected $riskCommentAfter;

    /**
     * @var int
     *
     * @ORM\Column(name="risk_max_risk_before", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskMaxRiskBefore = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_color_before", type="string", length=100, nullable=true)
     */
    protected $riskColorBefore;

    /**
     * @var int
     *
     * @ORM\Column(name="risk_max_risk_after", type="smallint", options={"unsigned":false, "default":-1})
     */
    protected $riskMaxRiskAfter = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="risk_color_after", type="string", length=100, nullable=true)
     */
    protected $riskColorAfter;

    /**
     * @var string
     *
     * @ORM\Column(name="cache_comment_after", type="string", length=255, nullable=true)
     */
    protected $cacheCommentAfter;

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

    public function getInstanceRisk(): InstanceRisk
    {
        return $this->instanceRisk;
    }

    public function setInstanceRisk(InstanceRisk $instanceRisk): self
    {
        $this->instanceRisk = $instanceRisk;

        return $this;
    }

    public function getInstanceRiskOp(): InstanceRiskOp
    {
        return $this->instanceRiskOp;
    }

    public function setInstanceRiskOp(InstanceRiskOp $instanceRiskOp): self
    {
        $this->instanceRiskOp = $instanceRiskOp;

        return $this;
    }

    public function isFinal(): bool
    {
        return (bool)$this->isFinal;
    }

    public function setIsFinal(bool $isFinal): self
    {
        $this->isFinal = (int)$isFinal;

        return $this;
    }

    public function getImplComment(): string
    {
        return (string)$this->implComment;
    }

    public function setImplComment(string $implComment): self
    {
        $this->implComment = $implComment;

        return $this;
    }

    public function getRecoCode(): string
    {
        return (string)$this->recoCode;
    }

    public function setRecoCode(string $recoCode): self
    {
        $this->recoCode = $recoCode;

        return $this;
    }

    public function getRecoDescription(): string
    {
        return (string)$this->recoDescription;
    }

    public function setRecoDescription(string $recoDescription): self
    {
        $this->recoDescription = $recoDescription;

        return $this;
    }

    public function getRecoImportance(): int
    {
        return $this->recoImportance;
    }

    public function setRecoImportance(int $recoImportance): self
    {
        $this->recoImportance = $recoImportance;

        return $this;
    }

    public function getRecoComment(): string
    {
        return (string)$this->recoComment;
    }

    public function setRecoComment(string $recoComment): self
    {
        $this->recoComment = $recoComment;

        return $this;
    }

    public function getRecoResponsable(): string
    {
        return (string)$this->recoResponsable;
    }

    public function setRecoResponsable(string $recoResponsable): self
    {
        $this->recoResponsable = $recoResponsable;

        return $this;
    }

    public function getRecoDueDate(): ?DateTime
    {
        return $this->recoDueDate;
    }

    public function setRecoDueDate(?DateTime $recoDueDate): self
    {
        $this->recoDueDate = $recoDueDate;

        return $this;
    }

    public function getRiskInstance(): string
    {
        return (string)$this->riskInstance;
    }

    public function setRiskInstance(string $riskInstance): self
    {
        $this->riskInstance = $riskInstance;

        return $this;
    }

    public function getRiskInstanceContext(): string
    {
        return (string)$this->riskInstanceContext;
    }

    public function setRiskInstanceContext(string $riskInstanceContext): self
    {
        $this->riskInstanceContext = $riskInstanceContext;

        return $this;
    }

    public function getRiskAsset(): string
    {
        return (string)$this->riskAsset;
    }

    public function setRiskAsset(string $riskAsset): self
    {
        $this->riskAsset = $riskAsset;

        return $this;
    }

    public function getRiskThreat(): string
    {
        return (string)$this->riskThreat;
    }

    public function setRiskThreat(string $riskThreat): self
    {
        $this->riskThreat = $riskThreat;

        return $this;
    }

    public function getRiskThreatVal(): int
    {
        return $this->riskThreatVal;
    }

    public function setRiskThreatVal(int $riskThreatVal): self
    {
        $this->riskThreatVal = $riskThreatVal;

        return $this;
    }

    public function getRiskVul(): string
    {
        return (string)$this->riskVul;
    }

    public function setRiskVul(string $riskVul): self
    {
        $this->riskVul = $riskVul;

        return $this;
    }

    public function getRiskVulValBefore(): int
    {
        return $this->riskVulValBefore;
    }

    public function setRiskVulValBefore(int $riskVulValBefore): self
    {
        $this->riskVulValBefore = $riskVulValBefore;

        return $this;
    }

    public function getRiskVulValAfter(): int
    {
        return $this->riskVulValAfter;
    }

    public function setRiskVulValAfter(int $riskVulValAfter): self
    {
        $this->riskVulValAfter = $riskVulValAfter;

        return $this;
    }

    public function getRiskOpDescription(): string
    {
        return (string)$this->riskOpDescription;
    }

    public function setRiskOpDescription(string $riskOpDescription): self
    {
        $this->riskOpDescription = $riskOpDescription;

        return $this;
    }

    public function getNetProbBefore(): int
    {
        return $this->netProbBefore;
    }

    public function setNetProbBefore(int $netProbBefore): self
    {
        $this->netProbBefore = $netProbBefore;

        return $this;
    }

    public function getRiskKindOfMeasure(): int
    {
        return $this->riskKindOfMeasure;
    }

    public function setRiskKindOfMeasure(int $riskKindOfMeasure): self
    {
        $this->riskKindOfMeasure = $riskKindOfMeasure;

        return $this;
    }

    public function getRiskCommentBefore(): string
    {
        return (string)$this->riskCommentBefore;
    }

    public function setRiskCommentBefore(string $riskCommentBefore): self
    {
        $this->riskCommentBefore = $riskCommentBefore;

        return $this;
    }

    public function getRiskCommentAfter(): string
    {
        return (string)$this->riskCommentAfter;
    }

    public function setRiskCommentAfter(string $riskCommentAfter): self
    {
        $this->riskCommentAfter = $riskCommentAfter;

        return $this;
    }

    public function getRiskMaxRiskBefore(): int
    {
        return $this->riskMaxRiskBefore;
    }

    public function setRiskMaxRiskBefore(int $riskMaxRiskBefore): self
    {
        $this->riskMaxRiskBefore = $riskMaxRiskBefore;

        return $this;
    }

    public function getRiskColorBefore(): string
    {
        return (string)$this->riskColorBefore;
    }

    public function setRiskColorBefore(string $riskColorBefore): self
    {
        $this->riskColorBefore = $riskColorBefore;

        return $this;
    }

    public function getRiskMaxRiskAfter(): int
    {
        return $this->riskMaxRiskAfter;
    }

    public function setRiskMaxRiskAfter(int $riskMaxRiskAfter): self
    {
        $this->riskMaxRiskAfter = $riskMaxRiskAfter;

        return $this;
    }

    public function getRiskColorAfter(): string
    {
        return (string)$this->riskColorAfter;
    }

    public function setRiskColorAfter(string $riskColorAfter): self
    {
        $this->riskColorAfter = $riskColorAfter;

        return $this;
    }

    public function getCacheCommentAfter(): string
    {
        return (string)$this->cacheCommentAfter;
    }

    public function setCacheCommentAfter(string $cacheCommentAfter): self
    {
        $this->cacheCommentAfter = $cacheCommentAfter;

        return $this;
    }
}
