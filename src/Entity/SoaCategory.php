<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\SoaCategorySuperClass;

/**
* @ORM\Table(name="soacategory", indexes={
*      @ORM\Index(name="anr", columns={"anr_id"}),
*      @ORM\Index(name="referential", columns={"referential_uuid"})
* })
* @ORM\Entity
*/
class SoaCategory extends SoaCategorySuperClass
{
    /**
    * @var Anr
    *
    * @ORM\ManyToOne(targetEntity="Anr")
    * @ORM\JoinColumns({@ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)})
    */
    protected $anr;

    /**
     * @var Referential
     *
     * @ORM\ManyToOne(targetEntity="Referential", inversedBy="categories")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="referential_uuid", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $referential;

    public function getAnr()
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }
}
