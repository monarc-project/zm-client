<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;
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

        $asset = $this->importCacheHelper->getCachedObjectByKeyAndId('assets', $data['asset']['uuid']);
        if ($asset === null) {
            /* The code should be unique. */
            $assetCode = \in_array(
                $data['asset']['code'],
                $this->importCacheHelper->getCachedDataByKey('assets_codes'),
                true
            ) ? $data['asset']['code'] . '-' . time() : $data['asset']['code'];

            $asset = (new Asset())
                ->setUuid($data['asset']['uuid'])
                ->setAnr($anr)
                ->setLabels($data['asset'])
                ->setDescriptions($data['asset'])
                ->setStatus($data['asset']['status'] ?? 1)
                ->setMode($data['asset']['mode'] ?? 0)
                ->setType($data['asset']['type'])
                ->setCode($assetCode);

            $this->assetTable->saveEntity($asset);

            $this->importCacheHelper->addDataToCache('assets', $asset, $asset->getUuid());
            $this->importCacheHelper->addDataToCache('assets_codes', $asset->getCode());
        }

        if (empty($data['amvs'])) {
            return $asset;
        }

        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;

        /* Threats */
        foreach ($data['threats'] as $threatUuid => $threatData) {
            /** @var Threat|null $threat */
            $threat = $this->importCacheHelper->getCachedObjectByKeyAndId('threats', $threatUuid);
            $themeData = $data['themes'][$threatData['theme']] ?? [];
            if ($threat !== null) {
                /* Validate Theme. */
                $currentTheme = $threat->getTheme();
                if (!empty($themeData) && (
                    $currentTheme === null || $currentTheme->getLabel($languageIndex) !== $themeData[$labelKey]
                )) {
                    $theme = $this->importCacheHelper->getCachedObjectByKeyAndId('themes', $themeData[$labelKey]);
                    if ($theme === null) {
                        $theme = $this->getCreatedTheme($anr, $themeData);
                        $this->importCacheHelper->addDataToCache('themes', $theme, $theme->getLabel($languageIndex));
                    }

                    $threat->setTheme($theme);
                    $this->threatTable->saveEntity($threat, false);
                }
            } else {
                $theme = $this->importCacheHelper->getCachedObjectByKeyAndId('themes', $themeData[$labelKey]);
                if ($theme === null) {
                    $theme = $this->getCreatedTheme($anr, $themeData);
                    $this->importCacheHelper->addDataToCache('themes', $theme, $theme->getLabel($languageIndex));
                }

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

                $this->threatTable->saveEntity($threat, false);

                $this->importCacheHelper->addDataToCache('threats', $threat, $threat->getUuid());
                $this->importCacheHelper->addDataToCache('threats_codes', $threat->getCode());
            }
        }

        /* Vulnerabilities. */
        foreach ($data['vuls'] as $vulnerabilityData) {
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
        $this->vulnerabilityTable->getDb()->flush();

        /*
         * AMVs
         */
        $newAmvs = [];
        $currentAmvs = $this->amvTable->findByAnrIndexedByUuid($anr);
        foreach ($data['amvs'] as $keyAmv => $valueAmv) {
            if (isset($currentAmvs[$keyAmv])) { // the amv exists.
                $newAmvs[$keyAmv] = $currentAmvs[$keyAmv];
            } else {
                $amv = new Amv();
                $amv->setLanguage($languageIndex);
                // TODO: we must remove the business logic from the AmvSuperClass::getInputFilter.
                $amv->setDbAdapter($this->amvTable->getDb());
                $amv->exchangeArray($valueAmv);
                $amv->setAnr($anr)
                    ->setAsset($asset)
                    ->setMeasures(null);

                /** @var Threat|null $threat */
                $threat = $this->importCacheHelper->getCachedObjectByKeyAndId('threats', $valueAmv['threat']);
                /** @var Vulnerability|null $vulnerability */
                $vulnerability = $this->importCacheHelper
                    ->getCachedObjectByKeyAndId('vulnerabilities', $valueAmv['vulnerability']);
                if ($threat === null || $vulnerability === null) {
                    throw new Exception(sprintf(
                        'The import file is malformed. AMV\'s "%s" threats or vulnerability was not processed before.',
                        $keyAmv
                    ));
                }

                $amv->setThreat($threat)->setVulnerability($vulnerability);

                $this->amvTable->saveEntity($amv, false);

                $newAmvs[$keyAmv] = $amv;
                $currentAmvs[$keyAmv] = $amv;

                // Update instances.
                // TODO: can be replaced to $asset->getObjects() and $object->getInstances()
                // TODO: perhaps cache can improve the speed.
                foreach ($this->monarcObjectTable->findByAnrAndAsset($anr, $asset) as $object) {
                    foreach ($this->instanceTable->findByAnrAndObject($anr, $object) as $instance) {
                        $instanceRisk = (new InstanceRisk())
                            ->setAnr($anr)
                            ->setAmv($amv)
                            ->setAsset($asset)
                            ->setInstance($instance)
                            ->setThreat($threat)
                            ->setVulnerability($vulnerability);

                        $this->instanceRiskTable->saveEntity($instanceRisk, false);
                    }
                    $this->instanceRiskTable->getDb()->flush();
                }
            }

            if (!empty($valueAmv['measures'])) {
                $this->importCacheHelper->prepareMeasuresAndReferentialCacheData($anr);
                foreach ($valueAmv['measures'] as $measureUuid) {
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
                            $this->referentialTable->saveEntity($referential);

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
                                ->{'setLabel' . $languageIndex}($data['measures'][$measureUuid]['category'][$labelKey]);
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

                    $measure->addAmv($currentAmvs[$keyAmv]);

                    $this->measureTable->saveEntity($measure);

                    $this->importCacheHelper->addDataToCache('measures', $measure, $measure->getUuid());
                }
            }
        }
        $this->amvTable->getDb()->flush();

        // Set old amvs to specific and delete them.
        $amvsToDelete = [];
        /** @var Amv[] $oldAmvs */
        $oldAmvs = $this->amvTable->findByAsset($asset);
        foreach ($oldAmvs as $oldAmv) {
            if (!isset($newAmvs[$oldAmv->getUuid()])) {
                // We fetch the instances risks which contains the amv to set the risk to specific.
                $instanceRisks = $this->instanceRiskTable->findByAmv($oldAmv);

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

                $amvsToDelete[] = $oldAmv;
            }
        }

        if (!empty($amvsToDelete)) {
            $this->amvTable->deleteEntities($amvsToDelete);
        }

        return $asset;
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
}
