<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\MeasureSuperClass;

/**
 * Measure
 *
 * @ORM\Table(name="measures", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="category", columns={"soacategory_id"}),
 *      @ORM\Index(name="referential", columns={"referential_uniqid"})
* })
 * @ORM\Entity
 */
class Measure extends MeasureSuperClass
{

  /**
   * @ORM\ManyToMany(targetEntity="MonarcFO\Model\Entity\Measure")
   * @ORM\JoinTable(name="measures_measures",
   *     joinColumns={@ORM\JoinColumn(name="father_id", referencedColumnName="uniqid")},
   *     inverseJoinColumns={@ORM\JoinColumn(name="child_id", referencedColumnName="uniqid")}
   * )
   */
   protected $measuresLinked;
    /**
     * @var \MonarcFO\Model\Entity\Anr
     *  @ORM\Id
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;


    /**
     * @var \MonarcFO\Model\Entity\SoaCategory
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\SoaCategory", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="soacategory_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $category;

    /**
     * @var \MonarcFO\Model\Entity\Referential
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Referential", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="referential_uniqid", referencedColumnName="uniqid", nullable=true)
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
