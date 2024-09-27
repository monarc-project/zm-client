<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="soa", indexes={
 *      @ORM\Index(name="measure", columns={"measure_id"}),
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class Soa
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
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $anr;

    /**
     * @var Measure
     *
     * @ORM\OneToOne(targetEntity="Measure")
     * @ORM\JoinColumns({@ORM\JoinColumn(name="measure_id", referencedColumnName="id", nullable=true)})
     */
    protected $measure;

    /**
     * @var string
     *
     * @ORM\Column(name="justification", type="text", nullable=true)
     */
    protected $remarks;

    /**
     * @var string
     *
     * @ORM\Column(name="evidences", type="text", nullable=true)
     */
    protected $evidences;

    /**
     * @var string
     *
     * @ORM\Column(name="actions", type="text", nullable=true)
     */
    protected $actions;

    /**
     * @var int
     *
     * @ORM\Column(name="compliance", type="integer", nullable=true)
     */
    protected $compliance;

    /**
     * @var int
     *
     * @ORM\Column(name="EX", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $EX = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="LR", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $LR = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="CO", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $CO = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="BR", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $BR = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="BP", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $BP = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="RRA", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $RRA = 0;

    /**
     * @var SoaScaleComment
     *
     * @ORM\ManyToOne(targetEntity="SoaScaleComment")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="soa_scale_comment_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $soaScaleComment;

    public function getId()
    {
        return $this->id;
    }

    public function getMeasure(): Measure
    {
        return $this->measure;
    }

    public function setMeasure(Measure $measure): self
    {
        $this->measure = $measure;

        return $this;
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

    public function getRemarks(): string
    {
        return (string)$this->remarks;
    }

    public function setRemarks(string $remarks): self
    {
        $this->remarks = $remarks;

        return $this;
    }

    public function getEvidences(): string
    {
        return (string)$this->evidences;
    }

    public function setEvidences(string $evidences): self
    {
        $this->evidences = $evidences;

        return $this;
    }

    public function getActions(): string
    {
        return (string)$this->actions;
    }

    public function setActions(string $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    public function getCompliance()
    {
        return $this->compliance;
    }

    public function setCompliance(int $compliance): self
    {
        $this->compliance = $compliance;

        return $this;
    }

    public function getEx(): int
    {
        return $this->EX;
    }

    public function setEx(int $EX): self
    {
        $this->EX = $EX;

        return $this;
    }

    public function getLr(): int
    {
        return $this->LR;
    }

    public function setLr(int $LR): self
    {
        $this->LR = $LR;

        return $this;
    }

    public function getCo(): int
    {
        return $this->CO;
    }

    public function setCo(int $CO): self
    {
        $this->CO = $CO;

        return $this;
    }

    public function getBr(): int
    {
        return $this->BR;
    }

    public function setBr(int $BR): self
    {
        $this->BR = $BR;

        return $this;
    }

    public function getBp(): int
    {
        return $this->BP;
    }

    public function setBp(int $BP): self
    {
        $this->BP = $BP;

        return $this;
    }

    public function getRra(): int
    {
        return $this->RRA;
    }

    public function setRra(int $RRA): self
    {
        $this->RRA = $RRA;

        return $this;
    }

    public function getSoaScaleComment(): ?SoaScaleComment
    {
        return $this->soaScaleComment;
    }

    public function setSoaScaleComment(SoaScaleComment $soaScaleComment): self
    {
        $this->soaScaleComment = $soaScaleComment;
        $soaScaleComment->addSoa($this);

        return $this;
    }
}
