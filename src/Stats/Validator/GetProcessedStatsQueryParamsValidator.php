<?php

namespace Monarc\FrontOffice\Stats\Validator;

use Laminas\Filter\StringTrim;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Digits;
use Laminas\Validator\InArray;
use Monarc\FrontOffice\Stats\DataObject\StatsDataObject;
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
                'name' => 'type',
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
                            'haystack' => StatsDataObject::getAvailableTypes(),
                            'messageTemplates' => [
                                InArray::NOT_IN_ARRAY => 'Should be one of the values: '
                                    . implode(', ', StatsDataObject::getAvailableTypes()),
                            ],
                        ],
                    ]
                ],
            ],
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
                            'haystack' => StatsAnrService::AVAILABLE_STATS_PROCESSORS,
                            'messageTemplates' => [
                                InArray::NOT_IN_ARRAY => 'Should be one of the values: '
                                    . implode(', ', StatsAnrService::AVAILABLE_STATS_PROCESSORS),
                            ],
                        ],
                    ]
                ],
            ],
            [
                'name' => 'processor_params',
                'required' => false,
                // 'filters' => [
                //     [
                //         'name' => StringTrim::class,
                //     ],
                // ],
                // 'validators' => [
                //     [
                //         'name' => InArray::class,
                //         // 'options' => [
                //         //     'haystack' => [
                //         //         'firstDimension' => ['processor_params'],
                //         //         'secondDimension' => ['risks_type', 'risks_state'],
                //         //     ],
                //         // ],
                //     ]
                // ],
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
