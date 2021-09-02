<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\OperationalRiskScaleTypeSuperClass;

/**
 * @ORM\Table(name="operational_risks_scales_types")
 * @ORM\Table(name="operational_risks_scales_types", indexes={
 *     @ORM\Index(name="op_risk_scales_types_anr_id_indx", columns={"anr_id"}),
 *     @ORM\Index(name="op_risk_scales_types_scale_id_indx", columns={"scale_id"})
 * })
 * @ORM\Entity
 */
class OperationalRiskScaleType extends OperationalRiskScaleTypeSuperClass
{
}
