<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrThemeService;
use Monarc\FrontOffice\Service\AnrThreatService;
use Monarc\FrontOffice\Table\ThemeTable;
use Monarc\FrontOffice\Table\ThreatTable;

class ThreatImportProcessor
{
    public function __construct(
        private ThreatTable $threatTable,
        private ThemeTable $themeTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrThreatService $anrThreatService,
        private AnrThemeService $anrThemeService
    ) {
    }

    public function processThreatsData(Entity\Anr $anr, array $threatsData, array $themesData = []): void
    {
        foreach ($threatsData as $threatData) {
            $this->processThreatData($anr, $threatData, $themesData);
        }
    }

    public function processThreatData(Entity\Anr $anr, array $threatData, array $themesData = []): Entity\Threat
    {
        $threat = $this->getThreatFromCache($anr, $threatData['uuid']);
        if ($threat !== null) {
            $this->syncThreatCiaCriteria($threat, $threatData);
            return $threat;
        }

        /* In the old structure themes are exported separately, convert to the new format. */
        $labelKey = 'label' . $anr->getLanguage();
        if (!empty($themesData)
            && !empty($threatData['theme'])
            && isset($themesData[$threatData['theme']][$labelKey])
        ) {
            $threatData['theme'] = ['label' => $themesData[$threatData['theme']][$labelKey]];
        }

        if ($this->importCacheHelper->isItemInArrayCache('threats_codes', $threatData['code'])) {
            $threatData['code'] .= '-' . time();
        }
        $threatData['theme'] = !empty($threatData['theme'])
            ? $this->processThemeData($anr, $threatData['theme'])
            : null;

        /* In the new data structure there is only "label" field set. */
        if (isset($threatData['label'])) {
            $threatData['label' . $anr->getLanguage()] = $threatData['label'];
        }
        if (isset($threatData['description'])) {
            $threatData['description' . $anr->getLanguage()] = $threatData['description'];
        }

        $threat = $this->anrThreatService->create($anr, $threatData, false);
        $this->importCacheHelper->addItemToArrayCache('threats_by_uuid', $threat, $threat->getUuid());

        return $threat;
    }

    public function getThreatFromCache(Entity\Anr $anr, string $uuid): ?Entity\Threat
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_threats_cache_loaded')) {
            $this->importCacheHelper->setArrayCacheValue('is_threats_cache_loaded', true);
            /** @var Entity\Threat $threat */
            foreach ($this->threatTable->findByAnr($anr) as $threat) {
                $this->importCacheHelper->addItemToArrayCache('threats_by_uuid', $threat, $threat->getUuid());
                $this->importCacheHelper->addItemToArrayCache('threats_codes', $threat->getCode(), $threat->getCode());
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('threats_by_uuid', $uuid);
    }

    private function processThemeData(Entity\Anr $anr, array $themeData): Entity\Theme
    {
        if (isset($themeData['label'])) {
            $themeData['label' . $anr->getLanguage()] = $themeData['label'];
        }
        $themeLabel = $themeData['label' . $anr->getLanguage()];
        $theme = $this->getThemeFromCache($anr, $themeLabel);
        if ($theme === null) {
            $theme = $this->anrThemeService->create($anr, $themeData, false);
            $this->importCacheHelper->addItemToArrayCache('themes_by_labels', $theme, $themeLabel);
        }

        return $theme;
    }

    private function getThemeFromCache(Entity\Anr $anr, string $label): ?Entity\Theme
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_themes_cache_loaded')) {
            $this->importCacheHelper->setArrayCacheValue('is_themes_cache_loaded', true);
            /** @var Entity\Theme $theme */
            foreach ($this->themeTable->findByAnr($anr) as $theme) {
                $this->importCacheHelper
                    ->addItemToArrayCache('themes_by_labels', $theme, $theme->getLabel($anr->getLanguage()));
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('themes_by_labels', $label);
    }

    private function syncThreatCiaCriteria(Entity\Threat $threat, array $threatData): void
    {
        if (empty($threatData['confidentiality'])
            || empty($threatData['integrity'])
            || empty($threatData['availability'])
        ) {
            return;
        }

        $threat
            ->setConfidentiality((int)$threatData['confidentiality'])
            ->setIntegrity((int)$threatData['integrity'])
            ->setAvailability((int)$threatData['availability']);
        $this->threatTable->save($threat, false);
    }
}
