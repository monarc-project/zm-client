<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use JetBrains\PhpStorm\ArrayShape;
use Monarc\FrontOffice\Entity;

trait InformationRiskExportTrait
{
    use MeasureExportTrait;
    use AssetExportTrait;
    use ThreatExportTrait;
    use VulnerabilityExportTrait;

    #[ArrayShape([
        'uuid' => "string",
        'asset' => "array",
        'threat' => "array",
        'vulnerability' => "array",
        'measures' => "array",
        'status' => "int"
    ])] private function prepareInformationRiskData(
        Entity\Amv $amv,
        bool $withEval = false,
        bool $withControls = true,
        bool $includeCompleteRelationData = true
    ): array {
        /** @var Entity\Asset $asset */
        $asset = $amv->getAsset();
        /** @var Entity\Threat $threat */
        $threat = $amv->getThreat();
        /** @var Entity\Vulnerability $vulnerability */
        $vulnerability = $amv->getVulnerability();
        $languageIndex = $amv->getAnr()->getLanguage();

        $measuresData = [];
        if ($withControls) {
            foreach ($amv->getMeasures() as $measure) {
                $measuresData[] = $this->prepareMeasureData(
                    $measure,
                    $languageIndex,
                    false,
                    $includeCompleteRelationData
                );
            }
        }

        return [
            'uuid' => $amv->getUuid(),
            'asset' => $this->prepareAssetData($asset, $languageIndex, $includeCompleteRelationData),
            'threat' => $this->prepareThreatData($threat, $languageIndex, $withEval, $includeCompleteRelationData),
            'vulnerability' => $this
                ->prepareVulnerabilityData($vulnerability, $languageIndex, $includeCompleteRelationData),
            'measures' => $measuresData,
            'status' => $amv->getStatus(),
        ];
    }
}
