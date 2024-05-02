<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use JetBrains\PhpStorm\ArrayShape;
use Monarc\FrontOffice\Entity;

trait MeasureExportTrait
{
    #[ArrayShape([
        'uuid' => "string",
        'code' => "string",
        'label' => "string",
        'referential' => "array",
        'category' => "array|null"
    ])] private function prepareMeasureData(Entity\Measure $measure, int $languageIndex, bool $includeLinks = false): array
    {
        $result = [
            'uuid' => $measure->getUuid(),
            'code' => $measure->getCode(),
            'label' => $measure->getLabel($languageIndex),
            'referential' => [
                'uuid' => $measure->getReferential()->getUuid(),
                'label' => $measure->getReferential()->getLabel($languageIndex)
            ],
            'category' => $measure->getCategory() !== null ? [
                'id' => $measure->getCategory()->getId(),
                'status' => $measure->getCategory()->getStatus(),
                'label' => $measure->getCategory()->getLabel($languageIndex),
            ] : null,
        ];

        if ($includeLinks) {
            foreach ($measure->getLinkedMeasures() as $linkedMeasure) {
                $result['linkedMeasures'][$linkedMeasure->getUuid()] = $this->prepareMeasureData(
                    $linkedMeasure,
                    $languageIndex
                );
            }
        }

        return $result;
    }
}
