<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\Soa;
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
use Monarc\FrontOffice\Model\Table\SoaTable;
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

    /** @var SoaTable */
    private $soaTable;

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
        SoaTable $soaTable
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
        $this->soaTable = $soaTable;
    }

    public function importFromArray($monarcVersion, array $data, Anr $anr): ?Asset
    {
        if (!isset($data['type']) || $data['type'] !== 'asset') {
            return null;
        }

        if (version_compare($monarcVersion, "2.8.2") < 0) {
            return $this->importOfOldVersion($data, $anr);
        }

        $asset = $this->assetTable->findByAnrAndUuid($anr, $data['asset']['uuid']);
        if ($asset === null) {
            $asset = (new Asset())
                ->setUuid($data['asset']['uuid'])
                ->setAnr($anr)
                ->setLabels($data['asset'])
                ->setDescriptions($data['asset'])
                ->setStatus($data['asset']['status'])
                ->setMode($data['asset']['mode'])
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
                            $this->referentialTable->saveEntity($referential, false);
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

                    $this->measureTable->saveEntity($measure, false);

                    $this->cachedData['measures'][$measureUuid] = $measure;
                }
            }
        }
        $this->amvTable->getDb()->flush();

        // Set old amvs to specific and delete them.
        $amvsToDelete = [];
        /** @var Amv[] $oldAmvs */
        $oldAmvs = $this->amvTable->findByAnrAndAsset($anr, $asset);
        foreach ($oldAmvs as $oldAmv) {
            if (!isset($newAmvs[$oldAmv->getUuid()])) {
                // We fetch the instances risks which contains the amv to set the risk to specific.
                $oldIRs = $this->instanceRiskTable->findByAmv($oldAmv);
                foreach ($oldIRs as $oldIR) {
                    $oldIR->setAmv(null);
//                    $oldIR->setAnr(null);
                    $oldIR->setSpecific(InstanceRisk::TYPE_SPECIFIC);
                    $this->instanceRiskTable->saveEntity($oldIR, false);
                }
                $this->instanceRiskTable->getDb()->flush();

                // TODO: check why it happens(ed).
//                foreach ($oldIRs as $oldIR) { //set the value DB because the set amv=null erase the value
//                    $oldIR->set('anr', $anr);
//                    $this->instanceRiskTable->saveEntity($oldIR, false);
//                }
//                $this->instanceRiskTable->getDb()->flush();

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

    /**
     * @deprecated suppose to be removed since v2.10.4
     *
     * @throws Exception
     */
    private function importOfOldVersion(array $data, Anr $anr): Asset
    {
        $languageIndex = $anr->getLanguage();

        $asset = current($this->assetTable->getEntityByFields([
            'anr' => $anr->getId(),
            'code' => $data['asset']['code'],
        ]));
        if (!empty($asset)) {
            $idAsset = $asset->getUuid();
        } else {
            $asset = new Asset();
            $asset->setDbAdapter($this->assetTable->getDb());
            $asset->setLanguage($languageIndex);
            $asset->exchangeArray($data['asset']);
            $asset->set('anr', $anr->getId());
            $this->setDependencies($asset, ['anr']);
            $idAsset = $this->assetTable->saveEntity($asset);
        }

        // Match the AMV Links with the asset
        $localAmv = [];
        if (!empty($data['amvs']) && !empty($idAsset)) {
            $localThemes = [];
            $themes = $this->themeTable->getEntityByFields(['anr' => $anr->getId()]);
            foreach ($themes as $t) {
                $localThemes[$t->get('label' . $languageIndex)] = $t->getId();
            }
            unset($themes);

            foreach ($data['amvs'] as $amvArray) {
                $amvData = [
                    'asset' => $idAsset,
                    'anr' => $anr->getId(),
                    'status' => $amvArray['status'],
                ];
                if (isset($data['threats'][$amvArray['threat']])) { // Threats
                    if (is_array($data['threats'][$amvArray['threat']])) {
                        // Theme
                        $idTheme = null;
                        if (!empty($data['threats'][$amvArray['threat']]['theme']) && !empty($data['themes'][$data['threats'][$amvArray['threat']]['theme']])) {
                            $t = $data['themes'][$data['threats'][$amvArray['threat']]['theme']];
                            if (isset($localThemes[$t['label' . $languageIndex]])) {
                                $idTheme = $data['themes'][$data['threats'][$amvArray['threat']]['theme']]['newid'] = $localThemes[$t['label' . $languageIndex]];
                            } elseif (!empty($data['themes'][$data['threats'][$amvArray['threat']]['theme']]['newid'])) {
                                $idTheme = $localThemes[$t['label' . $languageIndex]] = $data['themes'][$data['threats'][$amvArray['threat']]['theme']]['newid'];
                            } else {
                                $theme = new Theme();
                                $theme->setDbAdapter($this->themeTable->getDb());
                                $theme->setLanguage($languageIndex);
                                $t['id'] = null;
                                $theme->exchangeArray($t);
                                $theme->set('anr', $anr->getId());
                                $this->setDependencies($theme, ['anr']);
                                $idTheme = $this->themeTable->saveEntity($theme);
                                $localThemes[$t['label' . $languageIndex]] = $data['themes'][$data['threats'][$amvArray['threat']]['theme']]['newid'] = $idTheme;
                            }
                        }

                        $threat = $this->threatTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            'code' => $data['threats'][$amvArray['threat']]['code'],
                        ]);
                        if ($threat) {
                            $threat = current($threat);
                            $data['threats'][$amvArray['threat']] = $threat->getUuid();

                            // Update du theme
                            $theme = $threat->getTheme();
                            $oldTheme = empty($theme) ? null : $theme->getId();
                            if ($oldTheme != $idTheme) {
                                $threat->setDbAdapter($this->threatTable->getDb());
                                $threat->set('theme', $idTheme);
                                $this->setDependencies($threat, ['anr', 'theme']);
                                $this->threatTable->saveEntity($threat);
                            }
                        } else {
                            $threat = new Threat();
                            $threat->setDbAdapter($this->threatTable->getDb());
                            $threat->setLanguage($languageIndex);
                            $data['threats'][$amvArray['threat']]['id'] = null;

                            $data['threats'][$amvArray['threat']]['theme'] = $idTheme;
                            $threat->exchangeArray($data['threats'][$amvArray['threat']]);
                            $this->setDependencies($threat, ['theme']);
                            $threat->setAnr($anr);
                            $this->threatTable->saveEntity($threat);

                            $this->cachedData['threats'][$amvArray['threat']] = $threat;
                            $data['threats'][$amvArray['threat']] = $threat;
                        }
                    }
                    $amvData['threat'] = $data['threats'][$amvArray['threat']];
                }

                if (isset($data['vuls'][$amvArray['vulnerability']])) { // Vulnerabilities
                    if (is_array($data['vuls'][$amvArray['vulnerability']])) {
                        $vul = $this->vulnerabilityTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            'code' => $data['vuls'][$amvArray['vulnerability']]['code'],
                        ]);
                        if ($vul) {
                            $vul = current($vul);
                            $data['vuls'][$amvArray['vulnerability']] = $vul->getUuid();
                        } else {
                            $vul = new Vulnerability();
                            $vul->setDbAdapter($this->vulnerabilityTable->getDb());
                            $vul->setLanguage($languageIndex);
                            $data['vuls'][$amvArray['vulnerability']]['id'] = null;
                            $vul->exchangeArray($data['vuls'][$amvArray['vulnerability']]);
                            $this->setDependencies($vul, ['anr']);
                            $vul->setAnr($anr);
                            $this->vulnerabilityTable->saveEntity($vul);

                            $this->cachedData['vulnerabilities'][$amvArray['vulnerability']] = $vul;
                            $data['vuls'][$amvArray['vulnerability']] = $vul;
                        }
                    }
                    $amvData['vulnerability'] = $data['vuls'][$amvArray['vulnerability']];
                }
                if (array_key_exists('measure1', $amvArray)) { //old version without uuid
                    //we need to create ISO 27002, we check if the common if it's present or not
                    $referential = false;
                    $referentialCli = current($this->referentialTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'uuid' => '98ca84fb-db87-11e8-ac77-0800279aaa2b',
                    ]));
                    if (!$referentialCli) {
                        // TODO: this will not work, we need to fetch it from common.
                        $referential = (new Referential())
                            ->setUuid('98ca84fb-db87-11e8-ac77-0800279aaa2b')
                            ->setLabel1('ISO 27002')
                            ->setLabel2('ISO 27002')
                            ->setLabel3('ISO 27002')
                            ->setLabel4('ISO 27002');
                    }
                    if ($referential) {
                        $measures = $referential->getMeasures();
                        $referential->setMeasures(null);

                        // duplicate the referential
                        $newReferential = new Referential($referential);
                        $newReferential->setAnr($anr);

                        // duplicate categories
                        $categoryNewIds = [];
                        $category = $referential->getCategories();
                        foreach ($category as $cat) {
                            $newCategory = new SoaCategory($cat);
                            $newCategory->set('id', null);
                            $newCategory->setAnr($anr);
                            $newCategory->setMeasures(null);
                            $newCategory->setReferential($newReferential);
                            $categoryNewIds[$cat->id] = $newCategory;
                        }

                        $newReferential->setCategories($categoryNewIds);

                        // duplicate the measures
                        foreach ($measures as $measure) {
                            // duplicate and link the measures to the current referential
                            $newMeasure = new Measure($measure);
                            $newMeasure->setAnr($anr);
                            $newMeasure->setReferential($newReferential);
                            $newMeasure->setCategory($categoryNewIds[$measure->getCategory()->getId()]);
                            $newMeasure->setRolfRisks(new ArrayCollection());
                            $newMeasure->setAmvs(new ArrayCollection()); // need to initialize the amvs link
                            $newMeasure->setMeasuresLinked(new ArrayCollection()); //old analysis can't have measuresLinked
                            $this->measureTable->saveEntity($newMeasure, false);
                            $newSoa = new Soa();
                            $newSoa->setAnr($anr);
                            $newSoa->setMeasure($newMeasure);
                            $this->soaTable->saveEntity($newSoa, false);
                        }

                        $this->measureTable->getDb()->flush();
                    }

                    for ($i = 1; $i <= 3; $i++) {
                        if (isset($data['measures'][$amvArray['measure' . $i]])) { // Measure 1 / 2 / 3
                            if (is_array($data['measures'][$amvArray['measure' . $i]])) {
                                $measure = $this->measureTable->getEntityByFields([
                                    'anr' => $anr->getId(),
                                    'code' => $data['measures'][$amvArray['measure' . $i]]['code'],
                                ]);
                                if ($measure) {
                                    $measure = current($measure);
                                    $data['measures'][$amvArray['measure' . $i]] = $measure->getUuid();
                                }
                            }
                            $amvData['measures'][] = $data['measures'][$amvArray['measure' . $i]];
                        }
                    }
                }

                $amvTest = current($this->amvTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'asset' => ['anr' => $anr->getId(), 'uuid' => $amvData['asset']],
                    'threat' => ['anr' => $anr->getId(), 'uuid' => $amvData['threat']],
                    'vulnerability' => ['anr' => $anr->getId(), 'uuid' => $amvData['vulnerability']],
                ]));
                if (empty($amvTest)) { // on test que cet AMV sur cette ANR n'existe pas
                    $amv = new Amv();
                    $amv->setDbAdapter($this->amvTable->getDb());
                    $amv->setLanguage($languageIndex);
                    $measuresAmvs = (array_key_exists('measures', $amvData)) ? $amvData['measures'] : null;
                    unset($amvData['measures']);
                    $amv->exchangeArray($amvData, true);
                    $this->setDependencies($amv, ['anr', 'asset', 'threat', 'vulnerability',]);
                    $idAmv = $this->amvTable->saveEntity($amv);
                    if (isset($amvArray['measures'])) { //version with uuid
                        foreach ($amvArray['measures'] as $m) {
                            try {
                                $measure = $this->measureTable->getEntity([
                                    'anr' => $anr->getId(),
                                    'uuid' => $m,
                                ]);
                                $measure->addAmv($amv);
                            } catch (Exception $e) {
                            }
                        }
                    } else {
                        if (isset($measuresAmvs)) { // old version without uuid
                            foreach ($measuresAmvs as $m) {
                                try {
                                    $measure = $this->measureTable->getEntity([
                                        'anr' => $anr->getId(),
                                        'uuid' => $m,
                                    ]);
                                    $measure->addAmv($amv);
                                } catch (Exception $e) {
                                }
                            }
                        }
                    }
                    $localAmv[] = $idAmv;

                    // On met à jour les instances
                    $objects = $this->monarcObjectTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'asset' => [
                            'anr' => $anr->getId(),
                            'uuid' => $idAsset,
                        ],
                    ]);
                    foreach ($objects as $object) {
                        $instances = $this->instanceTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            'object' => [
                                'anr' => $anr->getId(),
                                'uuid' => $object->getUuid(),
                            ],
                        ]);
                        $i = 1;
                        $nbInstances = count($instances);
                        foreach ($instances as $instance) {
                            $instanceRisk = new InstanceRisk();
                            $instanceRisk->setLanguage($languageIndex);
                            $instanceRisk->setDbAdapter($this->instanceRiskTable->getDb());
                            $instanceRisk->setAnr($anr);
                            $instanceRisk->set('amv', $idAmv);
                            $instanceRisk->set('asset', $amvData['asset']);
                            $instanceRisk->set('instance', $instance);
                            $instanceRisk->set('threat', $amvData['threat']);
                            $instanceRisk->set('vulnerability', $amvData['vulnerability']);
                            $this->setDependencies($instanceRisk, ['amv', 'asset', 'threat', 'vulnerability']);

                            $this->instanceRiskTable->saveEntity($instanceRisk, $i === $nbInstances);
                            $i++;
                        }
                    }
                } else {
                    $localAmv[] = $amvTest->getUuid();
                    if (isset($amvArray['measures'])) { //version with uuid
                        foreach ($amvArray['measures'] as $m) {
                            try {
                                $measure = $this->measureTable->getEntity([
                                    'anr' => $anr->getId(),
                                    'uuid' => $m,
                                ]);
                                $measure->addAmv($amvTest);
                            } catch (Exception $e) {
                            }
                        }
                    } else {
                        if (isset($amvData['measures'])) { //old version before uuid
                            foreach ($amvData['measures'] as $m) {
                                try {
                                    $measure = $this->measureTable->getEntity([
                                        'anr' => $anr->getId(),
                                        'uuid' => $m,
                                    ]);
                                    $measure->addAmv($amvTest);
                                } catch (Exception $e) {
                                }
                            }
                        }
                    }
                }
            }
        }

        /*
        On teste si des liens AMVs différents étaient présents, si oui
        on passe les risques liés en spécifiques et on supprime les liens AMVs
        */
        if (empty($localAmv)) {
            $risks = $this->instanceRiskTable->getEntityByFields([
                'asset' => ['anr' => $anr->getId(), 'uuid' => $idAsset],
                'anr' => $anr->getId(),
                'amv' => ['op' => '!=', 'value' => ['anr' => $anr->getId(), 'uuid' => null]],
            ]);
        } else {
            $risks = $this->instanceRiskTable->getEntityByFields([
                'asset' => ['anr' => $anr->getId(), 'uuid' => $idAsset],
                'anr' => $anr->getId(),
                'amv' => ['op' => 'NOT IN', 'value' => ['anr' => $anr->getId(), 'uuid' => $localAmv]],
            ]);
        }
        if (!empty($risks)) {
            $amvs = [];
            foreach ($risks as $a) {
                $amv = $a->getAmv();
                if (!empty($amv)) {
                    $amvs[] = ['anr' => $anr->getId(), 'uuid' => $amv->getUuid()];
                    $a->set('amv', null);
                    $a->set('anr', null);
                    $this->instanceRiskTable->saveEntity($a);
                    $a->setAnr($anr); //TO IMPROVE we set the anr because it's delete by the line before amv = [uuid + anr]
                    $a->set('specific', 1);
                    $this->instanceRiskTable->saveEntity($a);
                }
            }
            if (!empty($amvs)) {
                $this->amvTable->deleteList($amvs);
            }
        }
        if (empty($risks)) {
            $amvs = $this->amvTable->getEntityByFields([
                'asset' => ['anr' => $anr->getId(), 'uuid' => $idAsset],
                'uuid' => ['op' => 'NOT IN', 'value' => $localAmv],
            ]);
            $idsAmv = [];
            foreach ($amvs as $amv) {
                $idsAmv[] = ['anr' => $anr->getId(), 'uuid' => $amv->getUuid()];
            }
            if (!empty($idsAmv)) {
                $this->amvTable->deleteList($idsAmv);
            }
        }

        return $asset;
    }
}
