<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AnrMetadatasOnInstancesSuperClass;

/**
 * @ORM\Table(name="anr_metadatas_on_instances", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class AnrMetadatasOnInstances extends AnrMetadatasOnInstancesSuperClass
{
    /**
     * @var int
     *
     * @ORM\Column(name="is_deletable", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $isDeletable = 0;

    public function isDeletable(): bool
    {
        return (bool)$this->isDeletable;
    }

    public function setIsDeletable(bool $isDeletable): self
    {
        $this->isDeletable = (int)$isDeletable;

        return $this;
    }
}
