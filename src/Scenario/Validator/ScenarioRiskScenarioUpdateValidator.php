<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\StringTrim;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

/** Validates a partial, typed risk-story write. */
final class ScenarioRiskScenarioUpdateValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        $rules = [];
        $fields = ['title' => 255, 'provenance' => 255];
        foreach ($fields as $name => $max) {
            $rules[] = [
                'name' => $name,
                'required' => false,
                'allow_empty' => $name !== 'title',
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => StringLength::class,
                    'options' => array_filter(
                        ['min' => $name === 'title' ? 1 : null, 'max' => $max],
                        static fn ($value): bool => $value !== null
                    ),
                ]],
            ];
        }

        return [...$rules, ...ScenarioRiskScenarioValidator::uuidRules()];
    }
}
