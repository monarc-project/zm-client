<?php
namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AmvSuperclass;

/**
 * Amv
 *
 * @ORM\Table(name="amvs", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="asset", columns={"asset_id"}),
 *      @ORM\Index(name="threat", columns={"threat_id"}),
 *      @ORM\Index(name="vulnerability", columns={"vulnerability_id"}),
 *      @ORM\Index(name="measure1", columns={"measure1_id"}),
 *      @ORM\Index(name="measure2", columns={"measure2_id"}),
 *      @ORM\Index(name="measure3", columns={"measure3_id"})
 * })
 * @ORM\Entity
 */
class Amv extends AmvSuperclass
{
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
     * @var \MonarcFO\Model\Entity\Asset
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Asset", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="asset_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $asset;

    /**
     * @var \MonarcFO\Model\Entity\Threat
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Threat", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="threat_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $threat;

    /**
     * @var \MonarcFO\Model\Entity\Vulnerability
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Vulnerability", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="vulnerability_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $vulnerability;

    /**
     * @var \MonarcFO\Model\Entity\Measure
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Measure", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="measure1_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $measure1;

    /**
     * @var \MonarcFO\Model\Entity\Measure
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Measure", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="measure2_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $measure2;

    /**
     * @var \MonarcFO\Model\Entity\Measure
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Measure", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="measure3_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $measure3;
}