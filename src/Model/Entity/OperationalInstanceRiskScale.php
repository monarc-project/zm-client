<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\OperationalInstanceRiskScaleSuperClass;

/**
 * @ORM\Table(name="operational_instance_risks_scales", indexes={
 *      @ORM\Index(name="op_ins_risk_scales_anr_id_indx", columns={"anr_id"}),
 *      @ORM\Index(name="op_ins_risk_scales_instance_risk_op_id_indx", columns={"instance_risk_op_id"}),
 *      @ORM\Index(name="op_ins_risk_scales_op_risk_scale_id_indx", columns={"operational_risk_scale_id"})
 * })
 * @ORM\Entity
 */
class OperationalInstanceRiskScale extends OperationalInstanceRiskScaleSuperClass
{
}
