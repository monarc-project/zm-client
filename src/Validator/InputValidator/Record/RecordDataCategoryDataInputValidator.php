<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Record;

use Monarc\Core\Validator\InputValidator\AbstractInputValidator;

class RecordDataCategoryDataInputValidator extends AbstractInputValidator
{
    protected function getRules(): array
    {
        return [
            $this->getTextRule('label', self::MAX_STRING_LENGTH),
            $this->getIntegerRule('id', false, 1, self::MAX_INTEGER),
        ];
    }
}
