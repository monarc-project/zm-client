<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\OperationalRiskScaleCommentSuperClass;

/**
 * @ORM\Table(name="operational_risks_scales_comments", indexes={
 *      @ORM\Index(name="op_risk_scales_comments_anr_id_indx", columns={"anr_id"}),
 *      @ORM\Index(name="op_risk_scales_comments_scale_id_indx", columns={"scale_id"})
 * })
 * @ORM\Entity
 */
class OperationalRiskScaleComment extends OperationalRiskScaleCommentSuperClass
{
}
