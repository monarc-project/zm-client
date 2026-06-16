<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="anr_history", indexes={
 *      @ORM\Index(name="idx_anr_history_anr_id", columns={"anr_id"}),
 *      @ORM\Index(name="idx_anr_history_target", columns={"target_type", "target_id"}),
 *      @ORM\Index(name="idx_anr_history_change_type", columns={"change_type"}),
 *      @ORM\Index(name="idx_anr_history_created_at", columns={"created_at"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class AnrHistory
{
    public const INFORMATION_RISK = 1;
    public const OPERATIONAL_RISK = 2;

    public const RISK_OWNER = 'risk_owner';
    public const RISK_SOURCE = 'risk_source';
    public const RISK_CONTEXT = 'risk_context';
    public const LAST_REVIEW_DATE = 'last_review_date';
    public const REVIEW_FREQUENCY = 'review_frequency';

    public const THREAT_PROBABILITY = 'threat_probability';
    public const VULNERABILITY_QUALIFICATION = 'vulnerability_qualification';
    public const CURRENT_RISK = 'current_risk';
    public const RESIDUAL_RISK = 'residual_risk';
    public const TREATMENT_TYPE = 'treatment_type';
    public const VULNERABILITY_REDUCTION = 'vulnerability_reduction';

    public const RESIDUAL_ACCEPTANCE_APPROVER = 'residual_acceptance_approver';
    public const RESIDUAL_ACCEPTANCE_DECISION = 'residual_acceptance_decision';
    public const RESIDUAL_ACCEPTANCE_JUSTIFICATION = 'residual_acceptance_justification';
    public const RESIDUAL_ACCEPTANCE_DATE = 'residual_acceptance_date';

    public const CONSEQUENCE_CONFIDENTIALITY = 'consequence_confidentiality';
    public const CONSEQUENCE_INTEGRITY = 'consequence_integrity';
    public const CONSEQUENCE_AVAILABILITY = 'consequence_availability';
    public const CONSEQUENCE_REPUTATION = 'consequence_reputation';
    public const CONSEQUENCE_LEGAL = 'consequence_legal';
    public const CONSEQUENCE_FINANCIAL = 'consequence_financial';

    public const CREATED = 1;
    public const FIELD_UPDATED = 10;

    public const RECOMMENDATION_LINKED = 20;
    public const RECOMMENDATION_UNLINKED = 21;

    public const CONSEQUENCE_CREATED = 30;
    public const CONSEQUENCE_UPDATED = 31;
    public const CONSEQUENCE_DELETED = 32;

    public const RESIDUAL_ACCEPTANCE_UPDATED = 40;

    /**
     * @ORM\Column(name="id", type="bigint", nullable=false, options={"unsigned": true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /** @ORM\Column(name="anr_id", type="integer", nullable=false, options={"unsigned": true}) */
    protected int $anrId;

    /** @ORM\Column(name="target_type", type="smallint", nullable=false, options={"unsigned": true}) */
    protected int $targetType;

    /** @ORM\Column(name="target_id", type="integer", nullable=false, options={"unsigned": true}) */
    protected int $targetId;

    /** @ORM\Column(name="change_type", type="smallint", nullable=false, options={"unsigned": true}) */
    protected int $changeType;

    /** @ORM\Column(name="field_code", type="string", length=100, nullable=true) */
    protected ?string $fieldCode = null;

    /** @ORM\Column(name="old_value", type="text", nullable=true) */
    protected ?string $oldValue = null;

    /** @ORM\Column(name="new_value", type="text", nullable=true) */
    protected ?string $newValue = null;

    /** @ORM\Column(name="performed_by_firstname", type="string", length=255, nullable=true) */
    protected ?string $performedByFirstname = null;

    /** @ORM\Column(name="performed_by_lastname", type="string", length=255, nullable=true) */
    protected ?string $performedByLastname = null;

    /** @ORM\Column(name="performed_by_email", type="string", length=255, nullable=true) */
    protected ?string $performedByEmail = null;

    /** @ORM\Column(name="created_at", type="datetime", nullable=false) */
    protected ?DateTime $createdAt = null;

    /** @ORM\PrePersist */
    public function initializeCreatedAt(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new DateTime();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnrId(): int
    {
        return $this->anrId;
    }

    public function setAnrId(int $anrId): self
    {
        $this->anrId = $anrId;

        return $this;
    }

    public function getTargetType(): int
    {
        return $this->targetType;
    }

    public function setTargetType(int $targetType): self
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): int
    {
        return $this->targetId;
    }

    public function setTargetId(int $targetId): self
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getChangeType(): int
    {
        return $this->changeType;
    }

    public function setChangeType(int $changeType): self
    {
        $this->changeType = $changeType;

        return $this;
    }

    public function getFieldCode(): ?string
    {
        return $this->fieldCode;
    }

    public function setFieldCode(?string $fieldCode): self
    {
        $this->fieldCode = $fieldCode;

        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): self
    {
        $this->oldValue = $oldValue;

        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): self
    {
        $this->newValue = $newValue;

        return $this;
    }

    public function getPerformedByFirstname(): ?string
    {
        return $this->performedByFirstname;
    }

    public function setPerformedByFirstname(?string $performedByFirstname): self
    {
        $this->performedByFirstname = $performedByFirstname;

        return $this;
    }

    public function getPerformedByLastname(): ?string
    {
        return $this->performedByLastname;
    }

    public function setPerformedByLastname(?string $performedByLastname): self
    {
        $this->performedByLastname = $performedByLastname;

        return $this;
    }

    public function getPerformedByEmail(): ?string
    {
        return $this->performedByEmail;
    }

    public function setPerformedByEmail(?string $performedByEmail): self
    {
        $this->performedByEmail = $performedByEmail;

        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
