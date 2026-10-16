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
            'justification' => 'Neutral fixture justification.',
        ]);

        self::assertSame('critical', $result['band']);
        self::assertSame(
            ['confidentiality'],
            array_keys(array_filter($result['dimensionResults'], static fn (int $v): bool => $v === 5))
        );
        self::assertSame('Critical is based on likelihood 4 and the highest impact 5 in Confidentiality.', $result['explanation']);
        self::assertSame('treatment_required_before_acceptance', $result['threshold']);
    }

    public function testItUsesTheApprovedMediumAndLowBandsWithoutProbabilityMultiplication(): void
    {
        $calculator = new ScenarioQualitativeCalculationService();

        self::assertSame('medium', $calculator->calculate($this->profile(), [
            'likelihood' => 3,
            'dimensionSeverities' => ['availability' => 2],
            'justification' => 'Neutral fixture justification.',
        ])['band']);
        self::assertSame('low', $calculator->calculate($this->profile(), [
            'likelihood' => 1,
            'dimensionSeverities' => ['availability' => 5],
            'justification' => 'Neutral fixture justification.',
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
            'justification' => 'Neutral fixture justification.',
        ]);
    }

    public function testItUsesTheLockedQualitativeMatrixAndNeverMultipliesValues(): void
    {
        $profile = $this->profile();
        $profile['bands'] = [
            'matrix' => array_fill(1, 5, array_fill(1, 5, 'low')),
        ];
        $profile['bands']['matrix']['5']['5'] = 'critical';
        $profile['acceptanceRules'] = [
            'low' => 'justification_required_for_retain',
            'medium' => 'justification_required_for_retain',
            'high' => 'treatment_required_before_acceptance',
            'critical' => 'treatment_required_before_acceptance',
        ];

        $result = (new ScenarioQualitativeCalculationService())->calculate($profile, [
            'likelihood' => 5,
            'dimensionSeverities' => ['availability' => 5],
            'justification' => 'Matrix policy fixture.',
        ]);

        self::assertSame('critical', $result['band']);
        self::assertSame('treatment_required_before_acceptance', $result['threshold']);
    }

    public function testItRejectsAQualitativeMatrixThatDecreasesWithSeverity(): void
    {
        $profile = $this->profile();
        $profile['bands'] = ['matrix' => array_fill(1, 5, array_fill(1, 5, 'low'))];
        $profile['bands']['matrix'][1][1] = 'high';
        $profile['acceptanceRules'] = [
            'low' => 'justification_required_for_retain',
            'medium' => 'justification_required_for_retain',
            'high' => 'treatment_required_before_acceptance',
            'critical' => 'treatment_required_before_acceptance',
        ];

        $this->expectException(\InvalidArgumentException::class);
        (new ScenarioQualitativeCalculationService())->validateProfile($profile);
    }

    public function testItValidatesTheDraftPolicyWithoutRequiringTheLockCompletenessRules(): void
    {
        $policy = [
            'aggregationPolicy' => 'highest_consequence_dimension',
            'bands' => ['matrix' => array_fill(1, 5, array_fill(1, 5, 'low'))],
            'acceptanceRules' => [
                'low' => 'justification_required_for_retain',
                'medium' => 'justification_required_for_retain',
                'high' => 'treatment_required_before_acceptance',
                'critical' => 'treatment_required_before_acceptance',
            ],
        ];

        (new ScenarioQualitativeCalculationService())->validatePolicy($policy);
        self::addToAssertionCount(1);
    }

    public function testItRejectsOperationalThresholdsThatCannotFormAnOrderedRange(): void
    {
        $policy = [
            'aggregationPolicy' => 'highest_consequence_dimension',
            'bands' => [
                'matrix' => array_fill(1, 5, array_fill(1, 5, 'low')),
                'thresholds' => ['low' => 12, 'high' => 6],
            ],
            'acceptanceRules' => [
                'low' => 'justification_required_for_retain',
                'medium' => 'justification_required_for_retain',
                'high' => 'treatment_required_before_acceptance',
                'critical' => 'treatment_required_before_acceptance',
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        (new ScenarioQualitativeCalculationService())->validatePolicy($policy);
    }

    public function testItUsesCustomOrderedImpactValues(): void
    {
        $profile = [
            'likelihoodLabels' => [
                ['value' => 1, 'label' => 'Rare'],
                ['value' => 2, 'label' => 'Possible'],
                ['value' => 3, 'label' => 'Likely'],
            ],
            'consequenceLabels' => [
                ['value' => 0, 'label' => 'None'],
                ['value' => 7, 'label' => 'Material'],
                ['value' => 20, 'label' => 'Severe'],
            ],
            'dimensions' => ['availability'],
            'dimensionLevels' => ['availability' => [
                ['value' => 0, 'label' => 'None'],
                ['value' => 7, 'label' => 'Material'],
                ['value' => 20, 'label' => 'Severe'],
            ]],
            'aggregationPolicy' => 'highest_consequence_dimension',
            'bands' => ['matrix' => [
                '1' => ['0' => 'low', '7' => 'low', '20' => 'medium'],
                '2' => ['0' => 'low', '7' => 'medium', '20' => 'high'],
                '3' => ['0' => 'medium', '7' => 'high', '20' => 'critical'],
            ]],
            'acceptanceRules' => [
                'low' => 'justification_required_for_retain',
                'medium' => 'justification_required_for_retain',
                'high' => 'treatment_required_before_acceptance',
                'critical' => 'treatment_required_before_acceptance',
            ],
        ];

        $result = (new ScenarioQualitativeCalculationService())->calculate($profile, [
            'likelihood' => 3,
            'dimensionSeverities' => ['availability' => 20],
            'justification' => 'Custom operational scale values.',
        ]);

        self::assertSame('critical', $result['band']);
        self::assertSame(['likelihood' => 3, 'consequence' => 20], $result['score']);
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
            'dimensionLabels' => ['confidentiality' => 'Confidentiality', 'availability' => 'Availability'],
            'aggregationPolicy' => 'highest_consequence_dimension',
            'bands' => ['low' => 'fixture'],
            'acceptanceRules' => ['low' => 'fixture'],
        ];
    }
}
