<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\InstanceRisk;

use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Monarc\Core\Validator\InputValidator\InstanceRisk\UpdateInstanceRiskDataInputValidator
    as CoreUpdateInstanceRiskDataInputValidator;

class UpdateInstanceRiskDataInputValidator extends CoreUpdateInstanceRiskDataInputValidator
{
    protected function getRules(): array
    {
        return array_merge(parent::getRules(), [
            [
                'name' => 'owner',
                'required' => false,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'context',
                'required' => false,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [],
            ],
            [
                'name' => 'reductionAmount',
                'required' => false,
                'filters' => [
                    [
                        'name' => ToInt::class,
                    ],
                ],
                'validators' => [],
            ],
        ]);
    }
}
