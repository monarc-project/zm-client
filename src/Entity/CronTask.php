<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="cron_tasks", indexes={
 *   @ORM\Index(name="name", columns={"name"}),
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class CronTask
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    public const NAME_INSTANCE_IMPORT = 'instance-import';

    public const STATUS_NEW = 0;
    public const STATUS_IN_PROGRESS = 1;
    public const STATUS_DONE = 2;
    public const STATUS_TERMINATED = 5;
    public const STATUS_FAILURE = 9;

    public const PRIORITY_LOW = 1;
    public const PRIORITY_HIGH = 9;

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
     * @ORM\Column(name="params", type="array", length=1024, nullable=false, options={"default":""})
     */
    protected $params = [];

    /**
     * @var int
     *
     * @ORM\Column(name="priority", type="smallint", nullable=false)
     */
    protected $priority;

    /**
     * @var int
     *
     * @ORM\Column(name="pid", type="integer", nullable=true)
     */
    protected $pid;

    /**
     * @var int
     *
     * @ORM\Column(name="status", type="smallint", nullable=false)
     */
    protected $status = self::STATUS_NEW;

    /**
     * @var string
     *
     * @ORM\Column(name="result_message", type="text", nullable=true)
     */
    protected $resultMessage;

    public function __construct(string $name, array $params, int $priority)
    {
        $this->setName($name)
            ->setParams($params)
            ->setPriority($priority);
    }

    public static function getAvailableNames(): array
    {
        return [self::NAME_INSTANCE_IMPORT];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        if (!\in_array($name, self::getAvailableNames(), true)) {
            throw new \LogicException(sprintf('The cron task name "%s" is not supported.', $name));
        }

        $this->name = $name;

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): self
    {
        $this->pid = $pid;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getResultMessage(): string
    {
        return $this->resultMessage;
    }

    public function setResultMessage(string $message): self
    {
        $this->resultMessage = $message;

        return $this;
    }
}
