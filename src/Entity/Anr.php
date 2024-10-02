<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Anr as AnrCore;
use Monarc\Core\Entity\AnrSuperClass;
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
     * @var ArrayCollection|Snapshot[]
     *
     * @ORM\OneToMany(targetEntity="Snapshot", mappedBy="anrReference", cascade={"remove"})
     */
    protected $referencedSnapshots;

    /**
     * @var ArrayCollection|RecommendationSet[]
     *
     * @ORM\OneToMany(targetEntity="RecommendationSet", mappedBy="anr", cascade={"remove"})
     */
    protected $recommendationSets;

    /**
     * @var Snapshot|null
     *
     * @ORM\OneToOne(targetEntity="Snapshot", mappedBy="anr", cascade={"remove"})
     */
    protected $snapshot;

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
     * @ORM\OneToMany(targetEntity="Referential", mappedBy="anr", cascade={"remove"})
     */
    protected $referentials;

    /**
     * @var UserAnr[]|ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="UserAnr", mappedBy="anr", cascade={"remove"})
     */
    protected $usersAnrsPermissions;

    public function __construct()
    {
        parent::__construct();

        $this->objects = new ArrayCollection();
        $this->objectCategories = new ArrayCollection();
        $this->referentials = new ArrayCollection();
        $this->referencedSnapshots = new ArrayCollection();
        $this->usersAnrsPermissions = new ArrayCollection();
        $this->recommendationSets = new ArrayCollection();
    }

    /**
     * Only the primitive data types properties values are set to the new object.
     * The relation properties have to be recreated manually.
     *
     * @return Anr
     */
    public static function constructFromObjectAndData(AnrSuperClass $sourceAnr, array $data): AnrSuperClass
    {
        /** @var Anr $newAnr */
        $newAnr = parent::constructFromObject($sourceAnr);

        if ($sourceAnr instanceof self) {
            /* Duplication of a FrontOffice analysis, creation of a snapshot or restoring it.
             * For snapshots we use tha same label and description (label [SNAP] prefix will be added later). */
            $newAnr->setLabel($data['label'] ?? $sourceAnr->getLabel())
                ->setDescription($data['description'] ?? $sourceAnr->getDescription())
                ->setLanguage($sourceAnr->getLanguage())
                ->setLanguageCode($sourceAnr->getLanguageCode())
                ->setIsVisibleOnDashboard((int)$sourceAnr->isVisibleOnDashboard())
                ->setIsStatsCollected((int)$sourceAnr->isStatsCollected())
                ->setModelId($sourceAnr->getModelId())
                ->setCacheModelAreScalesUpdatable($sourceAnr->getCacheModelAreScalesUpdatable())
                ->setCacheModelShowRolfBrut($sourceAnr->getCacheModelShowRolfBrut());
        } elseif ($sourceAnr instanceof AnrCore) {
            /* Creation of an analysis based on a model. */
            $languageIndex = (int)($data['language'] ?? 1);
            $newAnr->setLabel((string)$data['label'])
                ->setDescription((string)$data['description'])
                ->setLanguage($languageIndex)
                ->setLanguageCode($data['languageCode'] ?? 'fr')
                ->setModelId($sourceAnr->getModel()->getId())
                ->setCacheModelShowRolfBrut($sourceAnr->getModel()->showRolfBrut())
                ->setCacheModelAreScalesUpdatable($sourceAnr->getModel()->areScalesUpdatable());
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

    public function getCacheModelAreScalesUpdatable(): bool
    {
        return (bool)$this->cacheModelAreScalesUpdatable;
    }

    public function setCacheModelAreScalesUpdatable(bool $cacheModelAreScalesUpdatable): self
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

    public function removeReferential(Referential $referential): self
    {
        if ($this->referentials->contains($referential)) {
            $this->referentials->removeElement($referential);
        }

        return $this;
    }

    public function getReferencedSnapshots()
    {
        return $this->referencedSnapshots;
    }

    public function addReferencedSnapshot(Snapshot $snapshot): self
    {
        if (!$this->referencedSnapshots->contains($snapshot)) {
            $this->referencedSnapshots->add($snapshot);
            $snapshot->setAnrReference($this);
        }

        return $this;
    }

    public function removeReferencedSnapshot(Snapshot $snapshot): self
    {
        if ($this->referencedSnapshots->contains($snapshot)) {
            $this->referencedSnapshots->removeElement($snapshot);
        }

        return $this;
    }

    public function getRecommendationSets()
    {
        return $this->recommendationSets;
    }

    public function addRecommendationSet(RecommendationSet $recommendationSet): self
    {
        if (!$this->recommendationSets->contains($recommendationSet)) {
            $this->recommendationSets->add($recommendationSet);
            $recommendationSet->setAnr($this);
        }

        return $this;
    }

    public function isAnrSnapshot(): bool
    {
        return $this->snapshot !== null;
    }

    public function getSnapshot(): ?Snapshot
    {
        return $this->snapshot;
    }

    public function setSnapshot(Snapshot $snapshot): self
    {
        $this->snapshot = $snapshot;
        $snapshot->setAnr($this);

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

    public function getUsersAnrsPermissions()
    {
        return $this->usersAnrsPermissions;
    }

    public function addUserAnrPermission(UserAnr $userAnrPermission): self
    {
        if (!$this->usersAnrsPermissions->contains($userAnrPermission)) {
            $this->usersAnrsPermissions->add($userAnrPermission);
            $userAnrPermission->setAnr($this);
        }

        return $this;
    }

    public function removeUserAnrPermission(UserAnr $userAnrPermission): self
    {
        if ($this->usersAnrsPermissions->contains($userAnrPermission)) {
            $this->usersAnrsPermissions->removeElement($userAnrPermission);
        }

        return $this;
    }

    public function getInformationalRiskLevelColor(int $riskValue): string
    {
        if ($riskValue <= $this->seuil1) {
            return 'green';
        }
        if ($riskValue <= $this->seuil2) {
            return 'orange';
        }

        return 'alerte';
    }

    public function getOperationalRiskLevelColor(int $riskValue): string
    {
        if ($riskValue <= $this->seuilRolf1) {
            return 'green';
        }
        if ($riskValue <= $this->seuilRolf2) {
            return 'orange';
        }

        return 'alerte';
    }
}
