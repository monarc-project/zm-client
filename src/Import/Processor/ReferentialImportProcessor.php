<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrMeasureService;
use Monarc\FrontOffice\Service\AnrReferentialService;
use Monarc\FrontOffice\Service\SoaCategoryService;
use Monarc\FrontOffice\Table\MeasureTable;
use Monarc\FrontOffice\Table\ReferentialTable;
use Monarc\FrontOffice\Table\SoaCategoryTable;

class ReferentialImportProcessor
{
    public function __construct(
        private ReferentialTable $referentialTable,
        private MeasureTable $measureTable,
        private SoaCategoryTable $soaCategoryTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrReferentialService $anrReferentialService,
        private AnrMeasureService $anrMeasureService,
        private SoaCategoryService $soaCategoryService
    ) {
    }

    public function processReferentialsData(Entity\Anr $anr, array $referentialsData): void
    {
        $this->prepareReferentialUuidsAndMeasuresUuidsAndCodesCache($anr);
        $this->prepareSoaCategoriesCache($anr);
        foreach ($referentialsData as $referentialData) {
            $this->processReferentialData($anr, $referentialData);
        }
    }

    public function processReferentialData(Entity\Anr $anr, array $referentialData): Entity\Referential
    {
        $referential = $this->importCacheHelper->getItemFromArrayCache('referentials', $referentialData['uuid']);
        if ($referential !== null) {
            return $referential;
        }

        /* The current anr referential' UUIDs are preloaded, so can be validated first. */
        if ($this->importCacheHelper->isCacheKeySet('referential_' . $referential['uuid'] . '_measures_uuids')) {
            /** @var Entity\Referential $referential */
            $referential = $this->referentialTable->findByUuidAndAnr($referentialData['uuid'], $anr, false);
        }

        if ($referential === null) {
            /* In the new data structure there is only "label" field set. */
            if (isset($referentialData['label'])) {
                $referentialData['label' . $anr->getLanguage()] = $referentialData['label'];
            }

            $referential = $this->anrReferentialService->create($anr, $referentialData, false);
            $this->importCacheHelper->addItemToArrayCache('referentials', $referential, $referential->getUuid());
        }

        if (!empty($referentialData['measures'])) {
            $this->processMeasuresData($anr, $referential, $referentialData['measures']);
        }

        return $referential;
    }

    public function processMeasuresData(
        Entity\Anr $anr,
        Entity\Referential $referential,
        array $measuresData,
        bool $prepareCache = false
    ): void {
        if ($prepareCache) {
            $this->prepareReferentialUuidsAndMeasuresUuidsAndCodesCache($anr);
            $this->prepareSoaCategoriesCache($anr);
        }

        foreach ($measuresData as $measureData) {
            $this->processMeasureData($anr, $referential, $measureData);
        }
    }

    public function processSoaCategoryData(
        Entity\Anr $anr,
        Entity\Referential $referential,
        array $measureData
    ): ?Entity\SoaCategory {
        $soaCategory = null;
        if (!empty($measureData['category'])) {
            /* Support the previous structure format. */
            $soaCategoryLabel = $measureData['category']['label'] ?? $measureData['category'];
            $soaCategory = $this->importCacheHelper->getItemFromArrayCache(
                'referential_' . $referential->getUuid() . '_soa_categories_by_labels',
                $soaCategoryLabel
            );
            if ($soaCategory === null) {
                $soaCategory = $this->soaCategoryService->create($anr, [
                    'referential' => $referential,
                    'label' . $anr->getLanguage() => $soaCategoryLabel,
                ], false);
                $this->importCacheHelper->addItemToArrayCache(
                    'referential_' . $referential->getUuid() . '_soa_categories_by_labels',
                    $soaCategory,
                    $soaCategoryLabel
                );
            }
        }

        return $soaCategory;
    }

