<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use JetBrains\PhpStorm\ArrayShape;
use Monarc\FrontOffice\Entity;

trait OperationalRiskExportTrait
{
    use MeasureExportTrait;

    #[ArrayShape([
        'id' => "int",
        'code' => "string",
        'label' => "string",
        'description' => "string",
        'rolfTags' => "array",
        'measures' => "array"
    ])] private function prepareOperationalRiskData(
        Entity\RolfRisk $rolfRisk,
        int $languageIndex,
        bool $withControls = true,
        bool $includeCompleteRelationData = true
    ): array {
        $measuresData = [];
        if ($withControls) {
            foreach ($rolfRisk->getMeasures() as $measure) {
                $measuresData[] = $this->prepareMeasureData(
                    $measure,
                    $languageIndex,
                    false,
                    $includeCompleteRelationData
                );
            }
        }
        $rolfTagsData = [];
        foreach ($rolfRisk->getTags() as $rolfTag) {
            $rolfTagsData[] = [
                'code' => $rolfTag->getCode(),
                'label' => $rolfTag->getLabel($languageIndex),
            ];
        }

        return [
            'id' => $rolfRisk->getId(),
            'code' => $rolfRisk->getCode(),
            'label' => $rolfRisk->getLabel($languageIndex),
            'description' => $rolfRisk->getDescription($languageIndex),
            'rolfTags' => $rolfTagsData,
            'measures' => $measuresData,
        ];
    }
}
