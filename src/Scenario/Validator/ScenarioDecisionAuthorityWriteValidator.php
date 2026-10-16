<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\Boolean;
use Laminas\Filter\Callback;
use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\Validator\Between;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\StringLength;
use Laminas\InputFilter\ArrayInput;
use Laminas\Validator\InArray;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;
use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
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
                'required' => false,
                'allow_empty' => true,
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
                'name' => 'name',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => StringLength::class,
                    'options' => ['max' => 255],
                ]],
            ],
            [
                'name' => 'email',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    ['name' => StringTrim::class],
                    ['name' => Callback::class, 'options' => [
                        'callback' => static function ($value): ?string {
                            $value = trim((string) $value);

                            return $value === '' ? null : mb_strtolower($value);
                        },
                    ]],
                ],
                'validators' => [[
                    'name' => EmailAddress::class,
                ]],
            ],
            [
                'name' => 'rolePosition',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => StringLength::class,
                    'options' => ['max' => 255],
                ]],
            ],
            [
                'name' => 'roles',
                'required' => false,
                'allow_empty' => true,
                'type' => ArrayInput::class,
                'validators' => [[
                    'name' => InArray::class,
                    'options' => ['haystack' => AnrSupervisorRole::getAvailableRoles()],
                ]],
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
