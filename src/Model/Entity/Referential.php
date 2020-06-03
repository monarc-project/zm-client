<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\ReferentialSuperClass;

/**
 * Referential
 *
 * @ORM\Table(name="referentials", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"}),
 * })
 * @ORM\Entity
 */
class Referential extends ReferentialSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var Measure[]
     *
     * @ORM\OneToMany(targetEntity="Measure", mappedBy="referential", cascade={"persist"})
     */
    protected $measures;

    /**
     * @var SoaCategory[]
     *
     * @ORM\OneToMany(targetEntity="SoaCategory", mappedBy="referential", cascade={"persist"})
     */
    protected $categories;

    /**
    * @param Anr $anr
    */
    public function setAnr($anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    /**
     * @return Measure[]
     */
    public function getMeasures()
    {
        return $this->measures;
    }

    /**
     * @param Measure[] $measures
     */
    public function setMeasures($measures): self
    {
        $this->measures = $measures;

        return $this;
    }

    /**
     * @return SoaCategory[]
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @param SoaCategory $categories
     */
    public function setCategories($categories): self
    {
        $this->categories = $categories;

        return $this;
    }
}
