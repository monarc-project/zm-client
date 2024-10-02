<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrAssetService;
use Monarc\FrontOffice\Table\AssetTable;

class AssetImportProcessor
{
    public function __construct(
        private AssetTable $assetTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrAssetService $anrAssetService,
        private InformationRiskImportProcessor $informationRiskImportProcessor
    ) {
    }

    public function processAssetsData(Entity\Anr $anr, array $assetsData): void
    {
        foreach ($assetsData as $assetData) {
            $this->processAssetData($anr, $assetData);
        }
    }

    public function processAssetData(Entity\Anr $anr, array $assetData): Entity\Asset
    {
        $asset = $this->getAssetFromCache($anr, $assetData['uuid']);
        if ($asset === null) {
            /* The code should be unique. */
            if ($this->importCacheHelper->isItemInArrayCache('assets_codes', $assetData['code'])) {
                $assetData['code'] .= '-' . time();
            }

            /* In the new data structure there is only "label" field set. */
            if (isset($assetData['label'])) {
                $assetData['label' . $anr->getLanguage()] = $assetData['label'];
            }
            if (isset($assetData['description'])) {
                $assetData['description' . $anr->getLanguage()] = $assetData['description'];
            }

            $asset = $this->anrAssetService->create($anr, $assetData, false);
            $this->importCacheHelper->addItemToArrayCache('assets_by_uuid', $asset, $asset->getUuid());
        }

        /* In case if the process is called from the object then process information risks data. */
        if (!empty($assetData['informationRisks'])) {
            $this->informationRiskImportProcessor->processInformationRisksData($anr, $assetData['informationRisks']);
        }

        return $asset;
    }

    public function getAssetFromCache(Entity\Anr $anr, string $uuid): ?Entity\Asset
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_assets_cache_loaded')) {
            $this->importCacheHelper->setArrayCacheValue('is_assets_cache_loaded', true);
            /** @var Entity\Asset $asset */
            foreach ($this->assetTable->findByAnr($anr) as $asset) {
                $this->importCacheHelper->addItemToArrayCache('assets_by_uuid', $asset, $asset->getUuid());
                $this->importCacheHelper->addItemToArrayCache('assets_codes', $asset->getCode(), $asset->getCode());
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('assets_by_uuid', $uuid);
    }
}
