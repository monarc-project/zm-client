<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use MonarcCore\Model\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * RecordInternationalTransfer
 *
 * @ORM\Table(name="record_international_transfers", indexes={
 *      @ORM\Index(name="record", columns={"record_id"}),
 *      @ORM\Index(name="processor", columns={"processor_id"})
 * })
 * @ORM\Entity
 */
class RecordInternationalTransfer extends AbstractEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var \MonarcFO\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $anr;

    /**
     * @var \MonarcFO\Model\Entity\Record
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Record")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="record_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $record;

    /**
     * @var \MonarcFO\Model\Entity\RecordProcessor
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\RecordProcessor")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="processor_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $processor;

    /**
     * @var string
     *
     * @ORM\Column(name="organisation", type="string", length=255, nullable=true)
     */
    protected $organisation;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    protected $description;

    /**
     * @var string
     *
     * @ORM\Column(name="country", type="string", length=255, nullable=true)
     */
    protected $country;

    /**
     * @var array
     *
     * @ORM\Column(name="documents", type="array", nullable=true)
     */
    protected $documents;

    /**
     * @var string
     *
     * @ORM\Column(name="creator", type="string", length=255, nullable=true)
     */
    protected $creator;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    protected $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(name="updater", type="string", length=255, nullable=true)
     */
    protected $updater;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    protected $updatedAt;

    public function __construct($obj = null)
    {
        $this->documents = [];
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
    * @return Record
    */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }

    /**
     * @return string
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @param string $creator
     * @return RecordInternationalTransfer
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     * @return RecordInternationalTransfer
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getUpdater()
    {
        return $this->updater;
    }

    /**
     * @param string $updater
     * @return RecordInternationalTransfer
     */
    public function setUpdater($updater)
    {
        $this->updater = $updater;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     * @return RecordInternationalTransfer
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
