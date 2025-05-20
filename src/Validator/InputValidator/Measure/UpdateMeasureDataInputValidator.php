<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2025 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Measure;

use Monarc\Core\Validator\InputValidator\Measure\UpdateMeasureDataInputValidator as CoreUpdateMeasureDataInputValidator;

class UpdateMeasureDataInputValidator extends CoreUpdateMeasureDataInputValidator
{
    protected function initIncludeFilter(): void
    {
        if (!empty($this->initialData['referentialUuid'])) {
            $this->includeFilter['referential'] = [
                'uuid' => $this->initialData['referentialUuid'],
                'anr' => $this->includeFilter['anr'],
            ];
        }
    }
}
