<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use MonarcCore\Model\Entity\AbstractEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * RecordPersonalData
 *
 * @ORM\Table(name="record_personal_data", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="record", columns={"record_id"})
 * })
 * @ORM\Entity
 */
class RecordPersonalData extends AbstractEntity
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
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * @var \MonarcFO\Model\Entity\Record
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Record")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="record_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $record;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RecordDataSubject", cascade={"persist"})
     * @ORM\JoinTable(name="record_personal_data_record_data_subjects",
     *  joinColumns={@ORM\JoinColumn(name="personal_data_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="data_subject_id", referencedColumnName="id")}
     * )
     */
    protected $dataSubjects;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RecordDataCategory", cascade={"persist"})
     * @ORM\JoinTable(name="record_personal_data_record_data_categories",
     *  joinColumns={@ORM\JoinColumn(name="personal_data_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="data_category_id", referencedColumnName="id")}
     * )
     */
    protected $dataCategories;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    protected $description;

    /**
     * @var integer
     *
     * @ORM\Column(name="retention_period", type="integer", options={"unsigned":true, "default":0})
     */
    protected $retentionPeriod = 0;

    /**
     * @var smallint
     *
     * @ORM\Column(name="retention_period_mode", type="smallint", options={"default":0})
     */
    protected $retentionPeriodMode = 0;


    /**
     * @var string
     *
     * @ORM\Column(name="retention_period_description", type="string", length=255, nullable=false)
     */
    protected $retentionPeriodDescription;

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
        $this->dataSubjects = new ArrayCollection();
        $this->dataCategories = new ArrayCollection();
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
     * @return RecordPersonalData
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
     * @return RecordPersonalData
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
     * @return RecordPersonalData
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
     * @return RecordPersonalData
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
