<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\Validator\Between;
use Laminas\Validator\Date;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

final class ScenarioAnalysisCreateValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                'name' => 'languageCode',
                'required' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => Regex::class,
                    'options' => ['pattern' => '/^[a-z]{2,3}(?:-[A-Z]{2})?$/'],
                ]],
            ],
            [
                'name' => 'nextReviewAt',
                'required' => false,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => Date::class,
                    'options' => ['format' => \DateTimeInterface::ATOM],
                ]],
            ],
            [
                'name' => 'sourceAnrId',
                'required' => false,
                'filters' => [['name' => ToInt::class]],
                'validators' => [[
                    'name' => Between::class,
                    'options' => ['min' => 1, 'max' => PHP_INT_MAX],
                ]],
            ],
        ];
    }
}
