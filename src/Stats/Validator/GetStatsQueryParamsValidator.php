<?php

namespace Monarc\FrontOffice\Stats\Validator;

use DateTime;
use Laminas\InputFilter\ArrayInput;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Callback;
use Laminas\Validator\Date;
use Monarc\FrontOffice\Model\Table\AnrTable;
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
            ]
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
