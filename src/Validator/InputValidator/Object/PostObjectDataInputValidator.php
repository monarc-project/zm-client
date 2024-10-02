<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Object;

use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Monarc\Core\Entity\ObjectSuperClass;
use Monarc\Core\Validator\InputValidator\Object\PostObjectDataInputValidator as CorePostObjectDataInputValidator;

class PostObjectDataInputValidator extends CorePostObjectDataInputValidator
{
    protected function getRules(): array
    {
        return [
            $this->getLabelRule($this->defaultLanguageIndex),
            $this->getNameRule($this->defaultLanguageIndex),
            [
                'name' => 'scope',
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                ],
                'validators' => [
                    [
                        'name' => InArray::class,
                        'options' => [
                            'haystack' => [ObjectSuperClass::SCOPE_LOCAL, ObjectSuperClass::SCOPE_GLOBAL],
                        ]
                    ]
                ],
            ],
            [
                'name' => 'asset',
                'required' => true,
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
                'name' => 'category',
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                ],
                'validators' => [],
            ],
            [
                'name' => 'rolfTag',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                ],
                'validators' => [],
            ],
        ];
    }
}
