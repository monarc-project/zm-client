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
        private AnrAssetService $anrAssetService
    ) {
    }

    public function processAssetsData(Entity\Anr $anr, array $assetsData): void
    {
        $this->prepareAssetUuidsAndCodesCache($anr);
        foreach ($assetsData as $assetData) {
            $this->processAssetData($anr, $assetData);
        }
    }

    public function processAssetData(Entity\Anr $anr, array $assetData): Entity\Asset
    {
        $asset = $this->getAssetFromCacheOrDb($anr, $assetData['uuid']);
        if ($asset !== null) {
            return $asset;
        }

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
        $this->importCacheHelper->addItemToArrayCache('assets', $asset, $asset->getUuid());

        return $asset;
    }

    public function getAssetFromCacheOrDb(Entity\Anr $anr, string $uuid): ?Entity\Asset
    {
        $asset = $this->importCacheHelper->getItemFromArrayCache('assets', $uuid);
        /* The current anr asserts' UUIDs are preloaded, so can be validated first. */
        if ($asset === null && $this->importCacheHelper->isItemInArrayCache('assets_uuids', $uuid)) {
            /** @var ?Entity\Asset $asset */
            $asset = $this->assetTable->findByUuidAndAnr($uuid, $anr, false);
        }

        return $asset;
    }

    public function prepareAssetUuidsAndCodesCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('assets_uuids')) {
            foreach ($this->assetTable->findUuidsAndCodesByAnr($anr) as $data) {
                $this->importCacheHelper
                    ->addItemToArrayCache('assets_uuids', (string)$data['uuid'], (string)$data['uuid']);
                $this->importCacheHelper->addItemToArrayCache('assets_codes', $data['code'], $data['code']);
            }
        }
    }
}
