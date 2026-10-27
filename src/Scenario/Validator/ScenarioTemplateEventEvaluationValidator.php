<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\StringTrim;
use Laminas\Validator\InArray;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

/** Validates one local relevance decision for an immutable template event. */
final class ScenarioTemplateEventEvaluationValidator extends AbstractInputValidator
{
    private const STATES = ['not_evaluated', 'relevant', 'not_relevant', 'requires_follow_up'];

    protected function getRules(): array
    {
        return [
            [
                'name' => 'state',
                'required' => true,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => InArray::class,
                    'options' => ['haystack' => self::STATES, 'strict' => true],
                ]],
            ],
            [
                'name' => 'comment',
                'required' => false,
                'filters' => [['name' => StringTrim::class]],
                'validators' => [[
                    'name' => 'StringLength',
                    'options' => ['max' => 65535],
                ]],
            ],
        ];
    }
}
