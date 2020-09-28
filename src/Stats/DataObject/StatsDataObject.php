<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\DataObject;

use DateTime;
use JsonSerializable;
use LogicException;

/**
 * Stats DTO object.
 */
class StatsDataObject implements JsonSerializable
{
    public const TYPE_RISK = 'risk';
    public const TYPE_THREAT = 'threat';
    public const TYPE_VULNERABILITY = 'vulnerability';
    public const TYPE_CARTOGRAPHY = 'cartography';
    public const TYPE_COMPLIANCE = 'compliance';

    /** @var string */
    private $anr;

    /** @var string */
    private $type;

    /** @var string */
    private $date = '';

    /** @var array */
    private $data;

    /** @var array */
    private $processedData = [];

    public function __construct(array $data)
    {
        $this->setAnr($data['anr'])
            ->setType($data['type'])
            ->setData($data['data']);

        if (!empty($data['date'])) {
            $this->setDate($data['date']);
        }

        $this->setProcessedData($data['processedData']);
    }

    public function getAnr(): string
    {
        return $this->anr;
    }

    public function setAnr(string $anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function setDate(string $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!\in_array($type, static::getAvailableTypes(), true)) {
            throw new LogicException(sprintf('Stats type %s is not supported!', $type));
        }

        $this->type = $type;

        return $this;
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_RISK,
            self::TYPE_THREAT,
            self::TYPE_VULNERABILITY,
            self::TYPE_CARTOGRAPHY,
            self::TYPE_COMPLIANCE,
        ];
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getProcessedData(): array
    {
        return $this->processedData;
    }

    public function setProcessedData(array $processedData): self
    {
        $this->processedData = $processedData;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'anr' => $this->anr,
            'type' => $this->type,
            'date' => $this->date,
            'data' => $this->data,
        ];
    }
}
