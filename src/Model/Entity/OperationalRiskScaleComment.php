<?php declare(strict_types=1);

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
