<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Monarc\Core\Model\Entity\AbstractEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

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
class RecordPersonalData extends AbstractEntity
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
     * @var \Monarc\FrontOffice\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\Record
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Record")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="record_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $record;

    /**
     * @var string
     *
     * @ORM\Column(name="data_subject", type="string", length=255, nullable=true)
     */
    protected $dataSubject;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="Monarc\FrontOffice\Model\Entity\RecordDataCategory", cascade={"persist"})
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
     // 0 for day(s), 1 for month(s) and 2 for year(s)
    protected $retentionPeriodMode = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="retention_period_description", type="string", length=255, nullable=false)
     */
    protected $retentionPeriodDescription;

    public function __construct($obj = null)
    {
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
     * @return RecordPersonalData
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
    * @return RecordPersonalData
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
    * @return RecordPersonalData
    */
    public function setRecord($record)
    {
        $this->record = $record;
        return $this;
    }

    /**
     * @return string
     */
    public function getDataSubject()
    {
        return $this->dataSubject;
    }

    /**
    * @param string $dataSubject
    * @return RecordPersonalData
    */
    public function setDataSubject($dataSubject)
    {
        $this->dataSubject = $dataSubject;
        return $this;
    }

    /**
     * @return RecordDataCategory
     */
    public function getDataCategories()
    {
        return $this->dataCategories;
    }

    /**
    * @param int $dataCategories
    * @return RecordPersonalData
    */
    public function setDataCategories($dataCategories)
    {
        $this->dataCategories = $dataCategories;
        return $this;
    }
}
