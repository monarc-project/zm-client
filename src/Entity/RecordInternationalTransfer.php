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
 * RecordInternationalTransfer
 */
#[ORM\Table(name: 'record_international_transfers')]
#[ORM\Index(name: 'record', columns: ['record_id'])]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class RecordInternationalTransfer
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

    /**
     * @var Record
     */
    #[ORM\ManyToOne(targetEntity: 'Record', inversedBy: 'internationalTransfers')]
    #[ORM\JoinColumn(name: 'record_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    protected Record $record;
    #[ORM\Column(name: 'organisation', type: 'string', length: 255, nullable: true)]
    protected ?string $organisation = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(name: 'country', type: 'string', length: 255, nullable: true)]
    protected ?string $country = null;

    #[ORM\Column(name: 'documents', type: 'string', length: 255, nullable: true)]
    protected ?string $documents = null;

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

    public function getOrganisation(): ?string
    {
        return $this->organisation;
    }

    public function setOrganisation(?string $organisation): self
    {
        $this->organisation = $organisation;

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

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getDocuments(): ?string
    {
        return $this->documents;
    }

    public function setDocuments(?string $documents): self
    {
        $this->documents = $documents;

        return $this;
    }
}
