<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\InstanceRiskOp;

use Laminas\Filter\StringTrim;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;

class PostSpecificInstanceRiskOpDataInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        $isSourceFromRisk = isset($this->initialData['source'])
            && (int)$this->initialData['source'] === AnrInstanceRiskOpService::CREATION_SOURCE_FROM_RISK;

        return [
            [
                'name' => 'source',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => [
                                AnrInstanceRiskOpService::CREATION_SOURCE_FROM_RISK,
                                AnrInstanceRiskOpService::CREATION_SOURCE_NEW_RISK,
                            ],
                        ]
                    ]
                ],
            ],
            [
                'name' => 'instance',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                ],
                'validators' => [],
            ],
            [
                'name' => 'risk',
                'required' => $isSourceFromRisk,
                'allow_empty' => !$isSourceFromRisk,
                'filters' => [
                    ['name' => 'ToInt'],
                ],
                'validators' => [],
            ],
            [
                'name' => 'code',
                'required' => !$isSourceFromRisk,
                'filters' => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 1,
                            'max' => 100,
                        ]
                    ],
                ],
            ],
            [
                'name' => 'label',
                'required' => !$isSourceFromRisk,
                'filters' => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 1,
                            'max' => 255,
                        ]
                    ],
                ],
            ],
            [
                'name' => 'description',
                'required' => !$isSourceFromRisk,
                'filters' => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                ],
            ],
        ];
    }
}
