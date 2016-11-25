<?php
namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Thme
 *
 * @ORM\Table(name="rolf_categories", indexes={
 *      @ORM\Index(name="anr", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class RolfCategory extends \MonarcCore\Model\Entity\RolfCategorySuperclass
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
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return RolfCategory
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }
}
