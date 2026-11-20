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
use Laminas\Validator\Explode;

class RecordPersonalDataDataInputValidator extends AbstractInputValidator
{
    public function __construct(
        array $config,
        \Monarc\Core\Validator\InputValidator\InputValidationTranslator $translator,
        private RecordDataCategoryDataInputValidator $recordDataCategoryDataInputValidator
    ) {
        parent::__construct($config, $translator);
    }

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

        return [
            $this->getTextRule('dataSubject'),
            $this->getTextRule('description'),
            $this->getIntegerRule('retentionPeriod', false, 0, self::MAX_INTEGER),
            $this->getIntegerRule('retentionPeriodMode', false, 0, 2),
            $this->getTextRule('retentionPeriodDescription'),
            array_replace(
                $this->getIntegerRule('record', true, 1, self::MAX_INTEGER),
                ['filters' => $idFilters]
            ),
            [
                'name' => 'dataCategories',
                'required' => false,
                'allow_empty' => true,
                'filters' => [[
                    'name' => FilterCallback::class,
                    'options' => ['callback' => static fn ($value) => is_array($value)
                        ? array_map(
                            static fn ($category) => is_array($category) ? $category : ['id' => $category],
                            $value
                        )
                        : $value],
                ]],
                'validators' => [[
                    'name' => Callback::class,
                    'options' => ['callback' => static fn ($value): bool => is_array($value)],
                    'break_chain_on_failure' => true,
                ], [
                    'name' => Explode::class,
                    'options' => ['validator' => new Callback([
                        'callback' => fn ($category): bool => $this->recordDataCategoryDataInputValidator
                            ->isValid($category),
                    ])],
                ]]
            ],
        ];
    }
}
