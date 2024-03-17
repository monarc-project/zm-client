<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\SoaScaleCommentSuperClass;

/**
 * @ORM\Table(name="soa_scale_comments", indexes={
 *      @ORM\Index(name="soa_scale_comments_anr_id_indx", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class SoaScaleComment extends SoaScaleCommentSuperClass
{
    /**
     * @var string
     *
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    protected $comment = '';

    /**
     * @var ArrayCollection|Soa[]
     *
     * @ORM\OneToMany(targetEntity="Soa", mappedBy="soaScaleComment")
     */
    protected $soas;

    public function __construct()
    {
        $this->soas = new ArrayCollection();
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getSoas()
    {
        return $this->soas;
    }

    public function addSoa(Soa $soa): self
    {
        if (!$this->soas->contains($soa)) {
            $this->soas->add($soa);
            $soa->setSoaScaleComment($this);
        }

        return $this;
    }
}
