<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\SoaCategory;
use Monarc\FrontOffice\Model\Entity\Theme;
use Monarc\FrontOffice\Model\Entity\Threat;
use Monarc\FrontOffice\Model\Entity\Vulnerability;
use Monarc\FrontOffice\Model\Table\AmvTable;
use Monarc\FrontOffice\Model\Table\AssetTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\MeasureTable;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;
use Monarc\FrontOffice\Model\Table\ReferentialTable;
use Monarc\FrontOffice\Model\Table\SoaCategoryTable;
use Monarc\FrontOffice\Model\Table\ThemeTable;
use Monarc\FrontOffice\Model\Table\ThreatTable;
use Monarc\FrontOffice\Model\Table\VulnerabilityTable;
use Monarc\FrontOffice\Service\Helper\ImportCacheHelper;

class AssetImportService
{
    private AssetTable $assetTable;

    private ThemeTable $themeTable;

    private ThreatTable $threatTable;

    private VulnerabilityTable $vulnerabilityTable;

    private MeasureTable $measureTable;

    private AmvTable $amvTable;

    private MonarcObjectTable $monarcObjectTable;

    private InstanceTable $instanceTable;

    private InstanceRiskTable $instanceRiskTable;

    private ReferentialTable $referentialTable;

    private SoaCategoryTable $soaCategoryTable;

    private UserSuperClass $connectedUser;

    private ImportCacheHelper $importCacheHelper;

    public function __construct(
        AssetTable $assetTable,
        ThemeTable $themeTable,
        ThreatTable $threatTable,
        VulnerabilityTable $vulnerabilityTable,
        MeasureTable $measureTable,
        AmvTable $amvTable,
        MonarcObjectTable $monarcObjectTable,
        InstanceTable $instanceTable,
        InstanceRiskTable $instanceRiskTable,
        ReferentialTable $referentialTable,
        SoaCategoryTable $soaCategoryTable,
        ConnectedUserService $connectedUserService,
        ImportCacheHelper $importCacheHelper
    ) {
        $this->assetTable = $assetTable;
        $this->themeTable = $themeTable;
        $this->threatTable = $threatTable;
        $this->vulnerabilityTable = $vulnerabilityTable;
        $this->measureTable = $measureTable;
        $this->amvTable = $amvTable;
        $this->monarcObjectTable = $monarcObjectTable;
        $this->instanceTable = $instanceTable;
        $this->instanceRiskTable = $instanceRiskTable;
        $this->referentialTable = $referentialTable;
        $this->soaCategoryTable = $soaCategoryTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->importCacheHelper = $importCacheHelper;
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

        $this->importCacheHelper->prepareAssetsThreatsVulnerabilitiesAndThemesCacheData($anr);

        $asset = $this->processAssetDataAndGetAsset($data['asset'], $anr);
        if (!empty($data['amvs'])) {
            $this->processThreatsData($data['threats'], $anr);
            $this->processVulnerabilitiesData($data['vuls'], $anr);
            $this->processAmvsData($data['amvs'], $anr, $asset);
        }

        return $asset;
    }

    private function processAssetDataAndGetAsset(array $assetData, Anr $anr): Asset
    {
        /** @var Asset $asset */
        $asset = $this->importCacheHelper->getCachedObjectByKeyAndId('assets', $assetData['uuid']);
        if ($asset === null) {
            /* The code should be unique. */
            $assetCode = \in_array(
                $assetData['code'],
                $this->importCacheHelper->getCachedDataByKey('assets_codes'),
                true
            ) ? $assetData['code'] . '-' . time() : $assetData['code'];

            $asset = (new Asset())
                ->setUuid($assetData['uuid'])
                ->setAnr($anr)
                ->setLabels($assetData)
                ->setDescriptions($assetData)
                ->setStatus($assetData['status'] ?? 1)
                ->setMode($assetData['mode'] ?? 0)
                ->setType($assetData['type'])
                ->setCode($assetCode);

            $this->assetTable->saveEntity($asset, false);

            $this->importCacheHelper->addDataToCache('assets', $asset, $asset->getUuid());
            $this->importCacheHelper->addDataToCache('assets_codes', $asset->getCode());
        }

        return $asset;
    }

