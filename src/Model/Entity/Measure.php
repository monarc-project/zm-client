<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
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
     * @var Anr
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var ArrayCollection|Amv[]
     *
     * @ORM\ManyToMany(targetEntity="Amv", inversedBy="measures", fetch="EAGER")
     * @ORM\JoinTable(name="measures_amvs",
     *  joinColumns={@ORM\JoinColumn(name="measure_id", referencedColumnName="uuid"),
     *     @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     * },
     *  inverseJoinColumns={@ORM\JoinColumn(name="amv_id", referencedColumnName="uuid"),
     *     @ORM\JoinColumn(name="anr_id2", referencedColumnName="anr_id")
     * },
     * )
     */
    protected $amvs;

    /**
     * @var ArrayCollection|RolfRisk[]
     *
     * @ORM\ManyToMany(targetEntity="RolfRisk", inversedBy="measures", cascade={"persist"})
     * @ORM\JoinTable(name="measures_rolf_risks",
     *  inverseJoinColumns={@ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id")},
     *  joinColumns={@ORM\JoinColumn(name="measure_id", referencedColumnName="uuid"),
     *     @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     * },
     * )
     */
    protected $rolfRisks;

    /**
     * @var SoaCategory
     *
     * @ORM\ManyToOne(targetEntity="SoaCategory", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="soacategory_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $category;

    /**
     * @var Referential
     *
     * @ORM\ManyToOne(targetEntity="Referential", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="referential_uuid", referencedColumnName="uuid", nullable=true),
     *    @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $referential;

    /**
     * @var ArrayCollection|Measure[]
     *
     * @ORM\ManyToMany(targetEntity="Measure", inversedBy="measures", cascade={"persist"})
     * @ORM\JoinTable(name="measures_measures",
     *   joinColumns={@ORM\JoinColumn(name="child_id", referencedColumnName="uuid"),
     *      @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     *   },
     *   inverseJoinColumns={@ORM\JoinColumn(name="father_id", referencedColumnName="uuid"),
     *     @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     *   }
     * )
     */
    protected $measuresLinked;

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
}
