<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\FrontOffice\Scenario\Validator\ScenarioReviewValidator;
use PHPUnit\Framework\TestCase;

final class ScenarioReviewValidatorTest extends TestCase
{
    private const CASE_ID = '00000000-0000-4000-8000-000000000001';
    private const ASSESSMENT_ID = '00000000-0000-4000-8000-000000000002';
    private const TREATMENT_ID = '00000000-0000-4000-8000-000000000003';
    private const KEY = '00000000-0000-4000-8000-000000000004';

    public function testAcceptsAnExplicitScopedManualCaseAndScopeTransition(): void
    {
        $case = ScenarioReviewValidator::createCase([
            'reason' => 'manual',
            'analysisWide' => false,
            'riskScenarioUuids' => [self::CASE_ID],
            'idempotencyKey' => self::KEY,
        ]);
        $scope = ScenarioReviewValidator::transition('scope', [
            'analysisWide' => true,
            'riskScenarioUuids' => [],
            'idempotencyKey' => self::KEY,
        ]);

        self::assertSame('manual', $case['reason']);
        self::assertTrue($scope['analysisWide']);
    }

    public function testRequiresAttributedTreatmentEvidenceForTreatmentFollowUp(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ScenarioReviewValidator::transition('dispositions', [
            'idempotencyKey' => self::KEY,
            'dispositions' => [[
                'riskScenarioUuid' => self::CASE_ID,
                'riskScenarioRevision' => 2,
                'disposition' => 'treatment_follow_up',
                'rationale' => 'Follow the implementation plan.',
            ]],
        ]);
    }

    public function testRequiresAnExplicitOccurrenceSourceRatherThanInferringOneFromMonitoring(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ScenarioReviewValidator::triggerOccurrence([
            'triggerId' => 7,
            'analysisWide' => false,
            'riskScenarioUuids' => [self::CASE_ID],
            'occurredAt' => '2026-10-16T09:00:00Z',
            'triageRationale' => 'Reported change needs review.',
            'idempotencyKey' => self::KEY,
        ]);
    }

    public function testAcceptsAttributedTreatmentAndNewAssessmentEvidence(): void
    {
        $transition = ScenarioReviewValidator::transition('dispositions', [
            'idempotencyKey' => self::KEY,
            'dispositions' => [
                [
                    'riskScenarioUuid' => self::CASE_ID,
                    'riskScenarioRevision' => 2,
                    'disposition' => 'reassessed',
                    'reassessedAssessmentUuid' => self::ASSESSMENT_ID,
                ],
                [
                    'riskScenarioUuid' => self::ASSESSMENT_ID,
                    'riskScenarioRevision' => 3,
                    'disposition' => 'treatment_follow_up',
                    'rationale' => 'Implement the selected control.',
                    'treatmentUuids' => [self::TREATMENT_ID],
                ],
            ],
        ]);

        self::assertCount(2, $transition['dispositions']);
    }
}
