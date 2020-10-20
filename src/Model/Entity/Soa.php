<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AbstractEntity;

/**
 * Soa
 *
 * @ORM\Table(name="soa", indexes={
 *      @ORM\Index(name="measure", columns={"measure_id"}),
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class Soa extends AbstractEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var Measure
     * @ORM\OneToOne(targetEntity="Measure", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="measure_id", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true),
     * })
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

    public function getFiltersForService()
    {
        $filterJoin = [
            [
                'as' => 'm',
                'rel' => 'measure',
            ],
        ];
        $filterLeft = [
        ];
        $filtersCol = [
            'm.label1',
            'm.label2',
            'm.label3',
            'm.label4',
            'm.code',
            'remarks',
            'actions',
            'evidences'
        ];

        return [$filterJoin, $filterLeft, $filtersCol];
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
     *
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return Measure
     */
    public function getMeasure()
    {
        return $this->measure;
    }

    /**
     * @param Measure $measure
     *
     */
    public function setMeasure($measure)
    {
        $this->measure = $measure;
    }

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     *
     * @return Soa
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;

        return $this;
    }

    /**
     * @return string
     */
    public function getRemarks()
    {
        return $this->remarks;
    }

    /**
     * @param string $remarks
     *
     */
    public function setRemarks($remarks)
    {
        $this->remarks = $remarks;
    }

    /**
     * @return string
     */
    public function getEvidences()
    {
        return $this->evidences;
    }

    /**
     * @param string $evidences
     *
     */
    public function setEvidences($evidences)
    {
        $this->evidences = $evidences;
    }

    /**
     * @return string
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * @param string $actions
     *
     */
    public function setActions($actions)
    {
        $this->actions = $actions;
    }

    /**
     * @return integer
     */
    public function getCompliance()
    {
        return $this->compliance;
    }

    /**
     * @param integer $compliance
     *
     */
    public function setCompliance($compliance)
    {
        $this->compliance = $compliance;
    }

    /**
     * @return int
     */
    public function getEx()
    {
        return $this->EX;
    }

    /**
     * @param int $EX
     *
     */
    public function setEx($EX)
    {
        $this->EX = $EX;
    }

    /**
     * @return int
     */
    public function getLr()
    {
        return $this->LR;
    }

    /**
     * @param int $LR
     *
     */
    public function setLr($LR)
    {
        $this->LR = $LR;
    }

    /**
     * @return int
     */
    public function getCo()
    {
        return $this->CO;
    }

    /**
     * @param int $CO
     *
     */
    public function setCo($CO)
    {
        $this->CO = $CO;
    }

    /**
     * @return int
     */
    public function getBr()
    {
        return $this->BR;
    }

    /**
     * @param int $BR
     *
     */
    public function setBr($BR)
    {
        $this->BR = $BR;
    }

    /**
     * @return int
     */
    public function getBp()
    {
        return $this->BP;
    }

    /**
     * @param int $BP
     *
     */
    public function setBp($BP)
    {
        $this->BP = $BP;
    }

    /**
     * @return int
     */
    public function getRra()
    {
        return $this->RRA;
    }

    /**
     * @param int $RRA
     *
     */
    public function setRra($RRA)
    {
        $this->RRA = $RRA;
    }
}
