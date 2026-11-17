<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Record;

use Monarc\Core\Validator\InputValidator\AbstractInputValidator;
use Laminas\Filter\Callback as FilterCallback;
use Laminas\Validator\Callback;
use Laminas\Validator\Digits;
use Laminas\Validator\Explode;

class RecordDataInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        $idFilters = [
            [
                'name' => FilterCallback::class,
                'options' => [
                    'callback' => static fn ($value) => is_array($value) ? $value['id'] ?? null : $value,
                ],
            ],
            ['name' => 'ToInt'],
        ];

        $idsFilter = new FilterCallback(['callback' => static fn ($value) => is_array($value)
            ? array_values(array_filter(array_map(
                static fn ($item) => is_array($item) ? $item['id'] ?? null : $item,
                $value
            ), static fn ($id): bool => $id !== null && $id !== ''))
            : $value]);

        return [
            $this->getTextRule('label', self::MAX_STRING_LENGTH),
            $this->getTextRule('purposes'),
            $this->getTextRule('secMeasures'),
            array_replace(
                $this->getIntegerRule('controller', false, 1, self::MAX_INTEGER),
                ['filters' => $idFilters]
            ),
            array_replace(
                $this->getIntegerRule('representative', false, 1, self::MAX_INTEGER),
                ['filters' => $idFilters]
            ),
            array_replace(
                $this->getIntegerRule('dpo', false, 1, self::MAX_INTEGER),
                ['filters' => $idFilters]
            ),
            [
                'name' => 'jointControllers',
                'required' => false,
                'allow_empty' => true,
                'filters' => [$idsFilter],
                'validators' => [
                    [
                        'name' => Callback::class,
                        'options' => ['callback' => static fn ($value): bool => is_array($value)],
                        'break_chain_on_failure' => true
                    ],
                    ['name' => Explode::class, 'options' => ['validator' => new Digits()]],
                ]
            ],
            [
                'name' => 'personalData',
                'required' => false,
                'allow_empty' => true,
                'filters' => [$idsFilter],
                'validators' => [
                    [
                        'name' => Callback::class,
                        'options' => ['callback' => static fn ($value): bool => is_array($value)],
                        'break_chain_on_failure' => true
                    ],
                    ['name' => Explode::class, 'options' => ['validator' => new Digits()]],
                ]
            ],
            [
                'name' => 'recipients',
                'required' => false,
                'allow_empty' => true,
                'filters' => [$idsFilter],
                'validators' => [
                    [
                        'name' => Callback::class,
                        'options' => ['callback' => static fn ($value): bool => is_array($value)],
                        'break_chain_on_failure' => true
                    ],
                    ['name' => Explode::class, 'options' => ['validator' => new Digits()]],
                ]
            ],
            [
                'name' => 'internationalTransfers',
                'required' => false,
                'allow_empty' => true,
                'filters' => [$idsFilter],
                'validators' => [
                    [
                        'name' => Callback::class,
                        'options' => ['callback' => static fn ($value): bool => is_array($value)],
                        'break_chain_on_failure' => true
                    ],
                    ['name' => Explode::class, 'options' => ['validator' => new Digits()]],
                ]
            ],
            [
                'name' => 'processors',
                'required' => false,
                'allow_empty' => true,
                'filters' => [$idsFilter],
                'validators' => [
                    [
                        'name' => Callback::class,
                        'options' => ['callback' => static fn ($value): bool => is_array($value)],
                        'break_chain_on_failure' => true
                    ],
                    ['name' => Explode::class, 'options' => ['validator' => new Digits()]],
                ]
            ],
        ];
    }
}
