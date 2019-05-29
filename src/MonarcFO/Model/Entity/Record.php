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
 * Record
 *
 * @ORM\Table(name="records", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class Record extends AbstractEntity
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
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var string
     *
     * @ORM\Column(name="label1", type="string", length=255, nullable=true)
     */
    protected $label1;

    /**
     * @var string
     *
     * @ORM\Column(name="label2", type="string", length=255, nullable=true)
     */
    protected $label2;

    /**
     * @var string
     *
     * @ORM\Column(name="label3", type="string", length=255, nullable=true)
     */
    protected $label3;

    /**
     * @var string
     *
     * @ORM\Column(name="label4", type="string", length=255, nullable=true)
     */
    protected $label4;

    /**
     * @var \MonarcFO\Model\Entity\Controller
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\RecordController", cascade={"persist"})
     * @ORM\JoinColumn(name="controller", referencedColumnName="id")
     */
    protected $controller;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RecordController", cascade={"persist"})
     * @ORM\JoinTable(name="records_record_joint_controllers",
     *  joinColumns={@ORM\JoinColumn(name="record_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="controller_id", referencedColumnName="id")}
     * )
     */
    protected $jointControllers;

    /**
     * @var string
     *
     * @ORM\Column(name="representative", type="string", length=255, nullable=true)
     */
    protected $representative;

    /**
     * @var string
     *
     * @ORM\Column(name="dpo", type="string", length=255, nullable=true)
     */
    protected $dpo;

    /**
     * @var string
     *
     * @ORM\Column(name="purposes", type="string", length=255, nullable=true)
     */
    protected $purposes;


    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    protected $description;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RecordRecipientCategory", cascade={"persist"})
     * @ORM\JoinTable(name="records_record_recipient_categories",
     *  joinColumns={@ORM\JoinColumn(name="record_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="recipient_category_id", referencedColumnName="id")}
     * )
     */
    protected $recipients;

    /**
     * @var string
     *
     * @ORM\Column(name="id_third_country", type="string", length=255, nullable=true)
     */
    protected $idThirdCountry;

    /**
     * @var string
     *
     * @ORM\Column(name="dpo_third_country", type="string", length=255, nullable=true)
     */
    protected $dpoThirdCountry;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="erasure", type="datetime", nullable=false)
     */
    protected $erasure;

    /**
     * @var string
     *
     * @ORM\Column(name="sec_measures", type="string", length=255, nullable=true)
     */
    protected $secMeasures;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\RecordProcessor", cascade={"persist"})
     * @ORM\JoinTable(name="records_record_processors",
     *  joinColumns={@ORM\JoinColumn(name="record_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="processor_id", referencedColumnName="id")}
     * )
     */
    protected $processors;

    public function __construct($obj = null)
    {
        $this->joint = new ArrayCollection();
        $this->recipients = new ArrayCollection();
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
     * @return Controller
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
     * @return Controller
     */
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

    /**
    * @return string
    */
    public function getRepresentative()
    {
        return $this->representative;
    }
    /**
    * @param string $representative
    *
    */
    public function setRepresentative($representative)
    {
        $this->representative = $representative;
    }

    /**
    * @return string
    */
    public function getDpo()
    {
        return $this->dpo;
    }
    /**
    * @param string $dpo
    *
    */
    public function setDpo($dpo)
    {
        $this->dpo = $dpo;
    }

    /**
    * @return string
    */
    public function getPurposes()
    {
        return $this->purposes;
    }
    /**
    * @param string $purposes
    *
    */
    public function setPurposes($purposes)
    {
        $this->purposes = $purposes;
    }

    /**
    * @return string
    */
    public function getDescription()
    {
        return $this->description;
    }
    /**
    * @param string $description
    *
    */
    public function setDescription($description)
    {
        $this->description = $description;
    }
    /**
    * @return string
    */
    public function getIdThirdCountry()
    {
        return $this->idThirdCountry;
    }
    /**
    * @param string $idThirdCountry
    *
    */
    public function setIdThirdCountry($idThirdCountry)
    {
        $this->idThirdCountry = $idThirdCountry;
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
