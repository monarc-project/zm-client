<?php

namespace Monarc\FrontOffice\Stats\Validator;

use DateTime;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\ArrayInput;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Callback;
use Laminas\Validator\Date;
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
                'type' => ArrayInput::class,
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
            [
                'name' => 'dateFrom',
                'required' => false,
                'validators' => [
                    [
                        'name' => Date::class,
                    ],
                ],
            ],
        ];
    }
    
    public function validateDateFrom($value, array $context = []): bool
    {
        // if (empty($value)) {
        //     return true;
        // }
        
        file_put_contents('php://stderr', print_r($value , TRUE).PHP_EOL);

        $currentDate = new DateTime();
        if ($value > $currentDate->format('Y-m-d')) {
            $this->inputFilter->getInputs()['dateFrom']->setErrorMessage(
                '"dateFrom" should be lower or equal to current date.'
            );

            return false;
        }

        if (!empty($context['dateTo']) && $value > $context['dateTo']) {
            $this->inputFilter->getInputs()['dateFrom']->setErrorMessage(
                '"dateFrom" should be lower or equal to "dateTo".'
            );

            return false;
        }

        return true;
    }
}