    private function processThreatsData(array $threatsData, Anr $anr): void
    {
        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;

        foreach ($threatsData as $threatUuid => $threatData) {
            /** @var Threat|null $threat */
            $threat = $this->importCacheHelper->getCachedObjectByKeyAndId('threats', $threatUuid);
            $themeData = $data['themes'][$threatData['theme']] ?? [];
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
                }
            } else {
                $theme = $this->processThemeDataAndGetTheme($themeData, $anr);

                /* The code should be unique. */
                $threatData['code'] = \in_array(
                    $threatData['code'],
                    $this->importCacheHelper->getCachedDataByKey('threats_codes'),
                    true
                ) ? $threatData['code'] . '-' . time() : $threatData['code'];

                $threat = (new Threat())
                    ->setUuid($threatData['uuid'])
                    ->setAnr($anr)
                    ->setCode($threatData['code'])
                    ->setLabels($threatData)
                    ->setDescriptions($threatData)
                    ->setMode((int)$threatData['mode'])
                    ->setStatus((int)$threatData['status'])
                    ->setTrend((int)$threatData['trend'])
                    ->setQualification((int)$threatData['qualification'])
                    ->setComment($threatData['comment'] ?? '')
                    ->setCreator($this->connectedUser->getEmail());
                if (isset($threatData['c'])) {
                    $threat->setConfidentiality((int)$threatData['c']);
                }
                if (isset($threatData['i'])) {
                    $threat->setIntegrity((int)$threatData['i']);
                }
                if (isset($threatData['a'])) {
                    $threat->setAvailability((int)$threatData['a']);
                }
                $threat->setTheme($theme);

                $this->importCacheHelper->addDataToCache('threats', $threat, $threat->getUuid());
                $this->importCacheHelper->addDataToCache('threats_codes', $threat->getCode());
            }

