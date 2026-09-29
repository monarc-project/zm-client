<?php declare(strict_types=1);

namespace Monarc\FrontOfficeTest\Unit\Scenario;

use Monarc\FrontOffice\Scenario\Service\ScenarioQualitativeCalculationService;
use PHPUnit\Framework\TestCase;

final class ScenarioQualitativeCalculationServiceTest extends TestCase
{
    public function testItExplainsTheControllingDimensionAndThreshold(): void
    {
        $result = (new ScenarioQualitativeCalculationService())->calculate($this->profile(), [
            'likelihood' => 4,
            'dimensionSeverities' => ['confidentiality' => 5, 'availability' => 2],
            'rationale' => 'Neutral fixture rationale.',
        ]);

        self::assertSame('critical', $result['band']);
        self::assertSame(
            ['confidentiality'],
            array_keys(array_filter($result['dimensionResults'], static fn (int $v): bool => $v === 5))
        );
        self::assertSame('treatment_required_before_acceptance', $result['threshold']);
    }

    public function testItUsesTheApprovedMediumAndLowBandsWithoutProbabilityMultiplication(): void
    {
        $calculator = new ScenarioQualitativeCalculationService();

        self::assertSame('medium', $calculator->calculate($this->profile(), [
            'likelihood' => 3,
            'dimensionSeverities' => ['availability' => 2],
            'rationale' => 'Neutral fixture rationale.',
        ])['band']);
        self::assertSame('low', $calculator->calculate($this->profile(), [
            'likelihood' => 1,
            'dimensionSeverities' => ['availability' => 5],
            'rationale' => 'Neutral fixture rationale.',
        ])['band']);
    }

    public function testItRejectsAnUnapprovedDimensionOrAggregationRule(): void
    {
        $calculator = new ScenarioQualitativeCalculationService();
        $profile = $this->profile();
        $profile['aggregationPolicy'] = 'weighted_score';

        $this->expectException(\InvalidArgumentException::class);
        $calculator->calculate($profile, [
            'likelihood' => 3,
            'dimensionSeverities' => ['unapproved' => 3],
            'rationale' => 'Neutral fixture rationale.',
        ]);
    }

    /** @return array<string, mixed> */
    private function profile(): array
    {
        $labels = [
            ['value' => 1, 'label' => 'One'],
            ['value' => 2, 'label' => 'Two'],
            ['value' => 3, 'label' => 'Three'],
            ['value' => 4, 'label' => 'Four'],
            ['value' => 5, 'label' => 'Five'],
        ];

        return [
            'identifier' => 'neutral-test-profile',
            'title' => 'Neutral test profile',
            'likelihoodLabels' => $labels,
            'consequenceLabels' => $labels,
            'dimensions' => ['confidentiality', 'availability'],
            'aggregationPolicy' => 'highest_consequence_dimension',
            'bands' => ['low' => 'fixture'],
            'acceptanceRules' => ['low' => 'fixture'],
        ];
    }
}
