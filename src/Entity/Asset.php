<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\AssetSuperClass;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="assets", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id", "code"}),
 *      @ORM\Index(name="anr_id2", columns={"anr_id"})
 * })
 * @ORM\Entity
 */
class Asset extends AssetSuperClass
{
    /**
     * @var UuidInterface|string
     *
     * @ORM\Column(name="uuid", type="uuid", nullable=false)
     * @ORM\Id
     */
    protected $uuid;

    /**
     * @var Anr
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Anr", fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
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