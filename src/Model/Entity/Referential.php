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
     * @var \Monarc\FrontOffice\Model\Entity\Anr
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Anr", )
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\Measure
     *
     * @ORM\OneToMany(targetEntity="Monarc\FrontOffice\Model\Entity\Measure", mappedBy="referential", cascade={"persist"})
     */
    protected $measures;

    /**
     * @var \Monarc\FrontOffice\Model\Entity\SoaCategory
     *
     * @ORM\OneToMany(targetEntity="Monarc\FrontOffice\Model\Entity\SoaCategory", mappedBy="referential", cascade={"persist"})
     */
    protected $categories;

    /**
    * @param int $anr
    * @return Referential
    */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }

    /**
     * @return Measure
     */
    public function getMeasures()
    {
        return $this->measures;
    }

    /**
     * @param \Monarc\FrontOffice\Model\Entity\Measure $measures
     * @return Referential
     */
    public function setMeasures($measures)
    {
        $this->measures = $measures;
        return $this;
    }

    /**
     * @return Category
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @param \Monarc\FrontOffice\Model\Entity\Category $categories
     * @return Referential
     */
    public function setCategories($categories)
    {
        $this->categories = $categories;
        return $this;
    }
}
