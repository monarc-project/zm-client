<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\RiskSourceSuperClass;

/**
 * @ORM\Table(
 *     name="risk_sources",
 *     indexes={
 *         @ORM\Index(name="risk_sources_anr_id", columns={"anr_id"}),
 *         @ORM\Index(name="risk_sources_anr_id_is_active_indx", columns={"anr_id", "is_active"}),
 *         @ORM\Index(name="risk_sources_anr_id_label_indx", columns={"anr_id", "label"})
 *     }
 * )
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class RiskSource extends RiskSourceSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $anr;

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
