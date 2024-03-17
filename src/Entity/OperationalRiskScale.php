<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\OperationalRiskScaleSuperClass;

/**
 * @ORM\Table(name="operational_risks_scales", indexes={
 *      @ORM\Index(name="op_risk_scales_anr_id_indx", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class OperationalRiskScale extends OperationalRiskScaleSuperClass
{
}
