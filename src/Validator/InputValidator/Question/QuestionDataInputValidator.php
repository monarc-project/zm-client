<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Question;

use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

class QuestionDataInputValidator extends AbstractInputValidator
{
    protected array $defaultValues = ['label1' => null, 'label2' => null, 'label3' => null, 'label4' => null];

    public function validate(array $data, bool $partial = false): array
    {
        if (isset($data['response']) && is_int($data['response'])) {
            $data['response'] = (string)$data['response'];
        }

        return parent::validate($data, $partial);
    }

    protected function getRules(): array
    {
        $rules = [$this->getTextRule('response', self::MAX_STRING_LENGTH)];
        foreach ($this->systemLanguageIndexes as $index) {
            $rules[] = $this->getTextRule('label' . $index, self::MAX_STRING_LENGTH);
        }

        return $rules;
    }
}
