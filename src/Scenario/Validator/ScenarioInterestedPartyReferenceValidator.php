<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

use Laminas\Filter\ToInt;
use Laminas\Validator\Between;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

final class ScenarioInterestedPartyReferenceValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [[
            'name' => 'interestedPartyId',
            'required' => true,
            'filters' => [['name' => ToInt::class]],
            'validators' => [[
                'name' => Between::class,
                'options' => ['min' => 1, 'max' => PHP_INT_MAX],
            ]],
        ]];
    }
}
