<?php

namespace Monarc\FrontOffice\Stats\Validator;

use Laminas\Filter\StringTrim;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Digits;
use Laminas\Validator\InArray;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Validator\InputValidator\AbstractMonarcInputValidator;

class GetProcessedStatsQueryParamsValidator extends AbstractMonarcInputValidator
{
    public function __construct(InputFilter $inputFilter)
    {
        parent::__construct($inputFilter);
    }

    protected function getRules(): array
    {
        return [
            [
                'name' => 'processor',
                'required' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => StatsAnrService::AVAILABLE_PROCESSORS,
                            'messageTemplates' => [
                                InArray::NOT_IN_ARRAY => 'Should be one of the values: '
                                    . implode(', ', StatsAnrService::AVAILABLE_PROCESSORS),
                            ],
                        ],
                    ]
                ],
            ],
            [
                'name' => 'nbdays',
                'required' => false,
                'validators' => [
                    [
                        'name' => Digits::class
                    ]
                ],
            ],
        ];
    }
}
