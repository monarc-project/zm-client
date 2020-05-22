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

    /** @var string */
    private $anr;

    /** @var string */
    private $type;

    /** @var int */
    private $day;

    /** @var int */
    private $week;

    /** @var int */
    private $month;

    /** @var int */
    private $year;

    /** @var array */
    private $data;

    public function __construct(array $data)
    {
        $currentDateParams = $this->getCurrentDateParams();

        $this->setAnr($data['anr'] ?? '')
            ->setType($data['type'])
            ->setData($data['data'])
            ->setDay($data['day'] ?? $currentDateParams['day'])
            ->setWeek($data['week'] ?? $currentDateParams['week'])
            ->setMonth($data['month'] ?? $currentDateParams['month'])
            ->setYear($data['year'] ?? $currentDateParams['year']);
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

    public function getDay(): int
    {
        return $this->day;
    }

    public function setDay(int $day): self
    {
        $this->day = $day;

        return $this;
    }

    public function getWeek(): int
    {
        return $this->week;
    }

    public function setWeek(int $week): self
    {
        $this->week = $week;

        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

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

    private static function getAvailableTypes(): array
    {
        return [
            self::TYPE_RISK,
            self::TYPE_THREAT,
            self::TYPE_VULNERABILITY,
            self::TYPE_CARTOGRAPHY,
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

    public function jsonSerialize(): array
    {
        return [
            'anr' => $this->anr,
            'type' => $this->type,
            'day' => $this->day,
            'week' => $this->week,
            'month' => $this->month,
            'year' => $this->year,
            'data' => $this->data,
        ];
    }

    private function getCurrentDateParams(): array
    {
        $dateTime = new DateTime();

        return [
            'day' => (int)$dateTime->format('z') + 1,
            'week' => (int)$dateTime->format('W'),
            'month' => (int)$dateTime->format('m'),
            'year' => (int)$dateTime->format('Y'),
        ];
    }
}
