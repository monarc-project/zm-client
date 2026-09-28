<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Scenario\Validator\ScenarioRiskScenarioUpdateValidator;
use Monarc\FrontOffice\Scenario\Validator\ScenarioRiskScenarioValidator;
use PHPUnit\Framework\TestCase;

final class ScenarioRiskScenarioValidatorTest extends TestCase
{
    public function testCreateAcceptsTypedReferenceUuids(): void
    {
        $validator = new ScenarioRiskScenarioValidator([], $this->createMock(InputValidationTranslator::class));

        self::assertTrue($validator->isValid([
            'title' => 'Supplier compromise',
            'riskSourceReferenceUuid' => 'a0b1c2d3-e4f5-6789-abcd-0123456789ab',
            'threatReferenceUuid' => 'b0b1c2d3-e4f5-6789-abcd-0123456789ab',
            'vulnerabilityReferenceUuid' => 'c0b1c2d3-e4f5-6789-abcd-0123456789ab',
        ]));
    }

    public function testCreateRejectsMalformedReferenceUuid(): void
    {
        $validator = new ScenarioRiskScenarioValidator([], $this->createMock(InputValidationTranslator::class));

        self::assertFalse($validator->isValid([
            'title' => 'Supplier compromise',
            'threatReferenceUuid' => 'not-a-uuid',
        ]));
    }

    public function testUpdateAllowsPartialWritesButRejectsBlankTitle(): void
    {
        $validator = new ScenarioRiskScenarioUpdateValidator([], $this->createMock(InputValidationTranslator::class));
        self::assertTrue($validator->isValid(['causeNarrative' => 'Unpatched remote access service.']));

        $invalid = new ScenarioRiskScenarioUpdateValidator([], $this->createMock(InputValidationTranslator::class));
        self::assertFalse($invalid->isValid(['title' => '   ']));
    }
}
