<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\FrontOffice\Entity;

trait MeasureExportTrait
{
    private function prepareMeasureData(
        Entity\Measure $measure,
        int $languageIndex,
        bool $includeLinks = false,
        bool $includeCompleteData = true
    ): array {
        if (!$includeCompleteData) {
            return ['uuid' => $measure->getUuid()];
        }

        $result = [
            'uuid' => $measure->getUuid(),
            'code' => $measure->getCode(),
            'label' => $measure->getLabel($languageIndex),
            'referential' => [
                'uuid' => $measure->getReferential()->getUuid(),
                'label' => $measure->getReferential()->getLabel($languageIndex)
            ],
            'category' => $measure->getCategory() === null ? null : [
                'label' => $measure->getCategory()->getLabel($languageIndex),
            ],
        ];

        if ($includeLinks) {
            foreach ($measure->getLinkedMeasures() as $linkedMeasure) {
                $result['linkedMeasures'][] = [
                    'uuid' => $linkedMeasure->getUuid(),
                    'referential' => ['uuid' => $linkedMeasure->getReferential()->getUuid()],
                ];
            }
        }

        return $result;
    }
}
