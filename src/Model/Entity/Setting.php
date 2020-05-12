<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="settings", uniqueConstraints={@ORM\UniqueConstraint(name="name", columns={"name"})})
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Setting
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    public const SETTINGS_STATS = 'stats';
    public const SETTING_STATS_IS_SHARING_ENABLED = 'is_sharing_enabled';
    public const SETTING_STATS_API_KEY = 'api_key';

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
     * @var string
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
        return json_decode($this->value, true);
    }

    public function setValue(array $value): Setting
    {
        $this->value = json_encode($value);

        return $this;
    }
}
