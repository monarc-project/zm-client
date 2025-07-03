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
 * RecordProcessor
 *
 * @ORM\Table(name="record_processors", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class RecordProcessor extends AbstractEntity
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @var int
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
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255, nullable=true)
     */
    protected $label;


    /**
     * @var string
     *
     * @ORM\Column(name="contact", type="string", length=255, nullable=true)
     */
    protected $contact;

    /**
     * @var string
     *
     * @ORM\Column(name="activities", type="text", nullable=true)
     */
    protected $activities;

    /**
     * @var string
     *
     * @ORM\Column(name="sec_measures", type="text", nullable=true)
     */
    protected $secMeasures;

    /**
     * @var RecordActor
     * @ORM\ManyToOne(targetEntity="RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="representative", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $representative;

    /**
     * @var RecordActor
     * @ORM\ManyToOne(targetEntity="RecordActor", cascade={"persist"})
     * @ORM\JoinColumn(name="dpo", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $dpo;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return RecordProcessor
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
    * @param int|Anr $anr
    * @return RecordProcessor
    */
    public function setAnr($anr)
    {
        $this->anr = $anr;
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
    * @return RecordProcessor
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
    * @return RecordProcessor
    */
    public function setDpo($dpo)
    {
        $this->dpo = $dpo;
        return $this;
    }

    /**
    * @return string
    */
    public function getActivities(): string
    {
        return (string)$this->activities;
    }
    /**
    * @param string $activities
    * @return RecordProcessor
    */
    public function setActivities($activities)
    {
        $this->activities = $activities;
        return $this;
    }

    /**
    * @return string
    */
    public function getSecMeasures(): string
    {
        return (string)$this->secMeasures;
    }
    /**
    * @param string $secMeasures
    * @return RecordProcessor
    */
    public function setSecMeasures($secMeasures)
    {
        $this->secMeasures = $secMeasures;
        return $this;
    }

    public function getLabel(): string
    {
        return (string)$this->label;
    }

    public function setLabel($label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getContact(): string
    {
        return (string)$this->contact;
    }

    public function setContact($contact): self
    {
        $this->contact = $contact;

        return $this;
    }
}
