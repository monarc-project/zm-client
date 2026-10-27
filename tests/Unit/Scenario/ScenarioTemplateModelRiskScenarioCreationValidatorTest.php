<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\FrontOffice\Scenario\Validator\ScenarioTemplateModelRiskScenarioCreationValidator;
use PHPUnit\Framework\TestCase;

/** @covers \Monarc\FrontOffice\Scenario\Validator\ScenarioTemplateModelRiskScenarioCreationValidator */
final class ScenarioTemplateModelRiskScenarioCreationValidatorTest extends TestCase
{
    public function testItAcceptsOnlySnapshotRecordSelectionAndLocalEditableFields(): void
    {
        $data = (new ScenarioTemplateModelRiskScenarioCreationValidator())->validate([
            'idempotencyKey' => 'submission-20261022',
            'sourceRevision' => 1,
            'models' => [[
                'modelRecordUuid' => '00000000-0000-4000-8000-000000000001',
                'sourceRevision' => 1,
                'title' => '  Local supplier interruption  ',
                'description' => '  Interview wording.  ',
                'eventRecordUuids' => [
                    '00000000-0000-4000-8000-000000000002',
                    '00000000-0000-4000-8000-000000000003',
                ],
            ]],
        ]);

        self::assertSame('Local supplier interruption', $data['models'][0]['title']);
        self::assertSame('Interview wording.', $data['models'][0]['description']);
    }

    public function testItRejectsBrowserSuppliedCatalogDataAndMixedSourceRevisions(): void
    {
        $validator = new ScenarioTemplateModelRiskScenarioCreationValidator();
        $this->expectException(\InvalidArgumentException::class);

        $validator->validate([
            'idempotencyKey' => 'submission-20261022',
            'sourceRevision' => 1,
            'models' => [[
                'modelRecordUuid' => '00000000-0000-4000-8000-000000000001',
                'sourceRevision' => 2,
                'title' => 'Local supplier interruption',
                'eventRecordUuids' => [],
                'catalogEvent' => ['stableUuid' => '00000000-0000-4000-8000-000000000002'],
            ]],
        ]);
    }
}
