<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\FrontOffice\Scenario\Validator\ScenarioTreatmentValidator;
use PHPUnit\Framework\TestCase;

final class ScenarioTreatmentValidatorTest extends TestCase
{
    public function testCreateAcceptsTreatmentSpecificImportanceForImmutableRecommendationGuidance(): void
    {
        $payload = ScenarioTreatmentValidator::create([
            'treatmentType' => 'modify',
            'actionText' => 'Apply the approved hardening action.',
            'ownerId' => 9,
            'dueAt' => '2026-11-01T00:00:00Z',
            'status' => 'planned',
            'recommendationSelections' => [[
                'recommendationUuid' => '00000000-0000-4000-8000-000000000001',
                'treatmentImportance' => 3,
            ]],
        ]);

        self::assertSame(9, $payload['ownerId']);
        self::assertSame(3, $payload['recommendationSelections'][0]['treatmentImportance']);
    }

    public function testCreateRejectsDuplicateRecommendationSelections(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ScenarioTreatmentValidator::create([
            'treatmentType' => 'modify',
            'actionText' => 'Apply the approved hardening action.',
            'status' => 'planned',
            'recommendationSelections' => [
                ['recommendationUuid' => '00000000-0000-4000-8000-000000000001', 'treatmentImportance' => 1],
                ['recommendationUuid' => '00000000-0000-4000-8000-000000000001', 'treatmentImportance' => 3],
            ],
        ]);
    }

    public function testImplementationUpdateRejectsAnUnknownState(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ScenarioTreatmentValidator::implementationUpdate(['status' => 'accepted']);
    }

    public function testImplementationUpdateAllowsEvidenceAndNotApplicableRationaleForStateMachineValidation(): void
    {
        $payload = ScenarioTreatmentValidator::implementationUpdate([
            'status' => 'implemented',
            'completionEvidence' => 'Change reference CHG-001 has been verified.',
            'workingNote' => 'Implemented during the maintenance window.',
        ]);

        self::assertSame('implemented', $payload['status']);
        self::assertSame('Change reference CHG-001 has been verified.', $payload['completionEvidence']);
    }
}
