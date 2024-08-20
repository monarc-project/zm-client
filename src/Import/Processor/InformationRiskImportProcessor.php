<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrAmvService;
use Monarc\FrontOffice\Table\AmvTable;

class InformationRiskImportProcessor
{
    private array $maxPositionsPerAsset = [];

    public function __construct(
        private AmvTable $amvTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrAmvService $anrAmvService,
        private AssetImportProcessor $assetImportProcessor,
        private ThreatImportProcessor $threatImportProcessor,
        private VulnerabilityImportProcessor $vulnerabilityImportProcessor,
        private ReferentialImportProcessor $referentialImportProcessor
    ) {
    }

    public function processInformationRisksData(Entity\Anr $anr, array $informationRisksData): void
    {
        foreach ($informationRisksData as $informationRiskData) {
            $this->processInformationRiskData($anr, $informationRiskData);
        }
    }

    public function processInformationRiskData(Entity\Anr $anr, array $informationRiskData): Entity\Amv
    {
        $informationRisk = $this->getInformationRiskFromCache($anr, $informationRiskData['uuid']);
        if ($informationRisk === null) {
            $asset = $this->assetImportProcessor->processAssetData($anr, $informationRiskData['asset']);
            $threat = $this->threatImportProcessor->processThreatData($anr, $informationRiskData['threat']);
            $vulnerability = $this->vulnerabilityImportProcessor
                ->processVulnerabilityData($anr, $informationRiskData['vulnerability']);

            /* Prepare the max positions per asset as the objects are not saved in the DB. */
            if (!isset($this->maxPositionsPerAsset[$asset->getUuid()])) {
                $this->maxPositionsPerAsset[$asset->getUuid()] = $this->amvTable->findMaxPosition([
                    'anr' => $anr,
                    'asset' => [
                        'uuid' => $asset->getUuid(),
                        'anr' => $anr,
                    ],
                ]);
            }

            $informationRisk = $this->anrAmvService->createAmvFromPreparedData($anr, $asset, $threat, $vulnerability, [
                'uuid' => $informationRiskData['uuid'],
                'status' => $informationRiskData['status'],
                'setOnlyExactPosition' => true,
                'position' => ++$this->maxPositionsPerAsset[$asset->getUuid()],
            ], false, false);

            $this->importCacheHelper
                ->addItemToArrayCache('amvs_by_uuid', $informationRisk, $informationRisk->getUuid());
        }

        $saveInformationRisk = false;
        foreach ($informationRiskData['measures'] ?? [] as $measureData) {
            $measure = $this->referentialImportProcessor->getMeasureFromCache($anr, $measureData['uuid']);
            if ($measure === null && !empty($measureData['referential'])) {
                $referential = $this->referentialImportProcessor->processReferentialData(
                    $anr,
                    $measureData['referential']
                );
                $measure = $this->referentialImportProcessor->processMeasureData($anr, $referential, $measureData);
            }
            if ($measure !== null) {
                $informationRisk->addMeasure($measure);
                $saveInformationRisk = true;
            }
        }

        if ($saveInformationRisk) {
            $this->amvTable->save($informationRisk, false);
        }

        return $informationRisk;
    }

    private function getInformationRiskFromCache(Entity\Anr $anr, string $uuid): ?Entity\Amv
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_amvs_cache_loaded')) {
            $this->importCacheHelper->setArrayCacheValue('is_amvs_cache_loaded', true);
            /** @var Entity\Amv $amv */
            foreach ($this->amvTable->findByAnr($anr) as $amv) {
                $this->importCacheHelper->addItemToArrayCache('amvs_by_uuid', $amv, $amv->getUuid());
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('amvs_by_uuid', $uuid);
    }
}
