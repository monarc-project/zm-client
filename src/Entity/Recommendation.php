<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Interfaces\PositionedEntityInterface;
use Monarc\Core\Entity\Interfaces\PropertyStateEntityInterface;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\PropertyStateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Table(name="recommandations", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id","code"}),
 *      @ORM\Index(name="anr_id_2", columns={"anr_id"}),
 *      @ORM\Index(name="recommandation_set_uuid", columns={"recommandation_set_uuid", "code", "anr_id"}),
 *      @ORM\Index(name="recommendation_anr_position", columns={"anr_id", "position"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Recommendation implements PositionedEntityInterface, PropertyStateEntityInterface
{
    use PropertyStateEntityTrait;

    use CreateEntityTrait;
    use UpdateEntityTrait;

    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 0;

    public const EMPTY_POSITION = 0;

    public const EMPTY_IMPORTANCE = 0;
    public const LOW_IMPORTANCE = 1;
    public const MEDIUM_IMPORTANCE = 2;
    public const HIGH_IMPORTANCE = 3;

    /**
     * @var LazyUuidFromString|string
     *
     * @ORM\Column(name="uuid", type="uuid", nullable=false)
     * @ORM\Id
     */
    protected $uuid;

    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
     * })
     * @ORM\Id
     */
    protected $anr;

    /**
     * @var RecommendationSet
     *
     * @ORM\ManyToOne(targetEntity="RecommendationSet", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="recommandation_set_uuid", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $recommendationSet;

    /**
     * @var ArrayCollection|RecommendationRisk[]
     *
     * @ORM\OneToMany(targetEntity="RecommendationRisk", mappedBy="recommendation", cascade={"remove"})
     */
    protected $recommendationRisks;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=100, nullable=true)
     */
    protected $code;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    protected $description;

    /**
     * @var int
     *
     * @ORM\Column(name="importance", type="smallint", options={"unsigned":true, "default":0}, nullable=false)
     */
    protected $importance = self::EMPTY_IMPORTANCE;

    /**
     * @var int
     *
     * @ORM\Column(name="position", type="smallint", options={"unsigned":true, "default":0}, nullable=false)
     */
    protected $position = self::EMPTY_POSITION;

    /**
     * @var string
     *
     * @ORM\Column(name="comment", type="string", length=255, nullable=false)
     */
    protected $comment = '';

    /**
     * @var int
     *
     * @ORM\Column(name="status", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $status = self::STATUS_ACTIVE;

    /**
     * @var string
     *
     * @ORM\Column(name="responsable", type="string", length=255, nullable=true)
     */
    protected $responsible;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="duedate", type="datetime", nullable=true)
     */
    protected $dueDate;

    /**
     * @var int
     *
     * @ORM\Column(name="counter_treated", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $counterTreated = 0;

    public function __construct()
    {
        $this->recommendationRisks = new ArrayCollection();
    }

    public function getImplicitPositionRelationsValues(): array
    {
        return ['anr' => $this->anr];
    }

    public function getUuid(): string
    {
        return (string)$this->uuid;
    }

    public function setUuid($uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * @ORM\PrePersist
     */
    public function generateAndSetUuid(): self
    {
        if ($this->uuid === null) {
            $this->uuid = Uuid::uuid4();
        }

        return $this;
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

    public function getDueDate(): ?DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(?DateTime $date): self
    {
        $this->dueDate = $date;

        return $this;
    }

    public function setDueDateFromString(string $dateString): self
    {
        $this->dueDate = new DateTime($dateString);

        return $this;
    }

    public function getRecommendationSet(): RecommendationSet
    {
        return $this->recommendationSet;
    }

    public function setRecommendationSet(RecommendationSet $recommendationSet): self
    {
        $this->recommendationSet = $recommendationSet;
        $recommendationSet->addRecommendation($this);

        return $this;
    }

    public function getPosition(): int
    {
        return (int)$this->position;
    }

    public function getRecommendationRisks()
    {
        return $this->recommendationRisks;
    }

    public function addRecommendationRisk(RecommendationRisk $recommendationRisk): self
    {
        if (!$this->recommendationRisks->contains($recommendationRisk)) {
            $this->recommendationRisks->add($recommendationRisk);
            $recommendationRisk->setRecommendation($this);
        }

        return $this;
    }

    public function hasLinkedRecommendationRisks(): bool
    {
        return !$this->recommendationRisks->isEmpty();
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function shiftPositionUp(): self
    {
        $this->position--;

        return $this;
    }

    public function shiftPositionDown(): self
    {
        $this->position++;

        return $this;
    }

    public function getImportance(): int
    {
        return $this->importance;
    }

    public function isImportanceEmpty(): bool
    {
        return $this->importance === static::EMPTY_IMPORTANCE;
    }

    public static function getImportances(): array
    {
        return [
            static::EMPTY_IMPORTANCE => 'not set',
            static::LOW_IMPORTANCE => 'low',
            static::MEDIUM_IMPORTANCE => 'medium',
            static::HIGH_IMPORTANCE => 'high',
        ];
    }

    public function isPositionEmpty(): bool
    {
        return $this->position === static::EMPTY_POSITION;
    }

    public function setEmptyPosition(): self
    {
        $this->position = static::EMPTY_POSITION;

        return $this;
    }

    public function isPositionLowerThan(int $position): bool
    {
        return $this->position > $position;
    }

    public function isPositionHigherThan(int $position): bool
    {
        return $this->position < $position;
    }

    public function isPositionHigherOrEqualThan(int $position): bool
    {
        return $this->position <= $position;
    }

    public function setImportance(int $importance): self
    {
        $this->importance = $importance;

        return $this;
    }

    public function isImportanceLowerThan(int $importance): bool
    {
        return $this->importance < $importance;
    }

    public function isImportanceHigherThan(int $importance): bool
    {
        return $this->importance > $importance;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getDescription(): string
    {
        return (string)$this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getComment(): string
    {
        return (string)$this->comment;
    }

    public function setComment(string $comment): Recommendation
    {
        $this->comment = $comment;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getResponsible(): string
    {
        return (string)$this->responsible;
    }

    public function setResponsible(string $responsible): Recommendation
    {
        $this->responsible = $responsible;

        return $this;
    }

    public function getCounterTreated(): int
    {
        return $this->counterTreated;
    }

    public function setCounterTreated(int $counterTreated): self
    {
        $this->counterTreated = $counterTreated;

        return $this;
    }

    public function incrementCounterTreated(): self
    {
        $this->counterTreated++;

        return $this;
    }
}
