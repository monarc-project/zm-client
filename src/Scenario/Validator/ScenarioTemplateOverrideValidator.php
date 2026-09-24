<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\StringTrim;
use Laminas\Validator\InArray;
use Laminas\Validator\Regex;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

/** Validates one copy-on-write field on an instantiated template record. */
final class ScenarioTemplateOverrideValidator extends AbstractInputValidator
{
    private const FIELDS = [
        'title',
        'guidance',
        'linkedSubjects',
        'controls',
        'structure',
    ];

    protected function getRules(): array
    {
        return [
            [
                'name' => 'snapshotUuid',
                'required' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => Regex::class,
                    'options' => ['pattern' => '/^[a-f0-9-]{36}$/'],
                ]],
            ],
            [
                'name' => 'fieldName',
                'required' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => InArray::class,
                    'options' => ['haystack' => self::FIELDS, 'strict' => true],
                ]],
            ],
            ['name' => 'value', 'required' => true],
        ];
    }
}
