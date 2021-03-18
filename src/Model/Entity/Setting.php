<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\Traits;

/**
 * @ORM\Table(name="settings", uniqueConstraints={@ORM\UniqueConstraint(name="name", columns={"name"})})
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Setting
{
    use Traits\CreateEntityTrait;
    use Traits\UpdateEntityTrait;

    public const SETTINGS_STATS = 'stats';
    public const SETTING_STATS_IS_SHARING_ENABLED = 'is_sharing_enabled';

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    protected $name;

    /**
     * @var array
     *
     * @ORM\Column(name="value", type="json", nullable=false)
     */
    protected $value;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): array
    {
        return $this->value;
    }

    public function setValue(array $value): Setting
    {
        $this->value = $value;

        return $this;
    }
}
