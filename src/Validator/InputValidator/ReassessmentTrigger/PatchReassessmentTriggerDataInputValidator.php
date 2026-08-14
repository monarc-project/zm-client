<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Validator\InputValidator\ReassessmentTrigger;

use Laminas\Filter\Callback;
use Laminas\Filter\StringTrim;
use Laminas\Validator\NumberComparison;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

class PatchReassessmentTriggerDataInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                'name' => 'triggerType',
                'required' => false,
                'allow_empty' => false,
                'filters' => [
                    ['name' => StringTrim::class]
                ],
                'validators' => [],
            ],
            [
                'name' => 'description',
                'required' => false,
                'allow_empty' => false,
                'filters' => [
                    ['name' => StringTrim::class]
                ],
                'validators' => [],
            ],
            [
                'name' => 'monitoringApproach',
                'required' => false,
                'allow_empty' => false,
                'filters' => [
                    ['name' => StringTrim::class]
                ],
                'validators' => [],
            ],
            [
                'name' => 'isActive',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static fn ($value): ?bool => $value === null || $value === ''
                                ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN)
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
                                ? null : (int)$value
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
