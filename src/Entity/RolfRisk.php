<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\RolfRiskSuperClass;

/**
 * @ORM\Table(name="rolf_risks", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 *      @ORM\Index(name="anr_code", columns={"anr_id", "code"})
 * })
 * @ORM\Entity
 */
class RolfRisk extends RolfRiskSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var Measure[]|ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="Measure", mappedBy="rolfRisks")
     * @ORM\JoinTable(name="measures_rolf_risks",
     *  joinColumns={@ORM\JoinColumn(name="rolf_risk_id", referencedColumnName="id")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="measure_id", referencedColumnName="id")}
     * )
     */
    protected $measures;

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
