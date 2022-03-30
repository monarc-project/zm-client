<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
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
use Monarc\FrontOffice\Table\VulnerabilityTable;

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
        SoaCategoryTable $soaCategoryTable
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
    }

    public function importFromArray($monarcVersion, array $data, Anr $anr): ?Asset
    {
        if (!isset($data['type']) || $data['type'] !== 'asset') {
            return null;
        }

        if (version_compare($monarcVersion, '2.8.2') < 0) {
            throw new Exception('Import of files exported from MONARC v2.8.1 or lower are not supported.'
                . ' Please contact us for more details.'
            );
        }

        $asset = $this->assetTable->findByAnrAndUuid($anr, $data['asset']['uuid']);
        if ($asset === null) {
            $asset = (new Asset())
                ->setUuid($data['asset']['uuid'])
                ->setAnr($anr)
                ->setLabels($data['asset'])
                ->setDescriptions($data['asset'])
                ->setStatus($data['asset']['status'] ?? 1)
                ->setMode($data['asset']['mode'] ?? 0)
                ->setType($data['asset']['type'])
                ->setCode($data['asset']['code']);

            $this->assetTable->saveEntity($asset);
        }

        if (empty($data['amvs'])) {
            return $asset;
        }

        $languageIndex = $anr->getLanguage();
        $localThemes = [];
        $themes = $this->themeTable->findByAnr($anr);
        foreach ($themes as $theme) {
            $localThemes[$theme->getLabel($languageIndex)] = $theme;
        }

        /*
         * Threats
         */
        foreach ($data['threats'] as $threatUuid => $valueThreat) {
            try {
                $threat = $this->threatTable->findByAnrAndUuid($anr, $threatUuid);
                $this->cachedData['threats'][$threat->getUuid()] = $threat;
            } catch (EntityNotFoundException $exception) {
                $theme = null;
                if (isset($localThemes[$data['themes'][$valueThreat['theme']]['label' . $languageIndex]])) { //theme exists
                    $theme = $localThemes[$data['themes'][$valueThreat['theme']]['label' . $languageIndex]];
                } elseif (isset($data['themes'][$valueThreat['theme']])) {
                    $theme = new Theme();
                    $theme->setLanguage($languageIndex);
                    $theme->exchangeArray($data['themes'][$valueThreat['theme']]);
                    $theme->setAnr($anr);
                    $this->themeTable->saveEntity($theme, false);
                }
                $threat = new Threat();
                $threat->setLanguage($languageIndex);
                // TODO: Can be replaced with setters use, example in InstanceImportService.
                $threat->exchangeArray($valueThreat);
                $threat->setAnr($anr);
                $threat->setTheme($theme);
                $this->threatTable->saveEntity($threat, false);

                $this->cachedData['threats'][$threat->getUuid()] = $threat;

                continue;
            }

            if (!empty($valueThreat['theme'])
                && (
                    $threat->getTheme() === null
                    || $threat->getTheme()->getLabel($languageIndex) !== $data['themes'][$valueThreat['theme']]['label' . $languageIndex]
                )
            ) {
                if (isset($localThemes[$data['themes'][$valueThreat['theme']]['label' . $languageIndex]])) {
                    $theme = $localThemes[$data['themes'][$valueThreat['theme']]['label' . $languageIndex]];
                } else {
                    $theme = new Theme();
                    $theme->setLanguage($languageIndex);
                    $theme->exchangeArray($data['themes'][$valueThreat['theme']]);
                    $theme->setAnr($anr);
                    $this->themeTable->saveEntity($theme, false);
                }
                $threat->setTheme($theme);
                $this->threatTable->saveEntity($threat, false);
            }
        }

        /*
         * Vulnerabilities
         */
        $vulnerabilitiesUuids = $this->vulnerabilityTable->findUuidsByAnr($anr);
        foreach ($data['vuls'] as $valueVul) {
            if (!isset($this->cachedData['vulnerabilities'][(string)$valueVul['uuid']])
                && !\in_array((string)$valueVul['uuid'], $vulnerabilitiesUuids, true)
            ) {
                $vulnerability = new Vulnerability();
                $vulnerability->setLanguage($languageIndex);
                $vulnerability->exchangeArray($valueVul);
                $vulnerability->setAnr($anr);
                $this->vulnerabilityTable->saveEntity($vulnerability, false);
                $vulnerabilitiesUuids[] = $vulnerability->getUuid();

                $this->cachedData['vulnerabilities'][$vulnerability->getUuid()] = $vulnerability;
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
                $labelName = 'label' . $languageIndex;
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
                            && isset($data['measures'][$measureUuid]['referential'][$labelName])
                        ) {
                            $referential = (new Referential())
                                ->setAnr($anr)
                                ->setUuid($data['measures'][$measureUuid]['referential']['uuid'])
                                ->{'setLabel' . $languageIndex}($data['measures'][$measureUuid]['referential'][$labelName]);
                            $this->referentialTable->saveEntity($referential);
                        }

                        // For backward compatibility issue.
                        if ($referential === null) {
                            continue;
                        }

                        $category = $this->soaCategoryTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            $labelName => $data['measures'][$measureUuid]['category'][$labelName],
                            'referential' => [
                                'anr' => $anr->getId(),
                                'uuid' => $referential->getUuid(),
                            ],
                        ]);
                        if (empty($category)) {
                            $category = (new SoaCategory())
                                ->setAnr($anr)
                                ->setReferential($referential)
                                ->{'setLabel' . $languageIndex}($data['measures'][$measureUuid]['category'][$labelName]);
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
}
