<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Entity;

use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * @ORM\Table(name="stats_anrs")
 * @ORM\Entity
 */
class StatsAnr
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", cascade={"remove"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false)
     * })
     */
    protected $anr;

    /**
     * @var int
     *
     * @ORM\Column(name="day", type="smallint", nullable=false)
     */
    protected $day;

    /**
     * @var int
     *
     * @ORM\Column(name="week", type="smallint", nullable=false)
     */
    protected $week;

    /**
     * @var int
     *
     * @ORM\Column(name="month", type="smallint", nullable=false)
     */
    protected $month;

    /**
     * @var int
     *
     * @ORM\Column(name="year", type="smallint", nullable=false)
     */
    protected $year;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=50, nullable=false)
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(name="stats_data", type="json", nullable=false)
     */
    protected $statsData;

    public function __construct(array $data)
    {
        $this->anr = $data['anr'];
        $this->type = $data['type'];
        $this->statsData = $data['statsData'];
        $this->day = $data['day'] ?? date('z') + 1;
        $this->week = $data['week'] ?? (int)date('W');
        $this->month = $data['month'] ?? (int)date('m');
        $this->year = $data['year'] ?? (int)date('Y');
    }

    public function getAnr(): Anr
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): StatsAnr
    {
        $this->anr = $anr;

        return $this;
    }

    public function getDay(): int
    {
        return $this->day;
    }

    public function setDay(int $day): StatsAnr
    {
        $this->day = $day;

        return $this;
    }

    public function getWeek(): int
    {
        return $this->week;
    }

    public function setWeek(int $week): StatsAnr
    {
        $this->week = $week;

        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): StatsAnr
    {
        $this->month = $month;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): StatsAnr
    {
        $this->year = $year;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): StatsAnr
    {
        $this->type = $type;

        return $this;
    }

    public function getStatsData(): array
    {
        return json_decode($this->statsData, true);
    }

    public function setStatsData(array $statsData): StatsAnr
    {
        $this->statsData = $statsData;

        return $this;
    }
}
