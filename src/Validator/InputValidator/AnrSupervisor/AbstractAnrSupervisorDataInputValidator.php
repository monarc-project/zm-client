<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\AnrSupervisor;

use Laminas\Filter\Boolean;
use Laminas\Filter\Callback;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\ArrayInput;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;
use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
use Monarc\FrontOffice\Table\UserTable;
use Monarc\FrontOffice\Validator\FieldValidator\LinkedUserExistenceValidator;

abstract class AbstractAnrSupervisorDataInputValidator extends AbstractInputValidator
{
    public function __construct(
        array $config,
        InputValidationTranslator $translator,
        protected UserTable $userTable
    ) {
        parent::__construct($config, $translator);
    }

    abstract protected function isNameRequired(): bool;

    protected function getRules(): array
    {
        return [
            [
                'name' => 'name',
                'required' => $this->isNameRequired(),
                'allow_empty' => false,
                'filters' => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 1,
                            'max' => 255,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'email',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    ['name' => StringTrim::class],
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value): ?string {
                                $value = trim((string)$value);

                                return $value === '' ? null : mb_strtolower($value);
                            },
                        ],
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'max' => 255,
                        ],
                    ],
                    [
                        'name' => EmailAddress::class,
                    ],
                ],
            ],
            [
                'name' => 'rolePosition',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    ['name' => StringTrim::class],
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value): ?string {
                                $value = trim((string)$value);

                                return $value === '' ? null : $value;
                            },
                        ],
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'max' => 255,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'linkedUserId',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value): ?int {
                                if ($value === null || $value === '') {
                                    return null;
                                }

                                return (int)$value;
                            },
                        ],
                    ],
                ],
                'validators' => [
                    [
                        'name' => LinkedUserExistenceValidator::class,
                        'options' => [
                            'userTable' => $this->userTable,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'isActive',
                'required' => false,
                'filters' => [
                    ['name' => Boolean::class],
                ],
                'validators' => [],
            ],
            [
                'name' => 'roles',
                'required' => true,
                'allow_empty' => false,
                'type' => ArrayInput::class,
                'filters' => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => AnrSupervisorRole::getAvailableRoles(),
                        ],
                    ],
                ],
            ],
        ];
    }
}
