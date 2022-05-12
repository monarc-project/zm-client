<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\SoaScaleCommentSuperClass;

/**
 * @ORM\Table(name="soa_scale_comments", indexes={
 *      @ORM\Index(name="soa_scale_comments_anr_id_indx", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class SoaScaleComment extends SoaScaleCommentSuperClass
{
}
