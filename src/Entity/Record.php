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
 * Record
 *
 * @ORM\Table(name="records", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Record
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
     * @ORM\Column(name="label", type="string", length=255, nullable=true)
     */
    protected ?string $label = null;

    /**
     * @ORM\Column(name="purposes", type="text", nullable=true)
     */
    protected ?string $purposes = null;

    /**
     * @ORM\Column(name="sec_measures", type="text", nullable=true)
     */
    protected ?string $secMeasures = null;

    /**
     * @var RecordActor
     * @ORM\ManyToOne(targetEntity="RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="controller_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected ?RecordActor $controller = null;

    /**
     * @var RecordActor
     * @ORM\ManyToOne(targetEntity="RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="representative_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected ?RecordActor $representative = null;

    /**
     * @var RecordActor
     * @ORM\ManyToOne(targetEntity="RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="dpo_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected ?RecordActor $dpo = null;

    /**
     * @var Collection|RecordActor[]
     * @ORM\ManyToMany(targetEntity="RecordActor", cascade={"persist"})
     * @ORM\JoinTable(name="records_record_joint_controllers",
     *  joinColumns={@ORM\JoinColumn(name="record_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="controller_id", referencedColumnName="id")}
     * )
     */
    protected Collection $jointControllers;

    /**
     * @var Collection|RecordPersonalData[]
     * @ORM\OneToMany(
     *     targetEntity="RecordPersonalData", mappedBy="record",
     *     cascade={"persist", "remove"}, orphanRemoval=true
     * )
     */
    protected Collection $personalData;

    /**
     * @var Collection|RecordRecipient[]
     * @ORM\ManyToMany(targetEntity="RecordRecipient", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinTable(name="records_record_recipients",
     *  joinColumns={@ORM\JoinColumn(name="record_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="recipient_id", referencedColumnName="id")}
     * )
     */
    protected Collection $recipients;

    /**
     * @var Collection|RecordInternationalTransfer[]
     * @ORM\OneToMany(
     *     targetEntity="RecordInternationalTransfer", mappedBy="record",
     *     cascade={"persist", "remove"}, orphanRemoval=true
     * )
     */
    protected Collection $internationalTransfers;

    /**
     * @var Collection|RecordProcessor[]
     * @ORM\ManyToMany(targetEntity="RecordProcessor", cascade={"persist"})
     * @ORM\JoinTable(name="records_record_processors",
     *  joinColumns={@ORM\JoinColumn(name="record_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="processor_id", referencedColumnName="id")}
     * )
     */
    protected Collection $processors;

    public function __construct()
    {
        $this->jointControllers = new ArrayCollection();
        $this->personalData = new ArrayCollection();
        $this->recipients = new ArrayCollection();
        $this->internationalTransfers = new ArrayCollection();
        $this->processors = new ArrayCollection();
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getPurposes(): ?string
    {
        return $this->purposes;
    }

    public function setPurposes(?string $purposes): self
    {
        $this->purposes = $purposes;

        return $this;
    }

    public function getSecMeasures(): ?string
    {
        return $this->secMeasures;
    }

    public function setSecMeasures(?string $secMeasures): self
    {
        $this->secMeasures = $secMeasures;

        return $this;
    }

    public function getController(): ?RecordActor
    {
        return $this->controller;
    }

    public function setController(?RecordActor $controller): self
    {
        $this->controller = $controller;

        return $this;
    }

    public function getRepresentative(): ?RecordActor
    {
        return $this->representative;
    }

    public function setRepresentative(?RecordActor $representative): self
    {
        $this->representative = $representative;

        return $this;
    }

    public function getDpo(): ?RecordActor
    {
        return $this->dpo;
    }

    public function setDpo(?RecordActor $dpo): self
    {
        $this->dpo = $dpo;

        return $this;
    }

    public function getJointControllers(): Collection
    {
        return $this->jointControllers;
    }

    public function setJointControllers(iterable $jointControllers): self
    {
        $this->jointControllers->clear();
        foreach ($jointControllers as $recordActor) {
            if (!$this->jointControllers->contains($recordActor)) {
                $this->jointControllers->add($recordActor);
            }
        }

        return $this;
    }

    public function getPersonalData(): Collection
    {
        return $this->personalData;
    }

    public function setPersonalData(iterable $personalData): self
    {
        $personalData = [...$personalData];
        foreach ($this->personalData->toArray() as $existingRecordPersonalData) {
            if (!in_array($existingRecordPersonalData, $personalData, true)) {
                $this->personalData->removeElement($existingRecordPersonalData);
            }
        }
        foreach ($personalData as $recordPersonalData) {
            $recordPersonalData->setRecord($this);
            if (!$this->personalData->contains($recordPersonalData)) {
                $this->personalData->add($recordPersonalData);
            }
        }

        return $this;
    }

    public function getRecipients(): Collection
    {
        return $this->recipients;
    }

    public function setRecipients(iterable $recipients): self
    {
        $this->recipients->clear();
        foreach ($recipients as $recordRecipient) {
            if (!$this->recipients->contains($recordRecipient)) {
                $this->recipients->add($recordRecipient);
            }
        }

        return $this;
    }

    public function getInternationalTransfers(): Collection
    {
        return $this->internationalTransfers;
    }

    public function setInternationalTransfers(iterable $internationalTransfers): self
    {
        $internationalTransfers = [...$internationalTransfers];
        foreach ($this->internationalTransfers->toArray() as $existingRecordInternationalTransfer) {
            if (!in_array($existingRecordInternationalTransfer, $internationalTransfers, true)) {
                $this->internationalTransfers->removeElement($existingRecordInternationalTransfer);
            }
        }
        foreach ($internationalTransfers as $recordInternationalTransfer) {
            $recordInternationalTransfer->setRecord($this);
            if (!$this->internationalTransfers->contains($recordInternationalTransfer)) {
                $this->internationalTransfers->add($recordInternationalTransfer);
            }
        }

        return $this;
    }

    public function getProcessors(): Collection
    {
        return $this->processors;
    }

    public function setProcessors(iterable $processors): self
    {
        $this->processors->clear();
        foreach ($processors as $recordProcessor) {
            if (!$this->processors->contains($recordProcessor)) {
                $this->processors->add($recordProcessor);
            }
        }

        return $this;
    }

    public function addJointController(RecordActor $recordActor): self
    {
        if (!$this->jointControllers->contains($recordActor)) {
            $this->jointControllers->add($recordActor);
        }

        return $this;
    }

    public function addRecipientCategory(RecordRecipient $recordRecipient): self
    {
        if (!$this->recipients->contains($recordRecipient)) {
            $this->recipients->add($recordRecipient);
        }

        return $this;
    }

    public function addProcessor(RecordProcessor $recordProcessor): self
    {
        if (!$this->processors->contains($recordProcessor)) {
            $this->processors->add($recordProcessor);
        }

        return $this;
    }
}
