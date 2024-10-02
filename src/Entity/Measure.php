<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\MeasureSuperClass;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="measures", indexes={
 *      @ORM\Index(name="uuid_anr_id", columns={"uuid", "anr_id"}),
 *      @ORM\Index(name="category", columns={"soacategory_id"}),
 *      @ORM\Index(name="referential", columns={"referential_uuid"})
 * })
 * @ORM\Entity
 */
class Measure extends MeasureSuperClass
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var UuidInterface|string
     *
     * @ORM\Column(name="uuid", type="uuid", nullable=false)
     */
    protected $uuid;

    /**
     * @var ArrayCollection|Amv[]
     *
     * @ORM\ManyToMany(targetEntity="Amv", inversedBy="measures", fetch="EAGER")
     * @ORM\JoinTable(name="measures_amvs",
     *   joinColumns={@ORM\JoinColumn(name="measure_id", referencedColumnName="id")},
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="amv_id", referencedColumnName="uuid"),
     *     @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id")
     *   },
     * )
     */
    protected $amvs;

    /**
     * @var ArrayCollection|RolfRisk[]
     *
     * @ORM\ManyToMany(targetEntity="RolfRisk", inversedBy="measures")
     * @ORM\JoinTable(name="measures_rolf_risks",
     *   inverseJoinColumns={@ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id")},
     *   joinColumns={@ORM\JoinColumn(name="measure_id", referencedColumnName="id")}
     * )
     */
    protected $rolfRisks;

    /**
     * @var Referential
     *
     * @ORM\ManyToOne(targetEntity="Referential")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="referential_uuid", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $referential;

    /**
     * @var ArrayCollection|Measure[]
     *
     * @ORM\ManyToMany(targetEntity="Measure", inversedBy="measures", cascade={"persist"})
     * @ORM\JoinTable(name="measures_measures",
     *   joinColumns={@ORM\JoinColumn(name="master_measure_id", referencedColumnName="id")},
     *   inverseJoinColumns={@ORM\JoinColumn(name="linked_measure_id", referencedColumnName="id")}
     * )
     */
    protected $linkedMeasures;

    /**
     * @var Soa|null
     *
     * @ORM\OneToOne(targetEntity="Soa", mappedBy="measure")
     */
    protected $soa;

    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({@ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)})
     */
    protected $anr;

    public function getId()
    {
        return $this->id;
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    public function getSoa()
    {
        return $this->soa;
    }

    public function setSoa(Soa $soa): self
    {
        $this->soa = $soa;
        $soa->setMeasure($this);

        return $this;
    }
}
