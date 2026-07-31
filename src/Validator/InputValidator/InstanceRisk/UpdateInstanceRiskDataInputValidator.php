<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\InstanceRisk;

use Laminas\Filter\Callback;
use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\InputFilter\ArrayInput;
use Laminas\Validator\Date;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\InstanceRisk\UpdateInstanceRiskDataInputValidator
    as CoreUpdateInstanceRiskDataInputValidator;

class UpdateInstanceRiskDataInputValidator extends CoreUpdateInstanceRiskDataInputValidator
{
    protected function getRules(): array
    {
        return array_merge(parent::getRules(), [
            [
                'name' => 'owner',
                'required' => false,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'riskOwnerSupervisorId',
                'required' => false,
                'filters' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value) {
                                if ($value === null || $value === '') {
                                    return null;
                                }

                                return (int)$value;
                            },
                        ],
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'context',
                'required' => false,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'reductionAmount',
                'required' => false,
                'filters' => [
                    [
                        'name' => ToInt::class,
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'riskSourceId',
                'required' => false,
                'filters' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value) {
                                if ($value === null || $value === '') {
                                    return null;
                                }

                                return (int)$value;
                            },
                        ],
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'lastReviewDate',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
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
                        'name' => Date::class,
                        'options' => [
                            'format' => 'Y-m-d',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'nextReassessmentDate',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => Date::class,
                    'options' => ['format' => 'Y-m-d'],
                ]],
            ],
            [
                'name' => 'reassessmentTriggerIds',
                'required' => false,
                'allow_empty' => true,
                'type' => ArrayInput::class,
                'filters' => [],
                'validators' => [],
            ],
            [
                'name' => 'reviewFrequency',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
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
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'residualRiskDecision',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value): ?string {
                                $value = mb_strtolower(trim((string)$value));

                                return $value === '' ? null : $value;
                            },
                        ],
                    ],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => ['accepted', 'rejected', 'not_accepted'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'residualAcceptanceUseRiskOwner',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value): bool {
                                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                            },
                        ],
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'residualAcceptanceApproverSupervisorId',
                'required' => false,
                'filters' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value) {
                                if ($value === null || $value === '') {
                                    return null;
                                }

                                return (int)$value;
                            },
                        ],
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'residualRiskDecidedAt',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
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
                        'name' => Date::class,
                        'options' => [
                            'format' => 'Y-m-d',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'residualAcceptancePerformedByName',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
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
                'name' => 'residualAcceptancePerformedByEmail',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
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
                'name' => 'residualAcceptancePerformedOnBehalf',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value): bool {
                                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                            },
                        ],
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'residualRiskJustification',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
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
                'validators' => [],
            ],
        ]);
    }
}
