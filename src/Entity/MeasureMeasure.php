<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\MeasureMeasureSuperClass;
use Ramsey\Uuid\Uuid;

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
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var Uuid|string
     *
     * @ORM\Id
     * @ORM\Column(name="father_id", type="uuid", nullable=true)
     */
    protected $father;

    /**
     * @var Uuid|string
     *
     * @ORM\Id
     * @ORM\Column(name="child_id", type="uuid", nullable=true)
     */
    protected $child;

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     */
    public function setAnr($anr): self
    {
        $this->anr = $anr;

        return $this;
    }
}
