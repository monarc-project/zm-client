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
 * RecordProcessor
 *
 * @ORM\Table(name="record_processors", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class RecordProcessor extends AbstractEntity
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
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255, nullable=true)
     */
    protected $label;

    /**
     * @var array
     *
     * @ORM\Column(name="activities", type="array", nullable=true)
     */
    protected $activities;

    /**
     * @var string
     *
     * @ORM\Column(name="sec_measures", type="string", length=255, nullable=true)
     */
    protected $secMeasures;

    /**
     * @var \MonarcFO\Model\Entity\RecordActor
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="representative", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $representative;

    /**
     * @var \MonarcFO\Model\Entity\RecordActor
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="dpo", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $dpo;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RecordActor", cascade={"persist"})
     * @ORM\JoinTable(name="record_processors_record_actors",
     *  joinColumns={@ORM\JoinColumn(name="processor_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="actor_id", referencedColumnName="id")}
     * )
     */
    protected $cascadedProcessors;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\OneToMany(targetEntity="MonarcFO\Model\Entity\RecordInternationalTransfer", mappedBy="processor", cascade={"persist"})
     */
    protected $internationalTransfers;

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
        $this->activities = [];
        $this->cascadedProcessors = new ArrayCollection();
        $this->internationalTransfers = new ArrayCollection();
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
     * @return RecordProcessor
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
     * @return RecordProcessor
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
     * @return RecordProcessor
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
     * @return RecordProcessor
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }


    /**
     * @return Controller
     */
    public function getControllers()
    {
        return $this->controllers;
    }

    /**
    * @param int $jointControllers
    * @return Record
    */
    public function setControllers($controllers)
    {
        $this->controllers = $controllers;
        return $this;
    }
}
