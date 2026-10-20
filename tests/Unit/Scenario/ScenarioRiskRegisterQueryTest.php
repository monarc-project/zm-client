<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\FrontOffice\Scenario\Validator\ScenarioRiskRegisterQuery;
use PHPUnit\Framework\TestCase;

final class ScenarioRiskRegisterQueryTest extends TestCase
{
    public function testProvidesStableReadOnlyRegisterDefaults(): void
    {
        $query = ScenarioRiskRegisterQuery::validate([]);

        self::assertSame(1, $query['page']);
        self::assertSame(25, $query['pageSize']);
        self::assertSame('updatedAt', $query['sort']);
        self::assertSame('desc', $query['direction']);
    }

    public function testAcceptsWhitelistedFilterValues(): void
    {
        $query = ScenarioRiskRegisterQuery::validate([
            'page' => '2',
            'pageSize' => '50',
            'band' => 'high',
            'treatmentState' => 'action_required',
            'reviewState' => 'active',
            'triggerOccurred' => 'true',
            'acceptanceState' => 'decision_required',
            'sort' => 'title',
            'direction' => 'asc',
        ]);

        self::assertSame(2, $query['page']);
        self::assertSame(50, $query['pageSize']);
        self::assertSame('high', $query['band']);
        self::assertTrue($query['triggerOccurred']);
    }

    /** @dataProvider invalidQueries */
    public function testRejectsUnsupportedOrUnsafeQueries(array $query): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ScenarioRiskRegisterQuery::validate($query);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidQueries(): array
    {
        return [
            'unknown field' => [['anrId' => '78']],
            'unbounded page size' => [['pageSize' => '101']],
            'unknown band' => [['band' => 'numeric-risk-score']],
            'invalid boolean' => [['triggerOccurred' => 'maybe']],
            'unapproved sort' => [['sort' => 'riskValue']],
        ];
    }
}
