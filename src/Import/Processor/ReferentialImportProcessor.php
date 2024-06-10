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
        $this->prepareReferentialsAndMeasuresCache($anr);
        $this->prepareSoaCategoriesCache($anr);
        foreach ($referentialsData as $referentialData) {
            $this->processReferentialData($anr, $referentialData);
        }
    }

    public function processReferentialData(Entity\Anr $anr, array $referentialData): Entity\Referential
    {
        $referential = $this->importCacheHelper->getItemFromArrayCache('referentials', $referentialData['uuid']);
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
            $this->prepareReferentialsAndMeasuresCache($anr);
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

    public function getMeasureFromCache(string $measureUuid): ?Entity\Measure
    {
        return $this->importCacheHelper->getItemFromArrayCache('measures', $measureUuid);
    }

    public function processMeasureData(
        Entity\Anr $anr,
        Entity\Referential $referential,
        array $measureData
    ): Entity\Measure {
        $measure = $this->getMeasureFromCache($measureData['uuid']);
        if ($measure === null) {
            /* The code should be unique. */
            if (\in_array($measureData['code'], $this->importCacheHelper->getItemFromArrayCache(
                'measures_codes_by_ref_uuid',
                $referential->getUuid()
            ) ?? [], true)) {
                $measureData['code'] .= '-' . time();
            }

            /* In the new data structure there is only "label" field set. */
            if (isset($measureData['label'])) {
                $measureData['label' . $anr->getLanguage()] = $measureData['label'];
            }

            $soaCategory = $this->processSoaCategoryData($anr, $referential, $measureData);

            $measure = $this->anrMeasureService
                ->createMeasureObject($anr, $referential, $soaCategory, $measureData, false);
            $this->importCacheHelper->addItemToArrayCache('measures', $measure, $measure->getUuid());
        }

        $this->processLinkedMeasures($measure, $measureData);

        return $measure;
    }

    // TODO: handle the old structure 'measuresmeasures', it's in a parallel key with measures and consists elements:
    //  {"father": "3e3e542a-67b2-4a77-b09b-9dc9b977cd8e", "child": "01096bf7-a45e-40d9-851e-72a6b8d7344a"}
    private function processLinkedMeasures(Entity\Measure $measure, array $measureData): void
    {
        if (!empty($measureData['linkedMeasures'])) {
            foreach ($measureData['linkedMeasures'] as $linkedMeasureData) {
                $linkedMeasure = $this->importCacheHelper->getItemFromArrayCache(
                    'measures',
                    $linkedMeasureData['uuid']
                );
                if ($linkedMeasure !== null) {
                    $measure->addLinkedMeasure($linkedMeasure);
                    $this->measureTable->save($linkedMeasure, false);
                }
            }
        }
    }

    private function prepareReferentialsAndMeasuresCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('referentials')) {
            /** @var Entity\Referential $referential */
            foreach ($this->referentialTable->findByAnr($anr) as $referential) {
                $this->importCacheHelper->addItemToArrayCache('referentials', $referential, $referential->getUuid());
                $measuresCodes = [];
                foreach ($referential->getMeasures() as $measure) {
                    $this->importCacheHelper->addItemToArrayCache('measures', $measure, $measure->getUuid());
                    $measuresCodes[] = $measure->getCode();
                }
                $this->importCacheHelper->addItemToArrayCache(
                    'measures_codes_by_ref_uuid',
                    $measuresCodes,
                    $referential->getUuid()
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
