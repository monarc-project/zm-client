<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\ReferentialSuperClass;

/**
 * @ORM\Table(name="referentials", indexes={@ORM\Index(name="anr", columns={"anr_id"})})
 * @ORM\Entity
 */
class Referential extends ReferentialSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({@ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)})
     */
    protected $anr;

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;
        $anr->addReferential($this);

        return $this;
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }
}
