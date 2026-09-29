<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\Boolean;
use Laminas\Filter\ToInt;
use Laminas\Validator\Between;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;
use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Table\UserTable;
use Monarc\FrontOffice\Validator\FieldValidator\LinkedUserExistenceValidator;

/** Validates the narrow Scenario request used to configure a residual-risk approver. */
final class ScenarioDecisionAuthorityWriteValidator extends AbstractInputValidator
{
    public function __construct(
        array $config,
        InputValidationTranslator $translator,
        private UserTable $userTable
    ) {
        parent::__construct($config, $translator);
    }

    protected function getRules(): array
    {
        return [
            [
                'name' => 'linkedUserId',
                'required' => true,
                'filters' => [['name' => ToInt::class]],
                'validators' => [
                    [
                        'name' => Between::class,
                        'options' => ['min' => 1, 'max' => PHP_INT_MAX],
                    ],
                    [
                        'name' => LinkedUserExistenceValidator::class,
                        'options' => ['userTable' => $this->userTable],
                    ],
                ],
            ],
            [
                'name' => 'isActive',
                'required' => true,
                // `false` is a deliberate state when a linked approver is deactivated.
                'allow_empty' => true,
                'filters' => [['name' => Boolean::class]],
                'validators' => [],
            ],
        ];
    }
}
