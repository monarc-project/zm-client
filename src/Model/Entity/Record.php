<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Monarc\Core\Model\Entity\AbstractEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * Record
 *
 * @ORM\Table(name="records", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Record extends AbstractEntity
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
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"persist"})
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
     * @ORM\Column(name="purposes", type="string", length=255, nullable=true)
     */
    protected $purposes;

    /**
     * @var string
     *
     * @ORM\Column(name="sec_measures", type="string", length=255, nullable=true)
     */
    protected $secMeasures;

    /**
     * @var RecordActor
     * @ORM\ManyToOne(targetEntity="RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="controller_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $controller;

    /**
     * @var RecordActor
     * @ORM\ManyToOne(targetEntity="RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="representative_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $representative;

    /**
     * @var RecordActor
     * @ORM\ManyToOne(targetEntity="RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="dpo_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $dpo;

    /**
     * @var Collection|RecordActor[]
     * @ORM\ManyToMany(targetEntity="Monarc\FrontOffice\Model\Entity\RecordActor", cascade={"persist"})
     * @ORM\JoinTable(name="records_record_joint_controllers",
     *  joinColumns={@ORM\JoinColumn(name="record_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="controller_id", referencedColumnName="id")}
     * )
     */
    protected $jointControllers;

    /**
     * @var Collection|RecordPersonalData[]
     * @ORM\OneToMany(targetEntity="RecordPersonalData", mappedBy="record", cascade={"persist"})
     */
    protected $personalData;

    /**
     * @var Collection|RecordRecipient[]
     * @ORM\ManyToMany(targetEntity="RecordRecipient", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinTable(name="records_record_recipients",
     *  joinColumns={@ORM\JoinColumn(name="record_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="recipient_id", referencedColumnName="id")}
     * )
     */
    protected $recipients;

    /**
     * @var Collection|RecordInternationalTransfer[]
     * @ORM\OneToMany(targetEntity="RecordInternationalTransfer", mappedBy="record", cascade={"persist", "remove"})
     */
    protected $internationalTransfers;

    /**
     * @var Collection|RecordProcessor[]
     * @ORM\ManyToMany(targetEntity="RecordProcessor", cascade={"persist"})
     * @ORM\JoinTable(name="records_record_processors",
     *  joinColumns={@ORM\JoinColumn(name="record_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="processor_id", referencedColumnName="id")}
     * )
     */
    protected $processors;

    public function __construct($obj = null)
    {
        $this->jointControllers = new ArrayCollection();
        $this->personalData = new ArrayCollection();
        $this->recipients = new ArrayCollection();
        $this->internationalTransfers = new ArrayCollection();
        $this->processors = new ArrayCollection();

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
     * @param string $label
     *
     */
    public function setlabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return array
     */
    public function getPurposes()
    {
        return $this->purposes;
    }
    /**
     * @param string $purposes
     * @return Record
     */
    public function setPurposes($purposes)
    {
        $this->purposes = $purposes;
        return $this;
    }

    /**
     * @return RecordActor
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param int $controller
     * @return Record
     */
    public function setController($controller)
    {
        $this->controller = $controller;
        return $this;
    }

    /**
     * @return RecordActor
     */
    public function getRepresentative()
    {
        return $this->representative;
    }
    /**
     * @param string $representative
     * @return Record
     */
    public function setRepresentative($representative)
    {
        $this->representative = $representative;
        return $this;
    }

    /**
     * @return RecordActor
     */
    public function getDpo()
    {
        return $this->dpo;
    }
    /**
     * @param string $dpo
     * @return Record
     */
    public function setDpo($dpo)
    {
        $this->dpo = $dpo;
        return $this;
    }

    public function getJointControllers()
    {
        return $this->jointControllers;
    }

    /**
     * @param int $jointControllers
     * @return Record
     */
    public function setJointControllers($jointControllers)
    {
        $this->jointControllers = $jointControllers;
        return $this;
    }

    public function getPersonalData()
    {
        return $this->personalData;
    }

    /**
     * @param int $personalData
     * @return Record
     */
    public function setPersonalData($personalData)
    {
        $this->personalData = $personalData;
        return $this;
    }

    public function getInternationalTransfers()
    {
        return $this->internationalTransfers;
    }

    /**
     * @param int $internationalTransfers
     * @return Record
     */
    public function setInternationalTransfers($internationalTransfers)
    {
        $this->internationalTransfers = $internationalTransfers;
        return $this;
    }

    public function getProcessors()
    {
        return $this->processors;
    }

    /**
     * @param int $processors
     * @return Record
     */
    public function setProcessors($processors)
    {
        $this->processors = $processors;
        return $this;
    }

    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * @param int $recipients
     * @return Record
     */
    public function setRecipients($recipients)
    {
        $this->recipients = $recipients;
        return $this;
    }

    /**
     * Add joint controller
     *
     * @param int $jointController
     */
    public function addJointController($jointController)
    {
        $this->jointControllers->add($jointController);
    }
    /**
     * Add recipient category
     *
     * @param int $recipient
     */
    public function addRecipientCategory($recipient)
    {
        $this->recipients->add($recipient);
    }
    /**
     * Add processor
     *
     * @param int $processor
     */
    public function addProcessor($processor)
    {
        $this->processors->add($processor);
    }
}
