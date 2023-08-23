<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Monarc\Core\Model\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\Traits;

/**
 * @ORM\Table(name="snapshots")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Snapshot
{
    use Traits\CreateEntityTrait;
    use Traits\UpdateEntityTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Reference to the readonly analysis that contains the state of the snapshot.
     *
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * Reference to the original analysis, for which the snapshot is created,
     * and it is going to be replaced if the snapshot is restored.
     *
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_reference_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $anrReference;

    /**
     * @var string|null
     *
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    protected $comment;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     *
     * @return Snapshot
     */
    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    public function getAnrReference(): ?Anr
    {
        return $this->anrReference;
    }

    public function setAnrReference($anrReference): self
    {
        $this->anrReference = $anrReference;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
