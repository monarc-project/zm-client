<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrRecommendationService;
use Monarc\FrontOffice\Service\AnrRecommendationSetService;
use Monarc\FrontOffice\Table\RecommendationSetTable;

class RecommendationImportProcessor
{
    public function __construct(
        private RecommendationSetTable $recommendationSetTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrRecommendationSetService $anrRecommendationSetService,
        private AnrRecommendationService $anrRecommendationService
    ) {
    }

    public function processRecommendationSetsData(Entity\Anr $anr, array $recommendationSetsData): void
    {
        $this->prepareRecommendationsCache($anr);
        foreach ($recommendationSetsData as $recommendationSetData) {
            $this->processRecommendationSetData($anr, $recommendationSetData);
        }
    }

    public function processRecommendationSetData(Entity\Anr $anr, array $recommendationSetData): void
    {
        $labelKey = 'label' . $anr->getLanguage();
        /* Supports the structure format prior v2.13.1 */
        if (isset($recommendationSetData[$labelKey]) && !isset($recommendationSetData['label'])) {
            $recommendationSetData['label'] = $recommendationSetData[$labelKey];
        }
        $recommendationSet = $this->getRecommendationSetFromCache(
            $recommendationSetData['uuid'],
            $recommendationSetData['label']
        );
        if ($recommendationSet === null) {
            $recommendationSet = $this->anrRecommendationSetService->create($anr, $recommendationSetData, false);
            $this->importCacheHelper->addItemToArrayCache(
                'recommendations_sets',
                $recommendationSet,
                $recommendationSet->getUuid()
            );
        }

        if (!empty($recommendationSetData['recommendations'])) {
            $this->processRecommendationsData($recommendationSet, $recommendationSetData['recommendations']);
        }
    }

    public function processRecommendationsData(
        Entity\RecommendationSet $recommendationSet,
        array $recommendationsData,
        bool $prepareCache = false
    ): void {
        if ($prepareCache) {
            $this->prepareRecommendationsCache($recommendationSet->getAnr());
        }
        foreach ($recommendationsData as $recommendationData) {
            $this->processRecommendationData($recommendationSet, $recommendationData);
        }
    }

    public function processRecommendationData(
        Entity\RecommendationSet $recommendationSet,
        array $recommendationData
    ): Entity\Recommendation {
        $anr = $recommendationSet->getAnr();
        $recommendation = $this->getRecommendationFromCache($recommendationData['uuid']);
        if ($recommendation !== null) {
            return $recommendation;
        }

        /* The code should be unique within recommendations sets. */
        if (\in_array($recommendationData['code'], $this->importCacheHelper->getItemFromArrayCache(
            'recommendations_codes_by_set_uuid',
            $recommendationSet->getUuid()
        ) ?? [], true)) {
            $recommendationData['code'] .= '-' . time();
        }
        $recommendationData['recommendationSet'] = $recommendationSet;

        $recommendation = $this->anrRecommendationService->create($anr, $recommendationData, false);
        $this->importCacheHelper->addItemToArrayCache('recommendations', $recommendation, $recommendation->getUuid());

        return $recommendation;
    }

    public function getRecommendationSetFromCache(string $uuid, string $label): ?Entity\RecommendationSet
    {
        $recommendationSet = $this->importCacheHelper->getItemFromArrayCache('recommendations_sets', $uuid);
        if ($recommendationSet === null) {
            /** @var Entity\RecommendationSet $set */
            foreach ($this->importCacheHelper->getItemFromArrayCache('recommendations_sets') ?? [] as $set) {
                if ($set->getLabel() === $label) {
                    $recommendationSet = $set;
                    break;
                }
            }
        }

        return $recommendationSet;
    }

    public function getRecommendationFromCache(string $uuid): ?Entity\Recommendation
    {
        return $this->importCacheHelper->getItemFromArrayCache('recommendations', $uuid);
    }

    public function prepareRecommendationsCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('recommendations_sets')) {
            /** @var Entity\RecommendationSet $recommendationSet */
            foreach ($this->recommendationSetTable->findByAnr($anr) as $recommendationSet) {
                $this->importCacheHelper->addItemToArrayCache(
                    'recommendations_sets',
                    $recommendationSet,
                    $recommendationSet->getUuid()
                );
                $recommendationsCodes = [];
                foreach ($recommendationSet->getRecommendations() as $recommendation) {
                    $this->importCacheHelper->addItemToArrayCache(
                        'recommendations',
                        $recommendation,
                        $recommendation->getUuid()
                    );
                    $recommendationsCodes[] = $recommendation->getCode();
                }
                $this->importCacheHelper->addItemToArrayCache(
                    'recommendations_codes_by_set_uuid',
                    $recommendationsCodes,
                    $recommendationSet->getUuid()
                );
            }
        }
    }
}
