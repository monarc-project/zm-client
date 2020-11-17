<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Ramsey\Uuid\Uuid;

/**
 * Anr
 *
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
     * @var int
     *
     * @ORM\Column(name="language", type="integer", options={"unsigned":true, "default":1})
     */
    protected $language = 1;

    /**
     * @var int
     *
     * @ORM\Column(name="model_id", type="integer", options={"unsigned":true, "default":0})
     */
    protected $model = '0';

    /**
     * @var int
     *
     * @ORM\Column(name="model_impacts", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $modelImpacts = '0';

    /**
     * @var int
     *
     * @ORM\Column(name="cache_model_is_scales_updatable", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $cacheModelIsScalesUpdatable = '0';

    /**
     * @var int
     *
     * @ORM\Column(name="is_visible_on_dashboard", type="smallint", options={"default":1})
     */
    protected $isVisibleOnDashboard = 1;

    /**
     * @var Referential[]|ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="Monarc\FrontOffice\Model\Entity\Referential", mappedBy="anr", cascade={"persist"})
     */
    protected $referentials;

    /**
     * @param Referential[]|ArrayCollection $referentials
     */
    public function setReferentials($referentials)
    {
        $this->referentials = $referentials;
    }

    /**
     * @return Referential[]
     */
    public function getReferentials()
    {
        return $this->referentials;
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
        return $this->uuid;
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
}
