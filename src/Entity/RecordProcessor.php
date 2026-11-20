<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * RecordProcessor
 */
#[ORM\Table(name: 'record_processors')]
#[ORM\Index(name: 'anr', columns: ['anr_id'])]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class RecordProcessor
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    #[ORM\ManyToOne(targetEntity: 'Anr')]
    #[ORM\JoinColumn(name: 'anr_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    protected Anr $anr;

    #[ORM\Column(name: 'label', type: 'string', length: 255, nullable: true)]
    protected ?string $label = null;


    #[ORM\Column(name: 'contact', type: 'string', length: 255, nullable: true)]
    protected ?string $contact = null;

    #[ORM\Column(name: 'activities', type: 'text', nullable: true)]
    protected ?string $activities = null;

    #[ORM\Column(name: 'sec_measures', type: 'text', nullable: true)]
    protected ?string $secMeasures = null;

    /**
     * @var RecordActor
     */
    #[ORM\ManyToOne(targetEntity: 'RecordActor', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'representative', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    protected ?RecordActor $representative = null;

    /**
     * @var RecordActor
     */
    #[ORM\ManyToOne(targetEntity: 'RecordActor', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'dpo', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    protected ?RecordActor $dpo = null;

    /**
     * @return int
     */


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

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getActivities(): ?string
    {
        return $this->activities;
    }

    public function setActivities(?string $activities): self
    {
        $this->activities = $activities;

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
}
