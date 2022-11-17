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

class AssetImportService
{
    /** @var array|object[] */
    private $cachedData;

    /** @var AssetTable */
    private $assetTable;

    /** @var ThemeTable */
    private $themeTable;

    /** @var ThreatTable */
    private $threatTable;

    /** @var VulnerabilityTable */
    private $vulnerabilityTable;

    /** @var MeasureTable */
    private $measureTable;

    /** @var AmvTable */
    private $amvTable;

    /** @var MonarcObjectTable */
    private $monarcObjectTable;

    /** @var InstanceTable */
    private $instanceTable;

    /** @var InstanceRiskTable */
    private $instanceRiskTable;

    /** @var ReferentialTable */
    private $referentialTable;

    /** @var SoaCategoryTable */
    private $soaCategoryTable;

    private UserSuperClass $connectedUser;

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

        $asset = $this->assetTable->findByAnrAndUuid($anr, $data['asset']['uuid']);
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

            $this->assetTable->saveEntity($asset);
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

        /*
         * Threats
         */
        $threatsUuidsAndCodes = $this->threatTable->findUuidsAndCodesByAnr($anr);
        $threatsUuids = array_column($threatsUuidsAndCodes, 'uuid');
        $threatsCodes = array_column($threatsUuidsAndCodes, 'code');
        foreach ($data['threats'] as $threatUuid => $threatData) {
            if (isset($this->cachedData['threats'][$threatData['uuid']])) {
                continue;
            }

            if (\in_array((string)$threatData['uuid'], $threatsUuids, true)) {
                $threat = $this->threatTable->findByAnrAndUuid($anr, $threatUuid);
                $this->cachedData['threats'][$threat->getUuid()] = $threat;

                /* Validate Theme. */
                $currentTheme = $threat->getTheme();
                if (!empty($threatData['theme'])
                    && ($currentTheme === null
                        || $currentTheme->getLabel($languageIndex) !== $data['themes'][$threatData['theme']][$labelKey]
                    )
                ) {
                    if (isset($localThemes[$data['themes'][$threatData['theme']][$labelKey]])) {
                        $theme = $localThemes[$data['themes'][$threatData['theme']][$labelKey]];
                    } else {
                        $theme = $this->getCreatedTheme($anr, $data['themes'][$threatData['theme']]);
                    }

                    $threat->setTheme($theme);
                    $this->threatTable->saveEntity($threat, false);
                }
            } else {
                $theme = null;
                if (isset($localThemes[$data['themes'][$threatData['theme']][$labelKey]])) {
                    $theme = $localThemes[$data['themes'][$threatData['theme']][$labelKey]];
                } elseif (isset($data['themes'][$threatData['theme']])) {
                    $theme = $this->getCreatedTheme($anr, $data['themes'][$threatData['theme']]);
                }

                if (\in_array($threatData['code'], $threatsCodes, true)) {
                    $threatData['code'] .= '-' . time();
                }

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

                $this->cachedData['threats'][$threat->getUuid()] = $threat;
            }
        }
        unset($threatsUuidsAndCodes, $threatsUuids, $threatsCodes);

        /*
         * Vulnerabilities
         */
        $vulnerabilitiesUuidsAndCodes = $this->vulnerabilityTable->findUuidsAndCodesByAnr($anr);
        $vulnerabilitiesUuids = array_column($vulnerabilitiesUuidsAndCodes, 'uuid');
        $vulnerabilitiesCodes = array_column($vulnerabilitiesUuidsAndCodes, 'code');
        foreach ($data['vuls'] as $valueVul) {
            if (!isset($this->cachedData['vulnerabilities'][(string)$valueVul['uuid']])
                && !\in_array((string)$valueVul['uuid'], $vulnerabilitiesUuids, true)
            ) {
                if (\in_array($valueVul['code'], $vulnerabilitiesCodes, true)) {
                    $valueVul['code'] .= '-' . time();
                }

                $vulnerability = (new Vulnerability())
                    ->setUuid($valueVul['uuid'])
                    ->setAnr($anr)
                    ->setLabels($valueVul)
                    ->setDescriptions($valueVul)
                    ->setCode($valueVul['code'])
                    ->setMode($valueVul['mode'])
                    ->setStatus($valueVul['status'])
                    ->setCreator($this->connectedUser->getEmail());

                $this->vulnerabilityTable->saveEntity($vulnerability, false);

                $this->cachedData['vulnerabilities'][$valueVul['uuid']] = $vulnerability;
            }
        }
        $this->vulnerabilityTable->getDb()->flush();
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
                        $this->threatTable->findByAnrAndUuid($anr, $valueAmv['threat']);
                }
                $amv->setThreat($this->cachedData['threats'][$valueAmv['threat']]);

                if (!isset($this->cachedData['vulnerabilities'][$valueAmv['vulnerability']])) {
                    $this->cachedData['vulnerabilities'][$valueAmv['vulnerability']] =
                        $this->vulnerabilityTable->findByAnrAndUuid($anr, $valueAmv['vulnerability']);
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
                        $referentialUuid = $data['measures'][$measureUuid]['referential']['uuid']
                            ?? $data['measures'][$measureUuid]['referential'];

                        $referential = $this->referentialTable->findByAnrAndUuid($anr, $referentialUuid);

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
            $this->amvTable->deleteEntities($amvsToDelete);
        }

        return $asset;
    }

    /**
     * @return object[]
     */
    public function getCachedDataByKey(string $key): array
    {
        return $this->cachedData[$key] ?? [];
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
