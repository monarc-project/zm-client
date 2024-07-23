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
use Monarc\FrontOffice\Service\SoaService;
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
        private SoaCategoryService $soaCategoryService,
        private SoaService $soaService
    ) {
    }

    public function processReferentialsData(Entity\Anr $anr, array $referentialsData): void
    {
        foreach ($referentialsData as $referentialData) {
            $this->processReferentialData($anr, $referentialData);
        }
    }

    public function processReferentialData(Entity\Anr $anr, array $referentialData): Entity\Referential
    {
        $referential = $this->getReferentialFromCache($anr, $referentialData['uuid']);
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

    public function processMeasuresData(Entity\Anr $anr, Entity\Referential $referential, array $measuresData): void
    {
        foreach ($measuresData as $measureData) {
            $this->processMeasureData($anr, $referential, $measureData);
        }
    }

    public function processMeasureData(
        Entity\Anr $anr,
        Entity\Referential $referential,
        array $measureData
    ): Entity\Measure {
        $measure = $this->getMeasureFromCache($anr, $measureData['uuid']);
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

            $soa = $this->soaService->createSoaObject($anr, $measure);
            $this->importCacheHelper->addItemToArrayCache('soas_by_measure_uuids', $soa, $measure->getUuid());
        }

        $this->processLinkedMeasures($anr, $measure, $measureData);

        return $measure;
    }

    public function processLinkedMeasures(Entity\Anr $anr, Entity\Measure $measure, array $measureData): void
    {
        if (!empty($measureData['linkedMeasures'])) {
            foreach ($measureData['linkedMeasures'] as $linkedMeasureData) {
                $linkedMeasure = $this->getMeasureFromCache($anr, $linkedMeasureData['uuid']);
                if ($linkedMeasure !== null) {
                    $measure->addLinkedMeasure($linkedMeasure);
                    $this->measureTable->save($measure, false);
                }
            }
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
            $soaCategoryLabel = $measureData['category'];
            if (!empty($soaCategoryLabel['label'])) {
                $soaCategoryLabel = $soaCategoryLabel['label'];
            } elseif (!empty($soaCategoryLabel['label' . $anr->getLanguage()])) {
                $soaCategoryLabel = $soaCategoryLabel['label' . $anr->getLanguage()];
            }
            $soaCategory = $this->getSoaCategoryFromCache($anr, $referential->getUuid() . '_' . $soaCategoryLabel);
            if ($soaCategory === null) {
                $soaCategory = $this->soaCategoryService->create($anr, [
                    'referential' => $referential,
                    'label' . $anr->getLanguage() => $soaCategoryLabel,
                ], false);
                $this->importCacheHelper->addItemToArrayCache(
                    'soa_categories_by_referential_uuid_and_label',
                    $soaCategory,
                    $referential->getUuid() . '_' . $soaCategoryLabel
                );
            }
        }

        return $soaCategory;
    }

    public function getReferentialFromCache(Entity\Anr $anr, string $referentialUuid): ?Entity\Referential
    {
        $this->prepareReferentialsAndMeasuresCache($anr);

        return $this->importCacheHelper->getItemFromArrayCache('referentials', $referentialUuid);
    }

    public function getMeasureFromCache(Entity\Anr $anr, string $measureUuid): ?Entity\Measure
    {
        $this->prepareReferentialsAndMeasuresCache($anr);

        return $this->importCacheHelper->getItemFromArrayCache('measures', $measureUuid);
    }

    private function getSoaCategoryFromCache(Entity\Anr $anr, string $refUuidAndSoaCatLabel): ?Entity\SoaCategory
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_soa_categories_cache_loaded')) {
            $this->importCacheHelper->addItemToArrayCache('is_soa_categories_cache_loaded', true);
            /** @var Entity\SoaCategory $soaCategory */
            foreach ($this->soaCategoryTable->findByAnr($anr) as $soaCategory) {
                $this->importCacheHelper->addItemToArrayCache(
                    'soa_categories_by_referential_uuid_and_label',
                    $soaCategory,
                    $soaCategory->getReferential()->getUuid() . '_' . $soaCategory->getLabel($anr->getLanguage())
                );
            }
        }

        return $this->importCacheHelper
            ->getItemFromArrayCache('soa_categories_by_referential_uuid_and_label', $refUuidAndSoaCatLabel);
    }

    private function prepareReferentialsAndMeasuresCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_referentials_cache_loaded')) {
            $this->importCacheHelper->addItemToArrayCache('is_referentials_cache_loaded', true);
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
}
