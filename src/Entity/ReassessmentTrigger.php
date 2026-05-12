<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\ReassessmentTriggerSuperClass;

/**
 * @ORM\Table(
 *     name="anr_reassessment_triggers",
 *     indexes={
 *         @ORM\Index(name="anr_reassessment_triggers_anr_id_indx", columns={"anr_id"}),
 *         @ORM\Index(name="anr_reassessment_triggers_anr_id_trigger_type_indx", columns={"anr_id", "trigger_type"}),
 *         @ORM\Index(name="anr_reassessment_triggers_anr_id_position_indx", columns={"anr_id", "position"})
 *     }
 * )
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class ReassessmentTrigger extends ReassessmentTriggerSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
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

    public function getImplicitPositionRelationsValues(): array
    {
        return ['anr' => $this->anr];
    }
}
