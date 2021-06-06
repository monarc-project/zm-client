<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\OperationalInstanceRiskScaleSuperClass;

/**
 * @ORM\Table(name="operational_instance_risks_scales", indexes={
 *      @ORM\Index(name="op_ins_risk_scales_anr_id_indx", columns={"anr_id"}),
 *      @ORM\Index(name="op_ins_risk_scales_op_ins_risk_id_indx", columns={"operational_instance_risk_id"}),
 *      @ORM\Index(name="op_ins_risk_scales_op_risk_scale_id_indx", columns={"operational_risk_scale_id"})
 * })
 * @ORM\Entity
 */
class OperationalInstanceRiskScale extends OperationalInstanceRiskScaleSuperClass
{
}
