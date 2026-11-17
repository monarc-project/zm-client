<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\Collection;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * RecordPersonalData
 *
 * @ORM\Table(name="record_personal_data", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="record", columns={"record_id"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class RecordPersonalData
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected int $id;

    /**
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected Anr $anr;

    /**
     * @var Record
     * @ORM\ManyToOne(targetEntity="Record", inversedBy="personalData")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="record_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected Record $record;

    /**
     * @ORM\Column(name="data_subject", type="text", nullable=true)
     */
    protected ?string $dataSubject = null;

    /**
     * @var RecordDataCategory[]
     * @ORM\ManyToMany(targetEntity="RecordDataCategory", cascade={"persist"})
     * @ORM\JoinTable(name="record_personal_data_record_data_categories",
     *  joinColumns={@ORM\JoinColumn(name="personal_data_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="data_category_id", referencedColumnName="id")}
     * )
     */
    protected Collection $dataCategories;

    /**
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected ?string $description = null;

    /**
     * @ORM\Column(name="retention_period", type="integer", options={"unsigned":true, "default":0})
     */
    protected int $retentionPeriod = 0;

    /**
     * @ORM\Column(name="retention_period_mode", type="smallint", options={"default":0})
     */
     // 0 for day(s), 1 for month(s) and 2 for year(s)
    protected int $retentionPeriodMode = 0;

    /**
     * @ORM\Column(name="retention_period_description", type="text", nullable=true)
     */
    protected ?string $retentionPeriodDescription = null;

    public function __construct()
    {
        $this->dataCategories = new ArrayCollection();
    }

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

    public function getRecord(): Record
    {
        return $this->record;
    }

    public function setRecord(Record $record): self
    {
        $this->record = $record;

        return $this;
    }

    public function getDataSubject(): ?string
    {
        return $this->dataSubject;
    }

    public function setDataSubject(?string $dataSubject): self
    {
        $this->dataSubject = $dataSubject;

        return $this;
    }

    public function getDataCategories(): Collection
    {
        return $this->dataCategories;
    }

    public function setDataCategories(iterable $dataCategories): self
    {
        $this->dataCategories->clear();
        foreach ($dataCategories as $entity) {
            if (!$this->dataCategories->contains($entity)) {
                $this->dataCategories->add($entity);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getRetentionPeriod(): int
    {
        return $this->retentionPeriod;
    }

    public function setRetentionPeriod(int $retentionPeriod): self
    {
        $this->retentionPeriod = $retentionPeriod;

        return $this;
    }

    public function getRetentionPeriodMode(): int
    {
        return $this->retentionPeriodMode;
    }

    public function setRetentionPeriodMode(int $retentionPeriodMode): self
    {
        $this->retentionPeriodMode = $retentionPeriodMode;

        return $this;
    }

    public function getRetentionPeriodDescription(): ?string
    {
        return $this->retentionPeriodDescription;
    }

    public function setRetentionPeriodDescription(?string $retentionPeriodDescription): self
    {
        $this->retentionPeriodDescription = $retentionPeriodDescription;

        return $this;
    }
}
