<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Validator\InputValidator\RiskSource;

use Laminas\Filter\Callback;
use Laminas\Filter\StringTrim;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

class PostRiskSourceDataInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                'name' => 'label',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    ['name' => StringTrim::class]
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => ['min' => 1],
                    ]
                ],
            ],
            [
                'name' => 'isActive',
                'required' => false,
                'allow_empty' => true,
                'filters' => [[
                    'name' => Callback::class,
                    'options' => [
                        'callback' => static fn ($value):
                            bool => $value === null || $value === ''
                                ? true
                                : filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ],
                ]],
                'validators' => [],
            ],
        ];
    }
}
