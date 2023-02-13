<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\Theme;
use Monarc\FrontOffice\Model\Entity\Threat;
use Monarc\FrontOffice\Model\Entity\Vulnerability;
use Monarc\FrontOffice\Service\AnrAssetService;
use Monarc\FrontOffice\Service\AnrThemeService;
use Monarc\FrontOffice\Service\AnrThreatService;
use Monarc\FrontOffice\Service\AnrVulnerabilityService;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Service\SoaCategoryService;

class AssetImportService
{
    private Table\AssetTable $assetTable;

    private Table\ThreatTable $threatTable;

    private Table\VulnerabilityTable $vulnerabilityTable;

    private DeprecatedTable\MeasureTable $measureTable;

    private Table\AmvTable $amvTable;

    private DeprecatedTable\InstanceTable $instanceTable;

    private DeprecatedTable\InstanceRiskTable $instanceRiskTable;

    private DeprecatedTable\ReferentialTable $referentialTable;

    private UserSuperClass $connectedUser;

    private ImportCacheHelper $importCacheHelper;

    private SoaCategoryService $soaCategoryService;

    private AnrAssetService $anrAssetService;

    private AnrThreatService $anrThreatService;

    private AnrThemeService $anrThemeService;

    private AnrVulnerabilityService $anrVulnerabilityService;

