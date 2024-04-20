<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Recommendation;

use Laminas\Filter\DateTimeFormatter;
use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Monarc\Core\Service\Interfaces\PositionUpdatableServiceInterface;
use Monarc\Core\Table\Interfaces\UniqueCodeTableInterface;
use Monarc\Core\Validator\FieldValidator\UniqueCode;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;
use Monarc\Core\Validator\InputValidator\FilterFieldsValidationTrait;
use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Entity\Recommendation;

/**
 * Note. For UniqueCode validator $excludeFilter/$includeFilter properties have to be set before calling isValid method.
 */
class PatchRecommendationDataInputValidator extends AbstractInputValidator
{
    use FilterFieldsValidationTrait;

    public function __construct(
        array $config,
        InputValidationTranslator $translator,
        private UniqueCodeTableInterface $recommendationTable
    ) {
        parent::__construct($config, $translator);
    }

    protected function getRules(): array
    {
        if (!empty($this->initialData['recommendationSet']) && !empty($this->includeFilter['anr'])) {
            $this->includeFilter['recommendationSet'] = [
                'uuid' => $this->initialData['recommendationSet'],
                'anr' => $this->includeFilter['anr'],
            ];
        }

        return [
            [
                'name' => 'recommendationSet',
                'required' => false,
                'filters' => [],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 36,
                            'max' => 36,
                        ]
                    ],
                ],
            ],
            [
                'name' => 'code',
                'required' => false,
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
                    [
                        'name' => UniqueCode::class,
                        'options' => [
                            'uniqueCodeValidationTable' => $this->recommendationTable,
                            'includeFilter' => $this->includeFilter,
                            'excludeFilter' => $this->excludeFilter,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'description',
                'required' => false,
                'filters' => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [],
            ],
            [
                'name' => 'comment',
                'required' => false,
                'filters' => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [],
            ],
            [
                'name' => 'duedate',
                'required' => false,
                'filters' => [
                    ['name' => DateTimeFormatter::class],
                ],
                'validators' => [],
            ],
            [
                'name' => 'importance',
                'required' => false,
                'filters' => [
                    ['name' => ToInt::class],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => array_keys(Recommendation::getImportances()),
                        ]
                    ],
                ],
            ],
            [
                'name' => 'implicitPosition',
                'required' => false,
                'filters' => [
                    ['name' => ToInt::class],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => [
                                PositionUpdatableServiceInterface::IMPLICIT_POSITION_START,
                                PositionUpdatableServiceInterface::IMPLICIT_POSITION_END,
                                PositionUpdatableServiceInterface::IMPLICIT_POSITION_AFTER,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'previous',
                'required' => false,
                'filters' => [],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 36,
                            'max' => 36,
                        ]
                    ],
                ],
            ],
            [
                'name' => 'responsable',
                'required' => false,
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
                'name' => 'status',
                'required' => false,
                'filters' => [
                    ['name' => ToInt::class],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => [0, 1],
                        ],
                    ],
                ],
            ],
        ];
    }
}
