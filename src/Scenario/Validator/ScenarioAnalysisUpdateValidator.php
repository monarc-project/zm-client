<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\StringTrim;
use Laminas\Validator\Date;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

final class ScenarioAnalysisUpdateValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                'name' => 'lifecycle',
                'required' => false,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => ['draft', 'active', 'monitored', 'closed', 'archived'],
                    ],
                ]],
            ],
            [
                'name' => 'nextReviewAt',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => Date::class,
                    'options' => ['format' => \DateTimeInterface::ATOM],
                ]],
            ],
            [
                'name' => 'rationale',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => StringLength::class,
                    'options' => ['max' => 2000],
                ]],
            ],
        ];
    }
}
