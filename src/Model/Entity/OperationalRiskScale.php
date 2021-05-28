<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\OperationalRiskScaleSuperClass;

/**
 * @ORM\Table(name="operational_risks_scales", indexes={
 *      @ORM\Index(name="op_risk_scales_anr_id_indx", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class OperationalRiskScale extends OperationalRiskScaleSuperClass
{
}
