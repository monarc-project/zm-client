<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Scenario\Validator\ScenarioAnalysisUpdateValidator;
use PHPUnit\Framework\TestCase;

final class ScenarioAnalysisUpdateValidatorTest extends TestCase
{
    public function testAcceptsTheAlignedScenarioContextTextFields(): void
    {
        $validator = new ScenarioAnalysisUpdateValidator([], $this->createMock(InputValidationTranslator::class));

        self::assertTrue($validator->isValid([
            'riskAnalysisContext' => 'Scope, purpose and risk-management approach.',
            'trendsSummary' => 'No material trends beyond the recorded questions.',
            'managementOrganisation' => 'The risk owner coordinates review and escalation.',
        ]));
    }

    public function testRejectsAnOversizedScenarioContextField(): void
    {
        $validator = new ScenarioAnalysisUpdateValidator([], $this->createMock(InputValidationTranslator::class));

        self::assertFalse($validator->isValid([
            'riskAnalysisContext' => str_repeat('x', 65_536),
        ]));
    }
}
