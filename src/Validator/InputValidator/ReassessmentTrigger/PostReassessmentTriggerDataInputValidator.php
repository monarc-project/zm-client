<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Validator\InputValidator\ReassessmentTrigger;

use Laminas\Filter\Callback;
use Laminas\Filter\StringTrim;
use Laminas\Validator\NumberComparison;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

class PostReassessmentTriggerDataInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                'name' => 'triggerType',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    ['name' => StringTrim::class]
                ],
                'validators' => [],
            ],
            [
                'name' => 'description',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    ['name' => StringTrim::class]
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => ['min' => 1]
                    ]
                ],
            ],
            [
                'name' => 'monitoringApproach',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    ['name' => StringTrim::class]
                ],
                'validators' => [],
            ],
            [
                'name' => 'isActive', 'required' => false, 'allow_empty' => true,
                'filters' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static fn ($value): bool => $value === null || $value === ''
                                ? true
                                : filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ],
                    ]
                ],
                'validators' => [],
            ],
            [
                'name' => 'position',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static fn ($value): ?int => $value === null || $value === ''
                                ? null
                                : (int)$value
                        ],
                    ]
                ],
                'validators' => [
                    [
                        'name' => NumberComparison::class,
                        'options' => ['min' => 1, 'max' => PHP_INT_MAX]
                    ]
                ],
            ],
        ];
    }
}
