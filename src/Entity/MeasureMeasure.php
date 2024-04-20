<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\MeasureMeasureSuperClass;

/**
 * @ORM\Table(name="measures_measures", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class MeasureMeasure extends MeasureMeasureSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({@ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)})
     */
    protected $anr;

    /**
     * @var Measure
     *
     * @ORM\ManyToOne(targetEntity="Measure", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="master_measure_id", referencedColumnName="uuid", nullable=false),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=false)
     * })
     */
    protected $masterMeasure;

    /**
     * @var Measure
     *
     * @ORM\ManyToOne(targetEntity="Measure", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="linked_measure_id", referencedColumnName="uuid", nullable=false),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=false)
     * })
     */
    protected $linkedMeasure;

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }
}
