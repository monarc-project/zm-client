<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits;

/**
 * @ORM\Table(name="users_anrs")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class UserAnr
{
    use Traits\CreateEntityTrait;
    use Traits\UpdateEntityTrait;

    public const FULL_PERMISSIONS_RWD = 1;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="User", inversedBy="userAnrs")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;

    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $anr;

    /**
     * @var int
     *
     * @ORM\Column(name="rwd", type="smallint", nullable=true, options={"unsigned":true, "default":1})
     */
    protected $rwd = self::FULL_PERMISSIONS_RWD;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $user->addUserAnr($this);

        return $this;
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;
        $anr->addUserAnrPermission($this);

        return $this;
    }

    public function getRwd(): int
    {
        return $this->rwd;
    }

    public function setRwd(int $rwd): self
    {
        $this->rwd = $rwd;

        return $this;
    }

    public function hasWriteAccess(): bool
    {
        return $this->rwd === self::FULL_PERMISSIONS_RWD;
    }
}
