<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\SoaCategory;
use Monarc\FrontOffice\Model\Entity\Threat;
use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Entity\Vulnerability;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\MeasureTable;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;
use Monarc\FrontOffice\Model\Table\ReferentialTable;
use Monarc\FrontOffice\Model\Table\SoaCategoryTable;
use Monarc\FrontOffice\Table;

class AssetImportService
{
    /** @var object[][] */
    private array $cachedData = [];

    private Table\AssetTable $assetTable;

    private Table\ThemeTable $themeTable;

    private Table\ThreatTable $threatTable;

    private Table\VulnerabilityTable $vulnerabilityTable;

    private MeasureTable $measureTable;

    private Table\AmvTable $amvTable;

    private MonarcObjectTable $monarcObjectTable;

    private InstanceTable $instanceTable;

    private InstanceRiskTable $instanceRiskTable;

    private ReferentialTable $referentialTable;

    private SoaCategoryTable $soaCategoryTable;

    private AnrAssetService $anrAssetService;

    private AnrThreatService $anrThreatService;

    private AnrThemeService $anrThemeService;

    private AnrVulnerabilityService $anrVulnerabilityService;

    private User $connectedUser;

    public function __construct(
        Table\AssetTable $assetTable,
        Table\ThemeTable $themeTable,
        Table\ThreatTable $threatTable,
        Table\VulnerabilityTable $vulnerabilityTable,
        MeasureTable $measureTable,
        Table\AmvTable $amvTable,
        MonarcObjectTable $monarcObjectTable,
        InstanceTable $instanceTable,
        InstanceRiskTable $instanceRiskTable,
        ReferentialTable $referentialTable,
        SoaCategoryTable $soaCategoryTable,
        AnrAssetService $anrAssetService,
        AnrThreatService $anrThreatService,
        AnrThemeService $anrThemeService,
        AnrVulnerabilityService $anrVulnerabilityService,
        ConnectedUserService $connectedUserService
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
            throw new Exception(
                'Import of files exported from MONARC v2.8.1 or lower are not supported.'
                . ' Please contact us for more details.'
            );
        }

        /** @var Asset $asset */
        $asset = $this->assetTable->findByUuidAndAnr($data['asset']['uuid'], $anr);
        if ($asset === null) {
            $asset = $this->assetTable->findByAnrAndCode($anr, $data['asset']['code']);

            /* The code should be unique. */
            $assetCode = $asset === null ? $data['asset']['code'] : $asset->getCode() . '-' . time();

            $asset = (new Asset())
                ->setUuid($data['asset']['uuid'])
                ->setAnr($anr)
                ->setLabels($data['asset'])
                ->setDescriptions($data['asset'])
                ->setStatus($data['asset']['status'] ?? 1)
                ->setMode($data['asset']['mode'] ?? 0)
                ->setType($data['asset']['type'])
                ->setCode($assetCode);

            $this->assetTable->save($asset);
            $asset = $this->anrAssetService->create($anr, $data['asset']);
        }

        if (empty($data['amvs'])) {
            return $asset;
        }

        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;

        $localThemes = [];
        $themes = $this->themeTable->findByAnr($anr);
        foreach ($themes as $theme) {
            $localThemes[$theme->getLabel($languageIndex)] = $theme;
        }

        /* Threats */
        $threatsUuidsAndCodes = $this->threatTable->findUuidsAndCodesByAnr($anr);
        $threatsUuids = array_column($threatsUuidsAndCodes, 'uuid');
        $threatsCodes = array_column($threatsUuidsAndCodes, 'code');
        foreach ($data['threats'] as $threatUuid => $threatData) {
            if (isset($this->cachedData['threats'][$threatData['uuid']])) {
                continue;
            }

            if (\in_array((string)$threatData['uuid'], $threatsUuids, true)) {
                /** @var Threat|null $threat */
                $threat = $this->threatTable->findByUuidAndAnr($threatUuid, $anr, false);

                /* Validate Theme and set if it's empty for the threat and passed in the data. */
                $currentTheme = $threat->getTheme();
                if (!empty($threatData['theme'])
                    && ($currentTheme === null
                        || $currentTheme->getLabel($languageIndex) !== $data['themes'][$threatData['theme']][$labelKey]
                    )
                ) {
                    $theme = $localThemes[$data['themes'][$threatData['theme']][$labelKey]]
                        ?? $this->anrThemeService->create($anr, $data['themes'][$threatData['theme']], false);

                    $threat->setTheme($theme);
                    $this->threatTable->save($threat, false);
                }
            } else {
                $threatData['theme'] = $localThemes[$data['themes'][$threatData['theme']][$labelKey]]
                    ?? $this->anrThemeService->create($anr, $data['themes'][$threatData['theme']], false);

                if (\in_array($threatData['code'], $threatsCodes, true)) {
                    $threatData['code'] .= '-' . time();
                }

                // TODO: refactor the codes validation with use cache
                $threat = $this->anrThreatService->create($anr, $threatData, false);
            }

            $this->cachedData['threats'][$threat->getUuid()] = $threat;
        }
        unset($threatsUuidsAndCodes, $threatsUuids, $threatsCodes);

