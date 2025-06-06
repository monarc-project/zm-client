<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Monarc\Core\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * RecordInternationalTransfer
 *
 * @ORM\Table(name="record_international_transfers", indexes={
 *      @ORM\Index(name="record", columns={"record_id"}),
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class RecordInternationalTransfer extends AbstractEntity
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * @var Record
     * @ORM\ManyToOne(targetEntity="Record")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="record_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $record;
    /**
     * @var string
     *
     * @ORM\Column(name="organisation", type="string", length=255, nullable=true)
     */
    protected $organisation;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected $description;

    /**
     * @var string
     *
     * @ORM\Column(name="country", type="string", length=255, nullable=true)
     */
    protected $country;

    /**
     * @var string
     *
     * @ORM\Column(name="documents", type="string", length=255, nullable=true)
     */
    protected $documents;

    public function __construct($obj = null)
    {
        parent::__construct($obj);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return Record
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param int $anr
     *
     * @return Record
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;

        return $this;
    }

    /**
     * @return Record
     */
    public function getRecord()
    {
        return $this->record;
    }

    /**
     * @param int $record
     *
     * @return RecordInternationalTransfer
     */
    public function setRecord($record)
    {
        $this->record = $record;

        return $this;
    }

    public function getOrganisation(): string
    {
        return (string)$this->organisation;
    }

    public function setOrganisation($organisation): self
    {
        $this->organisation = $organisation;

        return $this;
    }

    public function getDescription(): string
    {
        return (string)$this->description;
    }

    public function setDescription($description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCountry(): string
    {
        return (string)$this->country;
    }

    public function setCountry($country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getDocuments(): string
    {
        return (string)$this->documents;
    }

    public function setDocuments($documents): self
    {
        $this->documents = $documents;

        return $this;
    }
}
