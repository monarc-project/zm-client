<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\TranslationSuperClass;

/**
 * @ORM\Table(name="translations",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(name="type_key_lang_unq", columns={"anr_id", "type", "key", "lang"})
 *   },
,   indexes={
 *    @ORM\Index(name="type_key_indx", columns={"anr_id", "type", "key"})
 *  }
 * )
 * @ORM\Entity
 */
class Translation extends TranslationSuperClass
{
    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id")
     * })
     */
    protected $anr;

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }
}