    public function __construct(
        Table\AssetTable $assetTable,
        Table\ThreatTable $threatTable,
        Table\VulnerabilityTable $vulnerabilityTable,
        DeprecatedTable\MeasureTable $measureTable,
        Table\AmvTable $amvTable,
        DeprecatedTable\InstanceTable $instanceTable,
        DeprecatedTable\InstanceRiskTable $instanceRiskTable,
        DeprecatedTable\ReferentialTable $referentialTable,
        ImportCacheHelper $importCacheHelper,
        SoaCategoryService $soaCategoryService,
        AnrAssetService $anrAssetService,
        AnrThreatService $anrThreatService,
        AnrThemeService $anrThemeService,
        AnrVulnerabilityService $anrVulnerabilityService,
        ConnectedUserService $connectedUserService
    ) {
        $this->assetTable = $assetTable;
        $this->threatTable = $threatTable;
        $this->vulnerabilityTable = $vulnerabilityTable;
        $this->measureTable = $measureTable;
        $this->amvTable = $amvTable;
        $this->instanceTable = $instanceTable;
        $this->instanceRiskTable = $instanceRiskTable;
        $this->referentialTable = $referentialTable;
        $this->importCacheHelper = $importCacheHelper;
        $this->soaCategoryService = $soaCategoryService;
        $this->anrAssetService = $anrAssetService;
        $this->anrThreatService = $anrThreatService;
        $this->anrThemeService = $anrThemeService;
        $this->anrVulnerabilityService = $anrVulnerabilityService;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function importFromArray($monarcVersion, array $data, Anr $anr): ?Asset
    {
        if (!isset($data['type']) || $data['type'] !== 'asset') {
            return null;
        }

        if (version_compare($monarcVersion, '2.8.2') < 0) {
            throw new Exception('Import of files exported from MONARC v2.8.1 or lower are not supported.'
                . ' Please contact us for more details.');
        }

        $asset = $this->processAssetDataAndGetAsset($data['asset'], $anr);
        if (!empty($data['amvs'])) {
            $this->processThreatsData($data['threats'], $data['themes'] ?? [], $anr);
            $this->processVulnerabilitiesData($data['vuls'], $anr);
            $this->processAmvsData($data['amvs'], $anr, $asset);
        }

        return $asset;
    }

    private function processAssetDataAndGetAsset(array $assetData, Anr $anr): Asset
    {
        /** @var Asset|null $asset */
        $asset = $this->assetTable->findByUuidAndAnr($assetData['uuid'], $anr, false);
        if ($asset !== null) {
            return $asset;
        }

        /* The code should be unique. */
        if ($this->assetTable->existsWithAnrAndCode($anr, $assetData['code'])) {
            $assetData['code'] .= '-' . time();
        }

//        $asset = (new Asset())
//            ->setUuid($assetData['uuid'])
//            ->setAnr($anr)
//            ->setLabels($assetData)
//            ->setDescriptions($assetData)
//            ->setStatus($assetData['status'] ?? 1)
//            ->setMode($assetData['mode'] ?? 0)
//            ->setType($assetData['type'])
//            ->setCode($assetData['code']);
//
//        $this->assetTable->save($asset, false);

        // TODO: adjust the asset service code...
        return $this->anrAssetService->create($anr, $assetData, false);
    }

    private function processThreatsData(array $threatsData, array $themesData, Anr $anr): void
    {
        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;

        foreach ($threatsData as $threatUuid => $threatData) {
            $themeData = $themesData[$threatData['theme']] ?? [];
            /** @var Threat|null $threat */
            $threat = $this->threatTable->findByUuidAndAnr($threatUuid, $anr, false);
            if ($threat !== null) {
                /* Validate Theme. */
                $currentTheme = $threat->getTheme();
                if (!empty($themeData)
                    && ($currentTheme === null
                        || $currentTheme->getLabel($languageIndex) !== $themeData[$labelKey]
                    )
                ) {
                    $theme = $this->processThemeDataAndGetTheme($themeData, $anr);
                    $threat->setTheme($theme);

                    $this->threatTable->save($threat, false);
                }
            } else {
                /* The code should be unique. */
                if ($this->importCacheHelper->getItemFromArrayCache('threats_codes', $threatData['code']) !== null
                   || $this->threatTable->existsWithAnrAndCode($anr, $threatData['code'])
                ) {
                    $threatData['code'] .= '-' . time();
                }
                $threatData['theme'] = !empty($themeData) ? $this->processThemeDataAndGetTheme($themeData, $anr) : null;

                $threat = $this->anrThreatService->create($anr, $threatData, false);
            }

            $this->importCacheHelper->addItemToArrayCache('threats', $threat, $threat->getUuid());
            $this->importCacheHelper->addItemToArrayCache('threats_codes', $threat->getCode(), $threat->getCode());
        }
    }

    private function processThemeDataAndGetTheme(array $themeData, Anr $anr): Theme
    {
        $this->importCacheHelper->prepareThemesCacheData($anr);

        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;
        $theme = $this->importCacheHelper->getItemFromArrayCache('themes_by_labels', $themeData[$labelKey]);
        if ($theme === null) {
            $theme = $this->anrThemeService->create($anr, $themeData, false);
            $this->importCacheHelper->addItemToArrayCache('themes_by_labels', $theme, $themeData[$labelKey]);
        }

        return $theme;
    }

    private function processVulnerabilitiesData(array $vulnerabilitiesData, Anr $anr): void
    {
        foreach ($vulnerabilitiesData as $vulnerabilityData) {
            /** @var Vulnerability|null $vulnerability */
            $vulnerability = $this->vulnerabilityTable->findByUuidAndAnr($vulnerabilityData['uuid'], $anr, false);
            if ($vulnerability === null) {
                /* The code should be unique. */
                if ($this->importCacheHelper
                    ->getItemFromArrayCache('vulnerabilities_codes', $vulnerabilityData['code']) !== null
                    || $this->vulnerabilityTable->existsWithAnrAndCode($anr, $vulnerabilityData['code'])
                ) {
                    $vulnerabilityData['code'] .= '-' . time();
                }

                $vulnerability = $this->anrVulnerabilityService->create($anr, $vulnerabilityData, false);
            }

            $this->importCacheHelper->addItemToArrayCache('vulnerabilities', $vulnerability, $vulnerability->getUuid());
            $this->importCacheHelper
                ->addItemToArrayCache('vulnerabilities_codes', $vulnerability->getCode(), $vulnerability->getCode());
        }
    }

    // TODO: use services to create the objects.
    private function processAmvsData(array $amvsData, Anr $anr, Asset $asset): void
    {
        $instances = null;
        foreach ($amvsData as $amvUuid => $amvData) {
            /** @var Amv|null $amv */
            $amv = $this->amvTable->findByUuidAndAnr($amvUuid, $anr, false);
            if ($amv === null) {
                $amv = (new Amv())
                    ->setUuid($amvUuid)
                    ->setAnr($anr)
                    ->setAsset($asset)
                    ->setMeasures(null)
                    ->setCreator($this->connectedUser->getEmail());

                $threat = $this->importCacheHelper->getItemFromArrayCache('threats', $amvData['threat']);
                $vulnerability = $this->importCacheHelper
                    ->getItemFromArrayCache('vulnerabilities', $amvData['vulnerability']);
                if ($threat === null || $vulnerability === null) {
                    throw new Exception(sprintf(
                        'The import file is malformed. AMV\'s "%s" threats or vulnerability was not processed before.',
                        $amvUuid
                    ));
                }

                $amv->setThreat($threat)->setVulnerability($vulnerability);

                $this->amvTable->save($amv, false);

                if ($instances === null) {
                    $instances = $this->instanceTable->findByAnrAndAsset($anr, $asset);
                }
                foreach ($instances as $instance) {
                    $instanceRisk = (new InstanceRisk())
                        ->setAnr($anr)
                        ->setAmv($amv)
                        ->setAsset($asset)
                        ->setInstance($instance)
                        ->setThreat($threat)
                        ->setVulnerability($vulnerability)
                        ->setCreator($this->connectedUser->getEmail());

                    $this->instanceRiskTable->saveEntity($instanceRisk, false);
                }
            }

            if (!empty($amvData['measures'])) {
                $this->processMeasuresAndReferentialData($amvData['measures'], $anr, $amv);
            }
        }

        foreach ($this->amvTable->findByAnrAndAsset($anr, $asset) as $oldAmv) {
            if (!isset($amvsData[$oldAmv->getUuid()])) {
                /** Set related instance risks to specific and delete the amvs leter */
                $instanceRisks = $oldAmv->getInstanceRisks();

                // TODO: remove the double iteration when #240 is done.
                // We do it due to multi-fields relation issue. When amv is set to null, anr is set to null as well.
                foreach ($instanceRisks as $instanceRisk) {
                    $instanceRisk->setAmv(null);
                    $instanceRisk->setAnr(null);
                    $instanceRisk->setSpecific(InstanceRisk::TYPE_SPECIFIC);
                    $this->instanceRiskTable->saveEntity($instanceRisk, false);
                }
                $this->instanceRiskTable->getDb()->flush();

                foreach ($instanceRisks as $instanceRisk) {
                    $instanceRisk
                        ->setAnr($anr)
                        ->setUpdater($this->connectedUser->getEmail());
                    $this->instanceRiskTable->saveEntity($instanceRisk, false);
                }
                $this->instanceRiskTable->getDb()->flush();

                $amvsToDelete[] = $oldAmv;
            }
        }

        if (!empty($amvsToDelete)) {
            $this->amvTable->deleteEntities($amvsToDelete);
        }
    }

    private function processMeasuresAndReferentialData(array $measuresData, Anr $anr, Amv $amv): void
    {
        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;
        foreach ($measuresData as $measureUuid) {
            $measure = $this->importCacheHelper->getItemFromArrayCache('measures', $measureUuid)
                ?: $this->measureTable->findByAnrAndUuid($anr, $measureUuid);
            if ($measure === null) {
                /* Backward compatibility. Prior v2.10.3 we did not set referential data when exported. */
                $referentialUuid = $data['measures'][$measureUuid]['referential']['uuid']
                    ?? $data['measures'][$measureUuid]['referential'];

                $referential = $this->importCacheHelper->getItemFromArrayCache('referentials', $referentialUuid)
                    ?: $this->referentialTable->findByAnrAndUuid($anr, $referentialUuid);

                /* For backward compatibility. */
                if ($referential === null
                    && isset($data['measures'][$measureUuid]['referential'][$labelKey])
                ) {
                    $referential = (new Referential())
                        ->setAnr($anr)
                        ->setUuid($referentialUuid)
                        ->setLabels([$labelKey => $data['measures'][$measureUuid]['referential'][$labelKey]])
                        ->setCreator($this->connectedUser->getEmail());

                    $this->referentialTable->saveEntity($referential, false);

                    $this->importCacheHelper->addItemToArrayCache('referentials', $referential, $referentialUuid);
                }

                /* For backward compatibility. */
                if ($referential === null) {
                    continue;
                }

                $soaCategory = $this->soaCategoryService->getOrCreateSoaCategory(
                    $this->importCacheHelper,
                    $anr,
                    $referential,
                    $data['measures'][$measureUuid]['category'][$labelKey] ?? ''
                );

                $measure = (new Measure())
                    ->setAnr($anr)
                    ->setUuid($measureUuid)
                    ->setCategory($soaCategory)
                    ->setReferential($referential)
                    ->setCode($data['measures'][$measureUuid]['code'])
                    ->setLabels($data['measures'][$measureUuid])
                    ->setCreator($this->connectedUser->getEmail());

                $this->importCacheHelper->addItemToArrayCache('measures', $measure, $measure->getUuid());
            }

            $measure->addAmv($amv);

            $this->measureTable->saveEntity($measure, false);
        }
    }
}
