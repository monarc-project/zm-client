<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\QuestionSuperClass;

#[ORM\Table(name: 'questions')]
#[ORM\Entity]
class Question extends QuestionSuperClass
{
    public const MODE_LOCALLY_CREATED = 1;

    /**
     * @var Anr
     */
    #[ORM\ManyToOne(targetEntity: 'Anr')]
    #[ORM\JoinColumn(name: 'anr_id', referencedColumnName: 'id', nullable: false)]
    protected Anr $anr;

    /**
     * @var string
     */
    #[ORM\Column(name: 'response', type: 'string', length: 255, nullable: true)]
    protected $response;

    /**
     * @var int
     */
    #[ORM\Column(name: 'mode', type: 'smallint', options: ['default' => 0])]
    protected $mode = 0;

    public function getImplicitPositionRelationsValues(): array
    {
        return ['anr' => $this->anr];
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     */

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    /**
     * @return string
     */

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse(?string $response): self
    {
        $this->response = $response;

        return $this;
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function setMode(int $mode): self
    {
        $this->mode = $mode;

        return $this;
    }
}