        /* Vulnerabilities */
        $vulnerabilitiesUuidsAndCodes = $this->vulnerabilityTable->findUuidsAndCodesByAnr($anr);
        $vulnerabilitiesUuids = array_column($vulnerabilitiesUuidsAndCodes, 'uuid');
        $vulnerabilitiesCodes = array_column($vulnerabilitiesUuidsAndCodes, 'code');
        foreach ($data['vuls'] as $vulnerabilityData) {
            if (!isset($this->cachedData['vulnerabilities'][(string)$vulnerabilityData['uuid']])
                && !\in_array((string)$vulnerabilityData['uuid'], $vulnerabilitiesUuids, true)
            ) {
                if (\in_array($vulnerabilityData['code'], $vulnerabilitiesCodes, true)) {
                    $vulnerabilityData['code'] .= '-' . time();
                }

                $vulnerability = $this->anrVulnerabilityService->create($anr, $vulnerabilityData, false);
                $vulnerabilitiesUuids[] = $vulnerability->getUuid();
                $this->cachedData['vulnerabilities'][$vulnerability->getUuid()] = $vulnerability;
            }
        }
        $this->vulnerabilityTable->flush();
        // TODO: the same as for threats, refactor the codes validation...
        unset($vulnerabilitiesUuidsAndCodes, $vulnerabilitiesUuids, $vulnerabilitiesCodes);

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

                if (!isset($this->cachedData['threats'][$valueAmv['threat']])) {
                    $this->cachedData['threats'][$valueAmv['threat']] =
                        $this->threatTable->findByUuidAndAnr($valueAmv['threat'], $anr);
                }
                $amv->setThreat($this->cachedData['threats'][$valueAmv['threat']]);

                if (!isset($this->cachedData['vulnerabilities'][$valueAmv['vulnerability']])) {
                    $this->cachedData['vulnerabilities'][$valueAmv['vulnerability']] =
                        $this->vulnerabilityTable->findByUuidAndAnr($valueAmv['vulnerability'], $anr);
                }
                $amv->setVulnerability($this->cachedData['vulnerabilities'][$valueAmv['vulnerability']]);

                $this->amvTable->saveEntity($amv, false);

                $newAmvs[$keyAmv] = $amv;
                $currentAmvs[$keyAmv] = $amv;

                // Update instances.
                $objects = $this->monarcObjectTable->findByAnrAndAsset($anr, $asset);
                foreach ($objects as $object) {
                    $instances = $this->instanceTable->findByAnrAndObject($anr, $object);
                    foreach ($instances as $instance) {
                        $instanceRisk = new InstanceRisk();
                        $instanceRisk->setLanguage($languageIndex);
                        $instanceRisk->setAnr($anr);
                        $instanceRisk->setAmv($amv);
                        $instanceRisk->setAsset($asset);
                        $instanceRisk->setInstance($instance);

                        $instanceRisk->setThreat($this->cachedData['threats'][$valueAmv['threat']]);
                        $instanceRisk->setVulnerability(
                            $this->cachedData['vulnerabilities'][$valueAmv['vulnerability']]
                        );

                        $this->instanceRiskTable->saveEntity($instanceRisk, false);
                    }
                    $this->instanceRiskTable->getDb()->flush();
                }
            }

            if (!empty($valueAmv['measures'])) {
                foreach ($valueAmv['measures'] as $measureUuid) {
                    $measure = $this->cachedData['measures'][$measureUuid]
                        ?? $this->measureTable->findByAnrAndUuid($anr, $measureUuid);
                    if ($measure === null) {
                        /*
                         * Backward compatibility.
                         * Prior v2.10.3 we did not set referential data when exported.
                         */
                        $referentialData = $data['measures'][$measureUuid]['referential'];
                        $referentialUuid = $referentialData['uuid'] ?? $referentialData;

                        $referential = $this->referentialTable->findByAnrAndUuid($anr, $referentialUuid);

                        // For backward compatibility issue.
                        if ($referential === null && isset($referentialData[$labelKey])) {
                            $referential = (new Referential())
                                ->setAnr($anr)
                                ->setUuid($referentialUuid)
                                ->setLabels([$labelKey => $referentialData[$labelKey]]);
                            $this->referentialTable->saveEntity($referential);
                        }

                        // For backward compatibility issue.
                        if ($referential === null) {
                            continue;
                        }
                        $categoryLabel = $data['measures'][$measureUuid]['category'][$labelName];
                        $category = $this->soaCategoryTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            $labelName => $categoryLabel,
                            'referential' => [
                                'anr' => $anr->getId(),
                                'uuid' => $referential->getUuid(),
                            ],
                        ]);
                        if (empty($category)) {
                            $category = (new SoaCategory())
                                ->setAnr($anr)
                                ->setReferential($referential)
                                ->setLabels([$labelName => $categoryLabel]);
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

                    $this->cachedData['measures'][$measureUuid] = $measure;
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
            $this->amvTable->removeList($amvsToDelete);
        }

        return $asset;
    }

    /**
     * @return object[][]
     */
    public function getCachedDataByKey(string $key): array
    {
        return $this->cachedData[$key] ?? [];
    }
}
