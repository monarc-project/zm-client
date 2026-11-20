<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Record;

use Monarc\Core\Validator\InputValidator\AbstractInputValidator;
use Laminas\Filter\Callback as FilterCallback;

class RecordProcessorDataInputValidator extends AbstractInputValidator
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

        return [
            $this->getTextRule('label', self::MAX_STRING_LENGTH),
            $this->getTextRule('contact', self::MAX_STRING_LENGTH),
            $this->getTextRule('activities'),
            $this->getTextRule('secMeasures'),
            array_replace(
                $this->getIntegerRule('representative', false, 1, self::MAX_INTEGER),
                ['filters' => $idFilters]
            ),
            array_replace(
                $this->getIntegerRule('dpo', false, 1, self::MAX_INTEGER),
                ['filters' => $idFilters]
            ),
        ];
    }
}
