<?php
/**
* @link      https://github.com/monarc-project for the canonical source repository
* @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
* @license   MONARC is licensed under GNU Affero General Public License version 3
*/

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\SoaCategorySuperClass;

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
    * @var \Monarc\FrontOffice\Model\Entity\Anr
    *
    * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Anr", cascade={"persist"})
    * @ORM\JoinColumns({
    *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
    * })
    */
    protected $anr;
    /**
     * @var \Monarc\FrontOffice\Model\Entity\Referential
     *
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Referential", inversedBy="categories", cascade={"persist"})
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
