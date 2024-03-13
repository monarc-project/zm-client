<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Anr;

use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Monarc\Core\Table\ModelTable;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;
use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Validator\FieldValidator\IsModelActiveValidator;

class CreateAnrDataInputValidator extends AbstractInputValidator
{
    private ModelTable $modelTable;

    public function __construct(
        array $config,
        InputValidationTranslator $translator,
        ModelTable $modelTable
    ) {
        $this->modelTable = $modelTable;

        parent::__construct($config, $translator);
    }

    protected function getRules(): array
    {
        return [
            [
                'name' => 'label',
                'required' => true,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
                'validators' => [
                    // TODO: Add unique label validation.
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
                'required' => false,
                'filters' => [
                    [
                        'name' => StringTrim::class,
                    ],
                ],
            ],
            [
                'name' => 'model',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    ['name' => ToInt::class],
                ],
                'validators' => [
                    [
                        'name' => IsModelActiveValidator::class,
                        'options' => [
                            'modelTable' => $this->modelTable,
                            'languageIndex' => $this->defaultLanguageIndex,
                        ],
                    ]
                ],
            ],
            [
                'name' => 'language',
                'required' => true,
                'allow_empty' => false,
                'filters' => [
                    ['name' => ToInt::class],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => $this->systemLanguageIndexes,
                        ]
                    ]
                ],
            ],
            [
                'name' => 'referentials',
                'required' => false,
                'filters' => [],
                'validators' => [],
            ]
        ];
    }
}
