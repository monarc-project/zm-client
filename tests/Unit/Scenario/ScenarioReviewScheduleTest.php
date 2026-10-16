<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use DateTimeImmutable;
use DateTimeZone;
use Monarc\FrontOffice\Scenario\Service\ScenarioReviewSchedule;
use PHPUnit\Framework\TestCase;

final class ScenarioReviewScheduleTest extends TestCase
{
    public function testRequiresBothAuthoritativeStrategyValuesBeforeScheduling(): void
    {
        $schedule = (new ScenarioReviewSchedule())->calculate(null, 'Annually');

        self::assertSame('not_scheduled', $schedule['state']);
        self::assertNull($schedule['nextReviewAt']);
        self::assertStringContainsString('Context Establishment', (string) $schedule['guidance']);
    }

    public function testDerivesUpcomingDueAndOverdueFromTheSharedAnrStrategy(): void
    {
        $service = new ScenarioReviewSchedule();
        $timeZone = new DateTimeZone('UTC');

        self::assertSame('upcoming', $service->calculate(
            '2026-01-15',
            'Quarterly',
            new DateTimeImmutable('2026-04-14 12:00:00', $timeZone)
        )['state']);
        self::assertSame('due', $service->calculate(
            '2026-01-15',
            'Quarterly',
            new DateTimeImmutable('2026-04-15 12:00:00', $timeZone)
        )['state']);
        $overdue = $service->calculate(
            '2026-01-15',
            'Quarterly',
            new DateTimeImmutable('2026-04-16 12:00:00', $timeZone)
        );
        self::assertSame('overdue', $overdue['state']);
        self::assertSame('2026-04-15T00:00:00Z', $overdue['nextReviewAt']);
    }
}
