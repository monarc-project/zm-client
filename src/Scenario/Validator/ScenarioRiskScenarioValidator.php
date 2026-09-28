<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\StringTrim;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

final class ScenarioRiskScenarioValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            [
                'name' => 'title',
                'required' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => StringLength::class,
                    'options' => ['min' => 1, 'max' => 255],
                ]],
            ],
            [
                'name' => 'provenance',
                'required' => false,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => StringLength::class,
                    'options' => ['max' => 255],
                ]],
            ],
            ...self::uuidRules(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function uuidRules(): array
    {
        return array_map(static fn (string $name): array => [
            'name' => $name,
            'required' => false,
            'allow_empty' => true,
            'filters' => [['name' => StringTrim::class]],
            'validators' => [[
                'name' => Regex::class,
                'options' => ['pattern' => '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/'],
            ]],
        ], [
            'sourceSnapshotUuid',
            'sourceInstanceRecordUuid',
        ]);
    }
}
