<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\MeasureSuperClass;

/**
 * Measure
 *
 * @ORM\Table(name="measures", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="category", columns={"soacategory_id"}),
 *      @ORM\Index(name="referential", columns={"referential_uuid"})
* })
 * @ORM\Entity
 */
class Measure extends MeasureSuperClass
{
   /**
     * Many Measures have many Measures.
     * @ORM\ManyToMany(targetEntity="Monarc\FrontOffice\Model\Entity\Measure", cascade={"persist"})
     * @ORM\JoinTable(name="measures_measures",
     *      joinColumns={@ORM\JoinColumn(name="child_id", referencedColumnName="uuid"),@ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="father_id", referencedColumnName="uuid"),@ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")}
     *      )
     */
   protected $measuresLinked;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\Anr
     *  @ORM\Id
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Anr", )
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
    * @var \Doctrine\Common\Collections\Collection
    * @ORM\ManyToMany(targetEntity="Monarc\FrontOffice\Model\Entity\Amv", inversedBy="measures", fetch="EAGER")
    * @ORM\JoinTable(name="measures_amvs",
    *  joinColumns={@ORM\JoinColumn(name="measure_id", referencedColumnName="uuid"),@ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")},
    *  inverseJoinColumns={@ORM\JoinColumn(name="amv_id", referencedColumnName="uuid"),@ORM\JoinColumn(name="anr_id2", referencedColumnName="anr_id")},
    * )
    */
    protected $amvs;

    /**
    * @var \Doctrine\Common\Collections\Collection
    * @ORM\ManyToMany(targetEntity="Monarc\FrontOffice\Model\Entity\RolfRisk", inversedBy="measures", cascade={"persist"})
    * @ORM\JoinTable(name="measures_rolf_risks",
    *  inverseJoinColumns={@ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id")},
    *  joinColumns={@ORM\JoinColumn(name="measure_id", referencedColumnName="uuid"),@ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")},
    * )
    */
    protected $rolfRisks;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\SoaCategory
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\SoaCategory", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="soacategory_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $category;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\Referential
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Referential", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="referential_uuid", referencedColumnName="uuid", nullable=true),
     *    @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $referential;

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return Measure
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }

    /**
     * @param Measure $measuresLinked
     * @return Measure
     */
    public function setMeasuresLinked($measuresLinked)
    {
        $this->measuresLinked = $measuresLinked;
        return $this;
    }

    /**
     * @return Measure
     */
    public function getMeasuresLinked()
    {
        return $this->measuresLinked;
    }

    /**
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param Category $category
     * @return Measure
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Referential
     */
    public function getReferential()
    {
        return $this->referential;
    }

    /**
     * @param Referential $category
     * @return Measure
     */
    public function setReferential($referential)
    {
        $this->referential = $referential;
        return $this;
    }
}
