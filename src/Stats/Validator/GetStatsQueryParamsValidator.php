<?php

namespace Monarc\FrontOffice\Stats\Validator;

use DateTime;
use Laminas\Filter\Boolean;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\ArrayInput;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Callback;
use Laminas\Validator\Date;
use Laminas\Validator\InArray;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Stats\DataObject\StatsDataObject;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Validator\FieldValidator\AnrExistenceValidator;
use Monarc\FrontOffice\Validator\InputValidator\AbstractMonarcInputValidator;

class GetStatsQueryParamsValidator extends AbstractMonarcInputValidator
{
    /** @var AnrTable */
    private $anrTable;

    public function __construct(InputFilter $inputFilter, AnrTable $anrTable)
    {
        $this->anrTable = $anrTable;

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
                'name' => 'anrs',
                'required' => false,
                'type' => ArrayInput::class,
                'validators' => [
                    [
                        'name' => AnrExistenceValidator::class,
                        'options' => [
                            'anrTable' => $this->anrTable,
                        ],
                    ]
                ],
            ],
            [
                'name' => 'postprocessor',
                'required' => false,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
            ],
            [
                'name' => 'dateFrom',
                'required' => false,
                'validators' => [
                    [
                        'name' => Date::class,
                    ],
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => [$this, 'validateDateFrom'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'dateTo',
                'required' => false,
                'validators' => [
                    [
                        'name' => Date::class,
                    ],
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => [$this, 'validateDateTo'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'aggregationPeriod',
                'required' => false,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => StatsAnrService::AVAILABLE_AGGREGATION_FIELDS,
                            'messageTemplates' => [
                                InArray::NOT_IN_ARRAY => 'Should be one of the values: '
                                    . implode(', ', StatsAnrService::AVAILABLE_AGGREGATION_FIELDS),
                            ],
                        ],
                    ]
                ],
            ],
            [
                'name' => 'getLast',
                'required' => false,
                'filters' => [
                    [
                        'name' => Boolean::class,
                    ],
                ],
            ],
        ];
    }

    public function validateDateFrom($value, array $context = []): bool
    {
        if (empty($value)) {
            return true;
        }

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

    public function validateDateTo($value, array $context = []): bool
    {
        if (empty($value)) {
            return true;
        }

        $currentDate = new DateTime();
        if ($value > $currentDate->format('Y-m-d')) {
            $this->inputFilter->getInputs()['dateTo']->setErrorMessage(
                '"dateTo" should be lower or equal to current date.'
            );

            return false;
        }

        if (!empty($context['dateFrom']) && $value < $context['dateFrom']) {
            $this->inputFilter->getInputs()['dateTo']->setErrorMessage(
                '"dateTo" should be bigger or equal to "dateFrom".'
            );

            return false;
        }

        return true;
    }
}
