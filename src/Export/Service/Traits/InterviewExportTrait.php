<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\FrontOffice\Entity;

trait InterviewExportTrait
{
    private function prepareInterviewData(Entity\Interview $interview): array
    {
        return [
            'id' => $interview->getId(),
            'date' => $interview->getDate(),
            'service' => $interview->getService(),
            'content' => $interview->getContent(),
        ];
    }
}