            $this->threatTable->saveEntity($threat, false);
        }
    }

    private function processThemeDataAndGetTheme(array $themeData, Anr $anr): Theme
    {
        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;
        $theme = $this->importCacheHelper->getCachedObjectByKeyAndId('themes', $themeData[$labelKey]);
        if ($theme === null) {
            $theme = $this->getCreatedTheme($anr, $themeData);
            $this->importCacheHelper->addDataToCache('themes', $theme, $theme->getLabel($languageIndex));
        }

        return $theme;
    }

    private function getCreatedTheme(Anr $anr, array $data): Theme
    {
        $theme = (new Theme())
            ->setAnr($anr)
            ->setLabels($data)
            ->setCreator($this->connectedUser->getEmail());

        $this->themeTable->saveEntity($theme, false);

        return $theme;
    }

    private function processVulnerabilitiesData(array $vulnerabilitiesData, Anr $anr): void
    {
        foreach ($vulnerabilitiesData as $vulnerabilityData) {
            $vulnerability = $this->importCacheHelper
                ->getCachedObjectByKeyAndId('vulnerabilities', $vulnerabilityData['uuid']);
            if ($vulnerability === null) {
                /* The code should be unique. */
                $vulnerabilityData['code'] = \in_array(
                    $vulnerabilityData['code'],
                    $this->importCacheHelper->getCachedDataByKey('vulnerabilities_codes'),
                    true
                ) ? $vulnerabilityData['code'] . '-' . time() : $vulnerabilityData['code'];

                $vulnerability = (new Vulnerability())
                    ->setUuid($vulnerabilityData['uuid'])
                    ->setAnr($anr)
                    ->setLabels($vulnerabilityData)
                    ->setDescriptions($vulnerabilityData)
                    ->setCode($vulnerabilityData['code'])
                    ->setMode($vulnerabilityData['mode'])
                    ->setStatus($vulnerabilityData['status'])
                    ->setCreator($this->connectedUser->getEmail());

                $this->vulnerabilityTable->saveEntity($vulnerability, false);

                $this->importCacheHelper->addDataToCache('vulnerabilities', $vulnerability, $vulnerability->getUuid());
                $this->importCacheHelper->addDataToCache('vulnerabilities_codes', $vulnerability->getCode());
            }
        }
    }

    private function processAmvsData(array $amvsData, Anr $anr, Asset $asset): void
    {
        $this->importCacheHelper->prepareAmvsCacheData($anr);
        $this->importCacheHelper->prepareInstancesByAssetCacheData($anr);
        /** @var Instance[]|null $instances */
        $instances = $this->importCacheHelper->getCachedObjectByKeyAndId('instancesByAssets', $asset->getUuid());

        foreach ($amvsData as $amvUuid => $amvData) {
            $amv = $this->importCacheHelper->getCachedObjectByKeyAndId('amvs', $amvUuid);
            if ($amv === null) {
                $amv = (new Amv())
                    ->setUuid($amvUuid)
                    ->setAnr($anr)
                    ->setAsset($asset)
                    ->setMeasures(null);

                /** @var Threat|null $threat */
                $threat = $this->importCacheHelper->getCachedObjectByKeyAndId('threats', $amvData['threat']);
                /** @var Vulnerability|null $vulnerability */
                $vulnerability = $this->importCacheHelper
                    ->getCachedObjectByKeyAndId('vulnerabilities', $amvData['vulnerability']);
                if ($threat === null || $vulnerability === null) {
                    throw new Exception(sprintf(
                        'The import file is malformed. AMV\'s "%s" threats or vulnerability was not processed before.',
                        $amvUuid
                    ));
                }

                $amv->setThreat($threat)->setVulnerability($vulnerability);

                $this->amvTable->saveEntity($amv, false);

                $this->importCacheHelper->addDataToCache('amvs', $amv, $amv->getUuid());

                if ($instances !== null) {
                    foreach ($instances as $instance) {
                        $instanceRisk = (new InstanceRisk())
                            ->setAnr($anr)
                            ->setAmv($amv)
                            ->setAsset($asset)
                            ->setInstance($instance)
                            ->setThreat($threat)
                            ->setVulnerability($vulnerability);

                        $this->instanceRiskTable->saveEntity($instanceRisk, false);
                    }
                }
            }

            if (!empty($amvData['measures'])) {
                $this->processMeasuresAndReferentialsData($amvData['measures'], $anr, $amv);
            }
        }

        $this->importCacheHelper->prepareAmvsByAssetsCacheData($anr);
        /** @var Amv[]|null $oldAmvs */
        $oldAmvs = $this->importCacheHelper->getCachedObjectByKeyAndId('amvsByAssets', $asset->getUuid());
        foreach ($oldAmvs as $oldAmv) {
            if (!isset($amvsData[$oldAmv->getUuid()])) {
                /** Set instance risks to specific. and delete amvs leter */

                $instanceRisks = $this->instanceRiskTable->findByAmv($amv);

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
                    $instanceRisk->setAnr($anr);
                    $this->instanceRiskTable->saveEntity($instanceRisk, false);
                }
                $this->instanceRiskTable->getDb()->flush();

                $amvsToDelete[] = $amv;
                $this->importCacheHelper->removeDataFromCache('amvs', $amv, $amv->getUuid());
            }
        }

        if (!empty($amvsToDelete)) {
            $this->amvTable->deleteEntities($amvsToDelete);
        }
    }

    private function processMeasuresAndReferentialsData(array $measuresData, Anr $anr, Amv $amv): void
    {
        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;
        $this->importCacheHelper->prepareMeasuresAndReferentialCacheData($anr);
        foreach ($measuresData as $measureUuid) {
            $measure = $this->importCacheHelper->getCachedObjectByKeyAndId('measures', $measureUuid);
            if ($measure === null) {
                /*
                 * Backward compatibility.
                 * Prior v2.10.3 we did not set referential data when exported.
                 */
                $referentialUuid = $data['measures'][$measureUuid]['referential']['uuid']
                    ?? $data['measures'][$measureUuid]['referential'];

                $referential = $this->importCacheHelper
                    ->getCachedObjectByKeyAndId('referentials', $referentialUuid);

                // For backward compatibility issue.
                if ($referential === null
                    && isset($data['measures'][$measureUuid]['referential'][$labelKey])
                ) {
                    $referential = (new Referential())
                        ->setAnr($anr)
                        ->setUuid($data['measures'][$measureUuid]['referential']['uuid'])
                        ->{'setLabel' . $languageIndex}(
                            $data['measures'][$measureUuid]['referential'][$labelKey]
                        );
                    $this->referentialTable->saveEntity($referential, false);

                    $this->importCacheHelper
                        ->addDataToCache('referentials', $referential, $referential->getUuid());
                }

                // For backward compatibility issue.
                if ($referential === null) {
                    continue;
                }

                $category = $this->soaCategoryTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    $labelKey => $data['measures'][$measureUuid]['category'][$labelKey],
                    'referential' => [
                        'anr' => $anr->getId(),
                        'uuid' => $referential->getUuid(),
                    ],
                ]);
                if (empty($category)) {
                    $category = (new SoaCategory())
                        ->setAnr($anr)
                        ->setReferential($referential)
                        ->{'setLabel' . $languageIndex}(
                            $data['measures'][$measureUuid]['category'][$labelKey]
                        );
                    /** @var SoaCategoryTable $soaCategoryTable */
                    $this->soaCategoryTable->saveEntity($category, false);
                } else {
                    $category = current($category);
                }

                $measure = (new Measure())
                    ->setAnr($anr)
                    ->setUuid($measureUuid)
                    ->setCategory($category)
                    ->setReferential($referential)
                    ->setCode($data['measures'][$measureUuid]['code'])
                    ->setLabels($data['measures'][$measureUuid]);
            }

            $measure->addAmv($amv);

            $this->measureTable->saveEntity($measure, false);

            $this->importCacheHelper->addDataToCache('measures', $measure, $measure->getUuid());
        }
    }
}
