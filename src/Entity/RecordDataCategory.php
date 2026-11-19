<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * RecordDataCategory
 */
#[ORM\Table(name: 'record_data_categories')]
#[ORM\Index(name: 'anr', columns: ['anr_id'])]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class RecordDataCategory
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    #[ORM\ManyToOne(targetEntity: 'Anr')]
    #[ORM\JoinColumn(name: 'anr_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    protected Anr $anr;

    #[ORM\Column(name: 'label', type: 'string', length: 255, nullable: true)]
    protected ?string $label = null;

    /**
     * @return int
     */


    public function getId(): int
    {
        return $this->id;
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }
}
