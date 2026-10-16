<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\StringTrim;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

final class ScenarioAnalysisUpdateValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        $rules = [
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
                'name' => 'justification',
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => StringLength::class,
                    'options' => ['max' => 2000],
                ]],
            ],
        ];

        foreach ([
            'riskAnalysisContext' => 65535,
            'trendsSummary' => 65535,
            'managementOrganisation' => 65535,
        ] as $name => $max) {
            $rules[] = [
                'name' => $name,
                'required' => false,
                'allow_empty' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => StringLength::class,
                    'options' => ['max' => $max],
                ]],
            ];
        }

        return $rules;
    }
}
