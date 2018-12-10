<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\MeasureMeasureSuperClass;

/**
 * MeasureMeasure
 *
 * @ORM\Table(name="measures_measures", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class MeasureMeasure extends MeasureMeasureSuperClass
{

  /**
   * @var \MonarcCore\Model\Entity\Measure
   * @ORM\Id
   * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Measure", inversedBy="measuresLinked")
   * @ORM\JoinColumns({
   *   @ORM\JoinColumn(name="father_id", referencedColumnName="uniqid", nullable=true),
   * })
   */
  protected $father;

  /**
   * @var \MonarcCore\Model\Entity\Measure
   * @ORM\Id
   * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Measure", cascade={"persist"})
   * @ORM\JoinColumns({
   *   @ORM\JoinColumn(name="child_id", referencedColumnName="uniqid", nullable=true)
   * })
   */
  protected $child;
    /**
     * @var \MonarcFO\Model\Entity\Anr
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return MeasureMeasure
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }
}
