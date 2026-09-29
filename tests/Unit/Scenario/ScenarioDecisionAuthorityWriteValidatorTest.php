<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Tests\Unit\Scenario;

use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Scenario\Validator\ScenarioDecisionAuthorityWriteValidator;
use Monarc\FrontOffice\Table\UserTable;
use PHPUnit\Framework\TestCase;

final class ScenarioDecisionAuthorityWriteValidatorTest extends TestCase
{
    public function testAcceptsAnExistingPositiveLinkedUserAndRequiredActiveState(): void
    {
        $userTable = $this->createMock(UserTable::class);
        $userTable->expects(self::once())->method('findById')->with(9);
        $validator = new ScenarioDecisionAuthorityWriteValidator(
            [],
            $this->createMock(InputValidationTranslator::class),
            $userTable
        );

        self::assertTrue($validator->isValid(['linkedUserId' => 9, 'isActive' => true]));
    }

    public function testAcceptsAnExplicitInactiveState(): void
    {
        $userTable = $this->createMock(UserTable::class);
        $userTable->expects(self::once())->method('findById')->with(9);
        $validator = new ScenarioDecisionAuthorityWriteValidator(
            [],
            $this->createMock(InputValidationTranslator::class),
            $userTable
        );

        self::assertTrue($validator->isValid(['linkedUserId' => 9, 'isActive' => false]));
    }

    public function testRejectsAZeroLinkedUserIdentifier(): void
    {
        $userTable = $this->createMock(UserTable::class);
        $userTable->expects(self::never())->method('findById');
        $validator = new ScenarioDecisionAuthorityWriteValidator(
            [],
            $this->createMock(InputValidationTranslator::class),
            $userTable
        );

        self::assertFalse($validator->isValid(['linkedUserId' => 0, 'isActive' => true]));
    }
}
