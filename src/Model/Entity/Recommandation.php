<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Monarc\Core\Validator\UniqueCode;

/**
 * Recommandation
 *
 * @ORM\Table(name="recommandations", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id","code"}),
 *      @ORM\Index(name="anr_id_2", columns={"anr_id"}),
 *      @ORM\Index(name="recommandation_set_uuid", columns={"recommandation_set_uuid", "code", "anr_id"}),
 *      @ORM\Index(name="recommendation_anr_position", columns={"anr_id", "position"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Recommandation extends AbstractEntity
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    const EMPTY_POSITION = 0;

    const EMPTY_IMPORTANCE = 0;
    const LOW_IMPORTANCE = 1;
    const MEDIUM_IMPORTANCE = 2;
    const HIGH_IMPORTANCE = 3;

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
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     * @ORM\Id
     */
    protected $anr;

    /**
     * @var RecommandationSet
     *
     * @ORM\ManyToOne(targetEntity="RecommandationSet", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="recommandation_set_uuid", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $recommandationSet;

    /**
     * @var ArrayCollection|RecommandationRisk[]
     *
     * @ORM\OneToMany(targetEntity="RecommandationRisk", mappedBy="recommandation", cascade={"persist", "remove"})
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
     * @ORM\Column(name="comment", type="string", length=255, nullable=true)
     */
    protected $comment;

    /**
     * @var int
     *
     * @ORM\Column(name="status", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $status = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="responsable", type="string", length=255, nullable=true)
     */
    protected $responsable;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="duedate", type="datetime", nullable=true)
     */
    protected $duedate;

    /**
     * @var int
     *
     * @ORM\Column(name="counter_treated", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $counterTreated = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="original_code", type="string", length=100, nullable=true)
     */
    protected $originalCode;

    /**
     * @var string
     *
     * @ORM\Column(name="token_import", type="string", length=255, nullable=true)
     */
    protected $tokenImport;

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid($uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    public function setAnr($anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    public function getDueDate(): ?DateTime
    {
        return $this->duedate;
    }

    public function setDueDate(?DateTime $date): self
    {
        $this->duedate = $date;

        return $this;
    }

    /**
     * @return RecommandationSet
     */
    public function getRecommandationSet()
    {
        return $this->recommandationSet;
    }

    /**
     * @param RecommandationSet $recommandationSet
     */
    public function setRecommandationSet($recommandationSet): self
    {
        $this->recommandationSet = $recommandationSet;

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

    public function hasLinkedRecommendationRisks(): bool
    {
        return $this->recommendationRisks !== null && !$this->recommendationRisks->isEmpty();
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

    public function setComment(string $comment): Recommandation
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

    public function getResponsable(): string
    {
        return (string)$this->responsable;
    }

    public function setResponsable(string $responsable): Recommandation
    {
        $this->responsable = $responsable;

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

    public function getFiltersForService()
    {
        $filterJoin = [
            [
                'as' => 'r',
                'rel' => 'recommandationSet',
            ],
        ];
        $filterLeft = [
            [
                'as' => 'r1',
                'rel' => 'recommandationSet',
            ],
        ];
        $filtersCol = [
            'r.uuid',
            'r.anr',
            'r.code',
        ];

        return [$filterJoin, $filterLeft, $filtersCol];
    }

    /**
     * @param bool $partial
     * @return mixed
     */
    public function getInputFilter($partial = true)
    {
        if (!$this->inputFilter) {
            parent::getInputFilter($partial);

            $validatorsCode = [];
            if (!$partial) {
                // TODO: fix the validator as far as the code is unique within a set.
                $validatorsCode = [
                    [
                        'name' => UniqueCode::class,
                        'options' => [
                            'entity' => $this
                        ],
                    ],
                ];
            }

            $this->inputFilter->add([
                'name' => 'code',
                'required' => !$partial,
                'allow_empty' => false,
                'filters' => [],
                'validators' => $validatorsCode
            ]);

            $this->inputFilter->add([
                'name' => 'anr',
                'required' => !$partial,
                'allow_empty' => false,
            ]);

            return $this->inputFilter;
        }
    }
}
