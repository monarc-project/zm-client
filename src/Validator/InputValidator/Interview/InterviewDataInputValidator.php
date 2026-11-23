<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Interview;

use Laminas\Validator\StringLength;
use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

class InterviewDataInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        $rules = [];
        foreach (['date', 'service', 'content'] as $field) {
            $rules[] = [
                'name' => $field,
                'required' => true,
                'allow_empty' => true,
                'continue_if_empty' => true,
                'validators' => [[
                    'name' => StringLength::class,
                    'options' => ['min' => 1, 'max' => self::MAX_STRING_LENGTH],
                ]],
            ];
        }

        return $rules;
    }
}
