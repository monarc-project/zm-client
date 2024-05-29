<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\ObjectCategorySuperClass;

/**
 * @ORM\Table(name="objects_categories", indexes={
 *      @ORM\Index(name="root_id", columns={"root_id"}),
 *      @ORM\Index(name="parent_id", columns={"parent_id"}),
 *      @ORM\Index(name="position", columns={"position"}),
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class ObjectCategory extends ObjectCategorySuperClass
{
    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    public function getImplicitPositionRelationsValues(): array
    {
        return array_merge(['anr' => $this->anr], parent::getImplicitPositionRelationsValues());
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;
        $anr->addObjectCategory($this);

        return $this;
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }
}
