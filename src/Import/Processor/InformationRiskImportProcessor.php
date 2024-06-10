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
        $this->prepareInformationRisksUuids($anr);
        foreach ($informationRisksData as $informationRiskData) {
            $this->processInformationRiskData($anr, $informationRiskData);
        }
    }

    public function processInformationRiskData(Entity\Anr $anr, array $informationRiskData): Entity\Amv
    {
        $informationRisk = $this->getInformationRiskFromCache($informationRiskData['uuid']);
        if ($informationRisk !== null) {
            return $informationRisk;
        }

        $asset = $this->assetImportProcessor->getAssetFromCache(
            $informationRiskData['asset']['uuid'] ?? $informationRiskData['asset']
        );
        $threat = $this->threatImportProcessor
            ->getThreatFromCache($informationRiskData['threat']['uuid'] ?? $informationRiskData['threat']);
        $vulnerability = $this->vulnerabilityImportProcessor->getVulnerabilityFromCache(
            $informationRiskData['vulnerability']['uuid'] ?? $informationRiskData['vulnerability']
        );
        if ($asset === null || $threat === null || $vulnerability === null) {
            throw new \LogicException(
                'Assets, threats and vulnerabilities have to be imported before the information risks.'
            );
        }

        /* Prepare the max positions per asset as the objects are not saved in the DB to be able to determine on fly. */
        if (!isset($this->maxPositionsPerAsset[$asset->getUuid()])) {
            $this->maxPositionsPerAsset[$asset->getUuid()] = $this->amvTable->findMaxPosition([
                'anr' => $anr,
                'asset' => [
                    'uuid' => $asset->getUuid(),
                    'anr' => $anr,
                ],
            ]);
        }

        $amv = $this->anrAmvService->createAmvFromPreparedData($anr, $asset, $threat, $vulnerability, [
            'uuid' => $informationRiskData['uuid'],
            'status' => $informationRiskData['status'],
            'setOnlyExactPosition' => true,
            'position' => ++$this->maxPositionsPerAsset[$asset->getUuid()],
        ], false, false);
        foreach ($informationRiskData['measures'] as $measureData) {
            $measureUuid = $measureData['uuid'] ?? $measureData;
            $measure = $this->referentialImportProcessor->getMeasureFromCache($measureUuid);
            if ($measure !== null) {
                $amv->addMeasure($measure);
            }
        }
        $this->amvTable->save($amv, false);
        $this->importCacheHelper->addItemToArrayCache('amvs', $amv, $amv->getUuid());

        return $amv;
    }

    public function getInformationRiskFromCache(string $uuid): ?Entity\Amv
    {
        return $this->importCacheHelper->getItemFromArrayCache('amvs', $uuid);
    }

    public function prepareInformationRisksUuids(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('amvs')) {
            /** @var Entity\Amv $amv */
            foreach ($this->amvTable->findByAnr($anr) as $amv) {
                $this->importCacheHelper->addItemToArrayCache('amvs', $amv, $amv->getUuid());
            }
        }
    }
}
