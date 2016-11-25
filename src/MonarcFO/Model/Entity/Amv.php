<?php

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

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
class Amv extends \MonarcCore\Model\Entity\AmvSuperclass
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

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return Amv
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }

    /**
     * @return Asset
     */
    public function getAsset()
    {
        return $this->asset;
    }

    /**
     * @param Asset $asset
     * @return Amv
     */
    public function setAsset($asset)
    {
        $this->asset = $asset;
        return $this;
    }

    /**
     * @return Threat
     */
    public function getThreat()
    {
        return $this->threat;
    }

    /**
     * @param Threat $threat
     * @return Amv
     */
    public function setThreat($threat)
    {
        $this->threat = $threat;
        return $this;
    }

    /**
     * @return Vulnerability
     */
    public function getVulnerability()
    {
        return $this->vulnerability;
    }

    /**
     * @param Vulnerability $vulnerability
     * @return Amv
     */
    public function setVulnerability($vulnerability)
    {
        $this->vulnerability = $vulnerability;
        return $this;
    }

    /**
     * @return Measure
     */
    public function getMeasure1()
    {
        return $this->measure1;
    }

    /**
     * @param Measure $measure1
     * @return Amv
     */
    public function setMeasure1($measure1)
    {
        $this->measure1 = $measure1;
        return $this;
    }

    /**
     * @return Measure
     */
    public function getMeasure2()
    {
        return $this->measure2;
    }

    /**
     * @param Measure $measure2
     * @return Amv
     */
    public function setMeasure2($measure2)
    {
        $this->measure2 = $measure2;
        return $this;
    }

    /**
     * @return Measure
     */
    public function getMeasure3()
    {
        return $this->measure3;
    }

    /**
     * @param Measure $measure3
     * @return Amv
     */
    public function setMeasure3($measure3)
    {
        $this->measure3 = $measure3;
        return $this;
    }
}

