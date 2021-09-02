<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\TranslationSuperClass;

/**
 * @ORM\Table(name="translations",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(name="anr_key_lang_unq", columns={"anr_id", "key", "lang"})
 *   },
 *   indexes={
 *    @ORM\Index(name="key_indx", columns={"key"}),
 *    @ORM\Index(name="anr_type_indx", columns={"anr", "type"})
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
}