    private function processMeasureData(
        Entity\Anr $anr,
        Entity\Referential $referential,
        array $measureData
    ): Entity\Measure {
        $measure = $this->importCacheHelper->getItemFromArrayCache('measures', $measureData['uuid']);
        if ($measure !== null) {
            return $measure;
        }

        /* The current anr measures' UUIDs are preloaded, so can be validated first. */
        if ($this->importCacheHelper->isItemInArrayCache(
            'referential_' . $referential->getUuid() . '_measures_uuids',
            $measureData['uuid']
        )) {
            /** @var Entity\Measure $measure */
            $measure = $this->measureTable->findByUuidAndAnr($measureData['uuid'], $anr, false);
            if ($measure !== null) {
                return $measure;
            }
        }

        /* The code should be unique. */
        if ($this->importCacheHelper->isItemInArrayCache(
            'referential_' . $referential->getUuid() . '_measures_codes',
            $measureData['code']
        )) {
            $measureData['code'] .= '-' . time();
        }

        /* In the new data structure there is only "label" field set. */
        if (isset($measureData['label'])) {
            $measureData['label' . $anr->getLanguage()] = $measureData['label'];
        }

        $soaCategory = $this->processSoaCategoryData($anr, $referential, $measureData);

        $measure = $this->anrMeasureService->createMeasureObject($anr, $referential, $soaCategory, $measureData, false);
        $this->importCacheHelper->addItemToArrayCache('measures', $measure, $measure->getUuid());

        $this->processLinkedMeasures($measure, $measureData);

        return $measure;
    }

    private function processLinkedMeasures(Entity\Measure $measure, array $measureData): void
    {
        if (!empty($measureData['linkedMeasures'])) {
            foreach ($measureData['linkedMeasures'] as $linkedMeasureData) {
                $linkedMeasure = $this->importCacheHelper
                    ->getItemFromArrayCache('measures', $linkedMeasureData['uuid']);
                if ($linkedMeasure === null) {
                    if ($this->importCacheHelper->isItemInArrayCache(
                        'referential_' . $linkedMeasureData['referential']['uuid'] . '_measures_uuids',
                        $linkedMeasureData['uuid']
                    )) {
                        /** @var Entity\Measure $linkedMeasure */
                        $linkedMeasure = $this->measureTable->findByUuidAndAnr(
                            $linkedMeasureData['uuid'],
                            $measure->getAnr(),
                            false
                        );
                    }
                }
                if ($linkedMeasure !== null) {
                    $measure->addLinkedMeasure($linkedMeasure);
                    $this->measureTable->save($linkedMeasure, false);
                }
            }
        }
    }

    private function prepareReferentialUuidsAndMeasuresUuidsAndCodesCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('referential_cache')) {
            $this->importCacheHelper->addItemToArrayCache('referential_cache', true);
            foreach ($this->referentialTable->findReferentialsUuidsWithMeasuresUuidsAndCodesByAnr($anr) as $data) {
                $this->importCacheHelper->addItemToArrayCache(
                    'referential_' . $data['uuid'] . '_measures_uuids',
                    (string)$data['measure_uuid'],
                    (string)$data['measure_uuid']
                );
                $this->importCacheHelper->addItemToArrayCache(
                    'referential_' . $data['uuid'] . '_measures_codes',
                    $data['measure_code'],
                    $data['measure_code']
                );
            }
        }
    }

    private function prepareSoaCategoriesCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('soa_categories_cache')) {
            $this->importCacheHelper->addItemToArrayCache('soa_categories_cache', true);
            /** @var Entity\SoaCategory $soaCategory */
            foreach ($this->soaCategoryTable->findByAnr($anr) as $soaCategory) {
                $this->importCacheHelper->addItemToArrayCache(
                    'referential_' . $soaCategory->getReferential()->getUuid() . '_soa_categories_by_labels',
                    $soaCategory,
                    $soaCategory->getLabel($anr->getLanguage())
                );
            }
        }
    }
}
