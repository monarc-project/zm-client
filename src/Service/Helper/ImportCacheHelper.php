<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service\Helper;

use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Table\AmvTable;
use Monarc\FrontOffice\Model\Table\AssetTable;
use Monarc\FrontOffice\Model\Table\MeasureTable;
use Monarc\FrontOffice\Model\Table\ReferentialTable;
use Monarc\FrontOffice\Model\Table\RolfRiskTable;
use Monarc\FrontOffice\Model\Table\RolfTagTable;
use Monarc\FrontOffice\Model\Table\ThemeTable;
use Monarc\FrontOffice\Model\Table\ThreatTable;
use Monarc\FrontOffice\Model\Table\VulnerabilityTable;

class ImportCacheHelper
{
    private AssetTable $assetTable;

    private ThreatTable $threatTable;

    private VulnerabilityTable $vulnerabilityTable;

    private ThemeTable $themeTable;

    private MeasureTable $measureTable;

    private ReferentialTable $referentialTable;

    private RolfTagTable $rolfTagTable;

    private RolfRiskTable $rolfRiskTable;

    private AmvTable $amvTable;

    private array $cachedData = [];

    public function __construct(
        AssetTable $assetTable,
        ThreatTable $threatTable,
        VulnerabilityTable $vulnerabilityTable,
        ThemeTable $themeTable,
        MeasureTable $measureTable,
        ReferentialTable $referentialTable,
        RolfTagTable $rolfTagTable,
        RolfRiskTable $rolfRiskTable,
        AmvTable $amvTable
    ) {
        $this->assetTable = $assetTable;
        $this->threatTable = $threatTable;
        $this->vulnerabilityTable = $vulnerabilityTable;
        $this->themeTable = $themeTable;
        $this->measureTable = $measureTable;
        $this->referentialTable = $referentialTable;
        $this->rolfTagTable = $rolfTagTable;
        $this->rolfRiskTable = $rolfRiskTable;
        $this->amvTable = $amvTable;
    }

    public function prepareAssetsThreatsVulnerabilitiesAndThemesCacheData(Anr $anr): void
    {
        $this->prepareAssetsCacheData($anr);
        $this->prepareThreatsCacheData($anr);
        $this->prepareVulnerabilitiesCacheData($anr);
        $this->prepareThemesCacheData($anr);
    }

    public function prepareAssetsCacheData(Anr $anr): void
    {
        if (!isset($this->cachedData['assets'])) {
            $this->cachedData['assets'] = [];
            $this->cachedData['assets_codes'] = [];
            foreach ($this->assetTable->findByAnr($anr) as $asset) {
                $this->cachedData['assets'][$asset->getUuid()] = $asset;
                $this->cachedData['assets_codes'][] = $asset->getCode();
            }
        }
    }

    public function prepareThreatsCacheData(Anr $anr): void
    {
        if (!isset($this->cachedData['threats'])) {
            $this->cachedData['threats'] = [];
            $this->cachedData['threats_codes'] = [];
            foreach ($this->threatTable->findByAnr($anr) as $threat) {
                $this->cachedData['threats'][$threat->getUuid()] = $threat;
                $this->cachedData['threats_codes'][] = $threat->getCode();
            }
        }
    }

    public function prepareVulnerabilitiesCacheData(Anr $anr): void
    {
        if (!isset($this->cachedData['vulnerabilities'])) {
            $this->cachedData['vulnerabilities'] = [];
            $this->cachedData['vulnerabilities_codes'] = [];
            foreach ($this->vulnerabilityTable->findByAnr($anr) as $vulnerability) {
                $this->cachedData['vulnerabilities'][$vulnerability->getUuid()] = $vulnerability;
                $this->cachedData['vulnerabilities_codes'][] = $vulnerability->getCode();
            }
        }
    }

    public function prepareThemesCacheData(Anr $anr): void
    {
        if (!isset($this->cachedData['themes'])) {
            $this->cachedData['themes'] = [];
            foreach ($this->themeTable->findByAnr($anr) as $theme) {
                $this->cachedData['themes'][$theme->getLabel($anr->getLanguage())] = $theme;
            }
        }
    }

    public function prepareMeasuresAndReferentialCacheData(Anr $anr): void
    {
        $this->prepareMeasuresCacheData($anr);
        $this->prepareReferentialsCacheData($anr);
    }

    public function prepareMeasuresCacheData(Anr $anr): void
    {
        if (!isset($this->cachedData['measures'])) {
            $this->cachedData['measures'] = $this->measureTable->findByAnrIndexedByUuid($anr);
        }
    }

    public function prepareReferentialsCacheData(Anr $anr): void
    {
        if (!isset($this->cachedData['referentials'])) {
            $this->cachedData['referentials'] = $this->referentialTable->findByAnrIndexedByUuid($anr);
        }
    }

    public function prepareRolfTagsCacheData(Anr $anr): void
    {
        if (!isset($this->cachedData['rolfTags'])) {
            $this->cachedData['rolfTags'] = $this->rolfTagTable->findByAnrIndexedByCode($anr);
        }
    }

    public function prepareRolfRisksCacheData(Anr $anr): void
    {
        if (!isset($this->cachedData['rolfRisks'])) {
            $this->cachedData['rolfRisks'] = $this->rolfRiskTable->findByAnrIndexedByCode($anr);
        }
    }

    public function prepareAmvsCacheData(Anr $anr): void
    {
        if (!isset($this->cachedData['amvs'])) {
            $this->cachedData['amvs'] = $this->amvTable->findByAnrIndexedByUuid($anr);
        }
    }

    public function addDataToCache(string $cacheKey, $value, $elementKey = null): void
    {
        if ($elementKey === null) {
            $this->cachedData[$cacheKey][] = $value;
        } else {
            $this->cachedData[$cacheKey][$elementKey] = $value;
        }
    }

    public function removeDataFromCache(string $cacheKey, $value, $elementKey = null): void
    {
        if ($elementKey === null) {
            unset($this->cachedData[$cacheKey]);
        } else {
            unset($this->cachedData[$cacheKey][$elementKey]);
        }
    }

    /**
     * @return object[]
     */
    public function getCachedDataByKey(string $cacheKey): array
    {
        return $this->cachedData[$cacheKey] ?? [];
    }

    /**
     * @return mixed|null Can be an object or array of objects.
     */
    public function getCachedObjectByKeyAndId(string $cacheKey, $elementKey)
    {
        return $this->cachedData[$cacheKey][$elementKey] ?? null;
    }
}
