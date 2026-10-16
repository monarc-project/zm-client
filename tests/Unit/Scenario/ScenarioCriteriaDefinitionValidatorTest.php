<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\FrontOffice\Scenario\Validator\ScenarioCriteriaDefinitionValidator;
use PHPUnit\Framework\TestCase;

final class ScenarioCriteriaDefinitionValidatorTest extends TestCase
{
    public function testItAcceptsOnlyTheTypedMutableDraftFields(): void
    {
        $uuid = '00000000-0000-4000-8000-000000000001';
        $level = static fn (int $value): array => [
            'uuid' => substr($uuid, 0, -1) . $value,
            'value' => $value,
            'label' => 'Level ' . $value,
            'description' => null,
            'enabled' => true,
        ];
        $levels = array_map($level, [0, 1, 2, 3, 4]);
        $data = [
            'title' => 'Operational-risk criteria',
            'aggregationPolicy' => 'highest_consequence_dimension',
            'bands' => ['matrix' => []],
            'acceptanceRules' => [],
            'likelihoodLevels' => $levels,
            'dimensions' => [[
                'uuid' => $uuid,
                'key' => 'availability',
                'label' => 'Availability',
                'enabled' => true,
                'levels' => $levels,
            ]],
        ];

        self::assertSame($data, (new ScenarioCriteriaDefinitionValidator())->updateDraft($data));
    }

    public function testItRejectsBrowserSuppliedSourceAndUnknownFields(): void
    {
        $validator = new ScenarioCriteriaDefinitionValidator();

        $this->expectException(\InvalidArgumentException::class);
        $validator->updateDraft(['source' => ['anrId' => 2]]);
    }

    public function testItAcceptsAnIndexedLikelihoodAndCustomOrderedImpactValues(): void
    {
        $level = static fn (string $uuid, int $value): array => [
            'uuid' => $uuid,
            'value' => $value,
            'label' => 'Comment ' . $value,
            'description' => null,
            'enabled' => true,
        ];
        $likelihood = [
            $level('00000000-0000-4000-8000-000000000001', 0),
            $level('00000000-0000-4000-8000-000000000002', 1),
            $level('00000000-0000-4000-8000-000000000003', 2),
        ];
        $impact = [
            $level('00000000-0000-4000-8000-000000000010', 0),
            $level('00000000-0000-4000-8000-000000000011', 7),
            $level('00000000-0000-4000-8000-000000000012', 20),
        ];
        $data = [
            'title' => 'Operational-risk criteria',
            'aggregationPolicy' => 'highest_consequence_dimension',
            'bands' => ['matrix' => []],
            'acceptanceRules' => [],
            'likelihoodLevels' => $likelihood,
            'dimensions' => [[
                'uuid' => '00000000-0000-4000-8000-000000000100',
                'key' => 'availability',
                'label' => 'Availability',
                'enabled' => true,
                'levels' => $impact,
            ]],
        ];

        self::assertSame($data, (new ScenarioCriteriaDefinitionValidator())->updateDraft($data));
    }
}
