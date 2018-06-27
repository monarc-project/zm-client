<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\MeasureSuperClass;

/**
 * Measure
 *
 * @ORM\Table(name="measures", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class Measure extends MeasureSuperClass
{
    /**
     * @var \MonarcFO\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;



         /**
          * @var \MonarcFO\Model\Entity\Category
          *
          * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Category", cascade={"persist"})
          * @ORM\JoinColumns({
          *   @ORM\JoinColumn(name="category_id", referencedColumnName="id", nullable=true)
          * })
          */
         protected $category;




    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return Measure
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }



        /**
         * @return Category
         */
        public function getCategory()
        {
            return $this->category;
        }

        /**
         * @param Category $category
         */
        public function setCategory($category)
        {
            $this->category = $category;
        }


}
