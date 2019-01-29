<?php
/**
* @link      https://github.com/monarc-project for the canonical source repository
* @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
* @license   MONARC is licensed under GNU Affero General Public License version 3
*/

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\SoaCategorySuperClass;

/**
* Category
*
* @ORM\Table(name="soacategory", indexes={
*      @ORM\Index(name="anr", columns={"anr_id"}),
*      @ORM\Index(name="referential", columns={"referential_uuid"})
* })
* @ORM\Entity
*/
class SoaCategory extends SoaCategorySuperClass
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
     * @var \MonarcFO\Model\Entity\Referential
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Referential", inversedBy="categories", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="referential_uuid", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $referential;

    /**
    * @return Anr
    */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
    * @param Anr $anr
    * @return Category
    */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }
}
