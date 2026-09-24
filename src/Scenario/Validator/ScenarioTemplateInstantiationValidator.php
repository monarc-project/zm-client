<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\Validator\Between;
use Laminas\Validator\Regex;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

final class ScenarioTemplateInstantiationValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                'name' => 'templateUuid',
                'required' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => Regex::class,
                    'options' => ['pattern' => '/^[a-f0-9-]{36}$/'],
                ]],
            ],
            [
                'name' => 'templateVersion',
                'required' => true,
                'filters' => [['name' => ToInt::class]],
                'validators' => [[
                    'name' => Between::class,
                    'options' => ['min' => 1, 'max' => PHP_INT_MAX],
                ]],
            ],
        ];
    }
}
