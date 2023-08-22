<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Table(name="anrs", uniqueConstraints={@ORM\UniqueConstraint(name="uuid", columns={"uuid"})})
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Anr extends AnrSuperClass
{
    /**
     * @var LazyUuidFromString|string
     *
     * @ORM\Column(name="uuid", type="uuid", nullable=false)
     */
    protected $uuid;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255, nullable=false)
     */
    protected $label;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected $description;

    /**
     * @var ArrayCollection|MonarcObject[]
     *
     * @ORM\OneToMany(targetEntity="MonarcObject", mappedBy="anr")
     */
    protected $objects;

    /**
     * @var ArrayCollection|ObjectCategory[]
     *
     * @ORM\OneToMany(targetEntity="ObjectCategory", mappedBy="anr")
     */
    protected $objectCategories;

    /**
     * @var int
     *
     * @ORM\Column(name="language", type="integer", options={"unsigned":true, "default":1})
     */
    protected $language = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="language_code", type="string", nullable=false, length=8, options={"default": "fr"})
     */
    protected $languageCode = 'fr';

    /**
     * @var int
     *
     * @ORM\Column(name="model_id", type="integer", options={"unsigned":true, "default":0})
     */
    protected $modelId = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="model_impacts", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $modelImpacts = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="cache_model_are_scales_updatable", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $cacheModelAreScalesUpdatable = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="is_visible_on_dashboard", type="smallint", options={"default":1})
     */
    protected $isVisibleOnDashboard = 1;

    /**
     * @var int
     *
     * @ORM\Column(name="is_stats_collected", type="smallint", options={"default":1})
     */
    protected $isStatsCollected = 1;

    /**
     * @var Referential[]|ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="Referential", mappedBy="anr", cascade={"persist"})
     */
    protected $referentials;

    public function __construct()
    {
        $this->objects = new ArrayCollection();
        $this->objectCategories = new ArrayCollection();
        $this->referentials = new ArrayCollection();
    }

    /**
     * Only the primitive data types properties values are set to the new object.
     * The relation properties have to be recreated manually.
     *
     * @return Anr
     */
    public static function constructFromObjectAndData(AnrSuperClass $anr, array $data): AnrSuperClass
    {
        /** @var Anr $newAnr */
        $newAnr = parent::constructFromObject($anr);

        if ($anr instanceof self) {
            /* Duplication of a FrontOffice analysis, creation of a snapshot or restoring it.
             * For snapshots we use tha same label and description (label [SNAP] prefix will be added later). */
            $newAnr->setLabel($data['label' . $anr->getLanguage()] ?? $anr->getLabel())
                ->setDescription($data['description' . $anr->getLanguage()] ?? $anr->getDescription())
                ->setLanguage($anr->getLanguage())
                ->setLanguageCode($anr->getLanguageCode())
                ->setIsVisibleOnDashboard((int)$anr->isVisibleOnDashboard())
                ->setIsStatsCollected((int)$anr->isStatsCollected())
                ->setModelId($anr->getModelId());
        } elseif ($anr instanceof \Monarc\Core\Model\Entity\Anr) {
            /* Creation of an analysis based on a model. */
            $languageIndex = (int)($data['language'] ?? 1);
            // TODO: define the langCode.
            $newAnr->setLabel((string)$data['label' . $languageIndex])
                ->setDescription((string)$data['description' . $languageIndex])
                ->setLanguage($languageIndex)
                ->setLanguageCode($data['languageCode'] ?? 'fr')
                ->setModelId($anr->getModel()->getId());
        } else {
            throw new \LogicException('The analysis can not be created due to the logic error.');
        }

        return $newAnr;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

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

    public function setLanguage($language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getLanguage(): int
    {
        return $this->language;
    }

    /**
     * @ORM\PrePersist
     */
    public function generateAndSetUuid(): self
    {
        $this->uuid = Uuid::uuid4();

        return $this;
    }

    public function getUuid(): string
    {
        return (string)$this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function isVisibleOnDashboard(): bool
    {
        return (bool)$this->isVisibleOnDashboard;
    }

    public function setIsVisibleOnDashboard(int $isVisibleOnDashboard): self
    {
        $this->isVisibleOnDashboard = $isVisibleOnDashboard;

        return $this;
    }

    public function isStatsCollected(): bool
    {
        return (bool)$this->isStatsCollected;
    }

    public function setIsStatsCollected(int $isStatsCollected): self
    {
        $this->isStatsCollected = $isStatsCollected;

        return $this;
    }

    public function setModelId(int $modelId): self
    {
        $this->modelId = $modelId;

        return $this;
    }

    public function getModelId(): ?int
    {
        return $this->modelId;
    }

    public function getModelImpacts(): int
    {
        return $this->modelImpacts;
    }

    public function setModelImpacts(int $modelImpacts): self
    {
        $this->modelImpacts = $modelImpacts;

        return $this;
    }

    public function getCacheModelAreScalesUpdatable(): int
    {
        return $this->cacheModelAreScalesUpdatable;
    }

    public function setCacheModelAreScalesUpdatable(int $cacheModelAreScalesUpdatable): self
    {
        $this->cacheModelAreScalesUpdatable = $cacheModelAreScalesUpdatable;

        return $this;
    }

    public function getObjects()
    {
        return $this->objects;
    }

    public function addObject(MonarcObject $object): self
    {
        if (!$this->objects->contains($object)) {
            $this->objects->add($object);
            $object->setAnr($this);
        }

        return $this;
    }

    public function getObjectCategories()
    {
        return $this->objectCategories;
    }

    public function addObjectCategory(ObjectCategory $objectCategory): self
    {
        if (!$this->objectCategories->contains($objectCategory)) {
            $this->objectCategories->add($objectCategory);
            $objectCategory->setAnr($this);
        }

        return $this;
    }

    public function getReferentials()
    {
        return $this->referentials;
    }

    public function addReferential(Referential $referential): self
    {
        if (!$this->referentials->contains($referential)) {
            $this->referentials->add($referential);
            $referential->setAnr($this);
        }

        return $this;
    }

    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    public function setLanguageCode(string $languageCode): self
    {
        $this->languageCode = $languageCode;

        return $this;
    }
}
