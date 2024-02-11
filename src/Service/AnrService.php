<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Monarc\Core\Model\Entity as CoreEntity;
use Monarc\Core\Model\Table as CoreDeprecatedTable;
use Monarc\Core\Table as CoreTable;
use Monarc\Core\Service as CoreService;
use Monarc\FrontOffice\Model\Entity;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Table;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Throwable;

class AnrService
{
    /*
    protected $amvTable;
    protected $anrTable;
    protected $assetTable;
    protected $instanceTable;
    protected $instanceConsequenceTable;
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;
    protected $modelTable;
    protected $measureTable;
    protected $MonarcObjectTable;
    protected $objectCategoryTable;
    protected $objectObjectTable;
    protected $rolfRiskTable;
    protected $rolfTagTable;
    protected $scaleTable;
    protected $scaleCommentTable;
    protected $scaleImpactTypeTable;
    protected CoreThreatTable $threatTable;
    protected CoreThemeTable $themeTable;
    protected $vulnerabilityTable;
    protected $questionTable;
    protected $questionChoiceTable;
    protected $soaTable;
    protected $soaCategoryTable;
    protected $referentialTable;
    protected $operationalRiskScaleTable;
    protected $operationalRiskScaleCommentTable;
    protected $translationTable;
    protected $anrMetadatasOnInstancesTable;
    protected $soaScaleCommentTable;


    protected $amvCliTable;
    protected $anrCliTable;
    protected $anrObjectCategoryCliTable;
    protected $assetCliTable;
    protected InstanceTable $instanceCliTable;
    protected $instanceConsequenceCliTable;
    protected $instanceRiskCliTable;
    protected $instanceRiskOpCliTable;
    protected $interviewCliTable;
    protected $measureCliTable;
    protected $objectCliTable;
    protected $objectCategoryCliTable;
    protected $objectObjectCliTable;
    protected RecommendationTable $recommendationTable;
    protected $recommendationHistoryCliTable;
    protected $recommendationRiskCliTable;
    protected $recommendationSetCliTable;
    protected $rolfRiskCliTable;
    protected $rolfTagCliTable;
    protected $scaleCliTable;
    protected $scaleCommentCliTable;
    protected $scaleImpactTypeCliTable;
    protected $snapshotCliTable;
    protected ThreatTable $threatCliTable;
    protected ThemeTable $themeCliTable;
    protected $userCliTable;
    protected $userAnrCliTable;
    protected $vulnerabilityCliTable;
    protected $questionCliTable;
    protected $questionChoiceCliTable;
    protected $soaCategoryCliTable;
    protected $recordCliTable;
    protected $recordActorCliTable;
    protected $recordDataCategoryCliTable;
    protected $recordPersonalDataCliTable;
    protected $recordInternationalTransferCliTable;
    protected $recordProcessorCliTable;
    protected $recordRecipientCliTable;
    protected $referentialCliTable;
    protected $measureMeasureCliTable;
    protected $operationalRiskScaleCliTable;
    protected $operationalRiskScaleTypeCliTable;
    protected $operationalRiskScaleCommentCliTable;
    protected $operationalInstanceRiskScaleCliTable;
    protected $instanceRiskOwnerCliTable;
    protected $translationCliTable;
    protected $anrMetadatasOnInstancesCliTable;
    protected $instanceMetadataCliTable;
    protected $soaScaleCommentCliTable;

    protected $instanceService;
    protected $recordService;
    protected $recordProcessorService;
*/

    /** @var Entity\User */
    private CoreEntity\UserSuperClass $connectedUser;

    private array $cachedData = [];

    // TODO: better to use e.g. anrTable and $coreAnrTable names to distinguish them.
    public function __construct(
        private Table\AnrTable $anrTable,
        private Table\UserAnrTable $userAnrTable,
        private Table\InstanceTable $instanceCliTable,
        private CoreTable\ModelTable $modelTable,
        private DeprecatedTable\ReferentialTable $referentialTable,
        private CoreDeprecatedTable\ReferentialTable $coreReferentialTable,
        private StatsAnrService $statsAnrService,
        private CronTaskService $cronTaskService,
        private CoreService\ConfigService $configService,
        private InstanceRiskOwnerService $instanceRiskOwnerService,
        CoreService\ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(
        $page = 1,
        $limit = 25,
        $order = null,
        $filter = null,
        $filterAnd = null
    ) {
        $isSuperAdmin = $this->connectedUser->hasRole(Entity\UserRole::SUPER_ADMIN_FO);

        // Retrieve connected user anrs
        $filterAnd['id'] = [];
        if (!$isSuperAdmin) {
            // TODO: check if we can avoid the extra db call.
            $anrs = $this->userAnrTable->findByUser($this->connectedUser);
            // getEntityByFields(['user' => $connectedUser->getId()]);
            foreach ($this->anr as $a) {
                $filterAnd['id'][$a->get('anr')->get('id')] = $a->get('anr')->get('id');
            }
        } else {
            $anrs = $this->anrCliTable->fetchAllObject();
            foreach ($anrs as $a) {
                $filterAnd['id'][$a->get('id')] = $a->get('id');
            }
        }

        // Filter out snapshots, as we don't want to show them unless we explicitly ask for them
        /** @var Table\SnapshotTable $snapshotCliTable */
        $snapshotCliTable = $this->get('snapshotCliTable');
        $snapshots = $snapshotCliTable->getEntityByFields(['anr' => $filterAnd['id']]);
        foreach ($snapshots as $snapshot) {
            unset($filterAnd['id'][$snapshot->get('anr')->get('id')]);
        }

        // Retrieve ANRs information
        $anrs = $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        // TODO: check if it can work without this:
        // $user = $userCliTable->findById($connectedUser->getId());
        foreach ($anrs as &$anr) {
            //verify if this is the last current user's anr
            $anr['isCurrentAnr'] = 0;
            if ($this->connectedUser->getCurrentAnr() !== null
                && $anr['id'] === $this->connectedUser->getCurrentAnr()->getId()
            ) {
                $anr['isCurrentAnr'] = 1;
            }

            $lk = current($this->get('userAnrCliTable')->getEntityByFields([
                'user' => $this->connectedUser->getId(),
                'anr' => $anr['id'],
            ]));
            $anr['rwd'] = (empty($lk)) ? -1 : $lk->get('rwd');

            /* Check if the Anr is under background import. */
            $anr['importStatus'] = [];
            if ($anr['status'] === CoreEntity\AnrSuperClass::STATUS_UNDER_IMPORT) {
                $importCronTask = $this->cronTaskService->getLatestTaskByNameWithParam(
                    Entity\CronTask::NAME_INSTANCE_IMPORT,
                    ['anrId' => $anr['id']]
                );
                if ($importCronTask !== null && $importCronTask->getStatus() === Entity\CronTask::STATUS_IN_PROGRESS) {
                    $timeDiff = $importCronTask->getUpdatedAt()->diff(new DateTime());
                    $instancesNumber = $this->instanceCliTable->countByAnrIdFromDate(
                        (int)$anr['id'],
                        $importCronTask->getUpdatedAt()
                    );
                    $anr['importStatus'] = [
                        'executionTime' => $timeDiff->h . ' hours ' . $timeDiff->i . ' min ' . $timeDiff->s . ' sec',
                        'createdInstances' => $instancesNumber,
                    ];
                }
            }
        }

        return $anrs;
    }

    public function getFilteredCount($filter = null, $filterAnd = null)
    {
        return \count($this->getList(1, 0, null, $filter, $filterAnd));
    }

    public function getEntity($id)
    {
        $anr = $this->get('table')->get($id);

        // Retrieve snapshot
        /** @var Table\SnapshotTable $snapshotCliTable */
        $snapshotCliTable = $this->get('snapshotCliTable');
        $anrSnapshot = current($snapshotCliTable->getEntityByFields(['anr' => $id]));

        $anr['isSnapshot'] = 0;
        $anr['snapshotParent'] = null;
        if (!empty($anrSnapshot)) {
            // This is a snapshot, tag it as so
            $anr['isSnapshot'] = 1;
            $anr['rwd'] = 0;
            $anr['snapshotParent'] = $anrSnapshot->get('anrReference')->get('id');
        } else {
            /** @var UserTable $userCliTable */
            $userCliTable = $this->get('userCliTable');

            /** @var CoreUser $connectedUser */
            $connectedUser = $userCliTable->getConnectedUser();

            $lk = current($this->get('userAnrCliTable')->getEntityByFields([
                'user' => $connectedUser->getId(),
                'anr' => $anr['id'],
            ]));
            if (empty($lk)) {
                throw new Exception('Restricted ANR', 412);
            } else {
                $anr['rwd'] = $lk->get('rwd');
            }
        }

        $this->setCurrentAnrToConnectedUser($anr);

        return $anr;
    }

    public function patch(Anr $anr, array $data): Anr
    {
        if (isset($data['referentials'])) {
            $this->updateReferential($anr, array_column($data['referentials'], 'uuid'));
        }
        // TODO: being able to update all the flags e.g. initDefContext ..., label, description. referentials.
        // TODO: update scales thresholds.
        // TODO: making anr names unique.

        $anr->setUpdater($this->connectedUser->getEmail());

        $this->anrTable->save($anr);

        return $anr;
    }

    /**
     * @param string[] $referentialUuids
     */
    private function updateReferential(Entity\Anr $anr, array $referentialUuids): void
    {
        foreach ($anr->getReferentials() as $referential) {
            if (\in_array($referential->getUuid(), $referentialUuids, true)) {
                unset($referentialUuids[$referential->getUuid()]);
            } else {
                $anr->removeReferential($referential);
                $this->referentialTable->deleteEntity($referential, false);
            }
        }

        /* Link new referential to the analysis from Core. */
        foreach ($referentialUuids as $referentialUuid) {
            $referentialFromCore = $this->coreReferentialTable->findByUuid($referentialUuid);

            /* Recreate the core's referential in the analysis.  */
            $referential = (new Entity\Referential())
                ->setAnr($anr)
                ->setLabels($referentialFromCore->getLabels())
                ->setUuid($referentialFromCore->getUuid())
                ->setCreator($this->connectedUser->getEmail());


            $categoryNewIds = [];
            foreach ($referentialFromCore->getCategories() as $categoryFromCore) {
                // TODO: ...
                $newCategory = new Entity\SoaCategory($cat);
                $newCategory->set('id', null);
                $newCategory->setAnr($anr);
                $newCategory->setMeasures(null);
                $newCategory->setReferential($newReferential);
                $categoryNewIds[$cat->id] = $newCategory;
            }
            $newReferential->setCategories($categoryNewIds);

            // duplicate the measures
            $measuresNewIds = [];
            foreach ($measures as $measure) {
                // duplicate and link the measures to the current referential
                $newMeasure = (new Entity\Measure($measure))
                    ->setAnr($anr)
                    ->setReferential($newReferential)
                    ->setCategory($categoryNewIds[$measure->category->id]);
                foreach ($newMeasure->getLinkedMeasures() as $linkedMeasure) {
                    $data = [];
                    if (!\count($this->get('measureMeasureCliTable')->getEntityByFields([
                        'anr' => $anr->id,
                        'father' => $measure->getUuid(),
                        'child' => $linkedMeasure->getUuid()
                    ]))) {
                        $data['father'] = $newMeasure->getUuid();
                        $data['child'] = $linkedMeasure->getUuid();
                        $newMeasureMeasure = new Entity\MeasureMeasure($data);
                        $newMeasureMeasure->setAnr($anr);
                        $this->get('measureMeasureCliTable')->save($newMeasureMeasure, false);
                    }

                    if (!\count($this->get('measureMeasureCliTable')->getEntityByFields([
                        'anr' => $anr->id,
                        'father' => $linkedMeasure->getUuid(),
                        'child' => $newMeasure->getUuid()
                    ]))) {
                        $data['father'] = $linkedMeasure->getUuid();
                        $data['child'] = $newMeasure->getUuid();
                        $newMeasureMeasure = new MeasureMeasure($data);
                        $newMeasureMeasure->setAnr($anr);
                        $this->get('measureMeasureCliTable')->save($newMeasureMeasure, false);
                    }
                }
                $amvs = $newMeasure->getAmvs();
                $rolfRisks = $newMeasure->getRolfRisks();
                // update the amv with the new measures from the current referential
                foreach ($amvs as $amvCommon) {
                    // match the AMVs from common with AMVS from cli
                    $amvCli = $this->get('amvCliTable')
                        ->getEntityByFields([
                            'asset' => ['uuid' => $amvCommon->getAsset()->getUuid(), 'anr' => $anr->getId()],
                            'threat' => ['uuid' => $amvCommon->getThreat()->getUuid(), 'anr' => $anr->getId()],
                            'vulnerability' => [
                                'uuid' => $amvCommon->getVulnerability()->getUuid(),
                                'anr' => $anr->id
                            ]
                        ]);
                    if (count($amvCli)) {
                        $newMeasure->addAmv($amvCli[0]);
                    }
                }
                foreach ($rolfRisks as $rolfRisk_common) {
                    // match the risks from common with risks from cli
                    $risk_cli = $this->get('rolfRiskCliTable')->getEntityByFields([
                        'anr' => $anr->id,
                        'label' . $this->getLanguage() => $rolfRisk_common->getLabel($this->getLanguage()),
                        'code' => $rolfRisk_common->getCode()
                    ]);
                    if (count($risk_cli)) {
                        //$risk_cli = $risk_cli[0];
                        $newMeasure->addOpRisk($risk_cli[0]);
                    }
                }
                $measuresNewIds[] = $newMeasure;

                $newSoa = new Soa();
                $newSoa->set('id', null);
                $newSoa->setAnr($anr);
                $newSoa->setMeasure($newMeasure);
                $this->get('soaTable')->save($newSoa, false);
            }
            $newReferential->setMeasures($measuresNewIds);

            $this->get('referentialCliTable')->save($newReferential);
        }

        return $anr->id;
    }

    /**
     * Creates a new analysis from a model which is located inside the common database.
     */
    public function createFromModelToClient(array $data): Entity\Anr
    {
        /** @var CoreEntity\Model $model */
        $model = $this->modelTable->findById((int)$data['model']);

        $availableLanguages = $this->getModelAvailableLanguages($model->getId());
        if (empty($availableLanguages[$data['language']])) {
            throw new Exception('Selected model\'s language is not supported', 412);
        }

        return $this->duplicateAnr($model->getAnr(), $data);
    }

    /**
     * Duplicates an analysis based on existing one on the client's side or on model's anr in the common database.
     */
    public function duplicateAnr(
        CoreEntity\AnrSuperClass $anr,
        array $data = [],
        string $snapshotMode = null
    ): Entity\Anr
    {
        $isSourceCommon = $anr instanceof CoreEntity\Anr;
        if (!$isSourceCommon && $snapshotMode === null) {
            /* Validate id the duplicated anr accessible for the user. */
            if (!$this->connectedUser->hasRole(Entity\UserRole::USER_ROLE_SYSTEM)
                && $this->userAnrTable->findByAnrAndUser($anr, $this->connectedUser) === null
            ) {
                throw new Exception('You are not authorized to duplicate this analysis', 412);
            }
        }

        if ($isSourceCommon) {
            /* Determine the language code when an analysis is created from a model. */
            $data['languageCode'] = strtolower($this->configService->getLanguageCodes()[$data['language']]);
        }

        $newAnr = Entity\Anr::constructFromObjectAndData($anr, $data)
            ->setCreator($this->connectedUser->getEmail());

        if ($snapshotMode === 'create') {
            /* The "[SNAP]" prefix is added for snapshots. */
            $newAnr->setLabel('[SNAP] ' . $newAnr->getLabel());
        }

        $this->anrTable->save($newAnr, false);

        /* Not needed for snapshots creation or restoring. */
        if ($snapshotMode === null) {
            $userAnr = (new Entity\UserAnr())
                ->setUser($this->connectedUser)
                ->setAnr($newAnr)
                ->setRwd(Entity\UserAnr::FULL_PERMISSIONS_RWD)
                ->setCreator($this->connectedUser->getEmail());

            $this->userAnrTable->save($userAnr, false);
        }

        // TODO: continue the refactoring from here
            // duplicate themes
            $themesNewIds = [];
            $themes = $isSourceCommon
                ? $this->themeTable->findAll()
                : $this->themeCliTable->findByAnr($anr);
            foreach ($themes as $theme) {
                // TODO: use service
                $newTheme = (new Entity\Theme())
                    ->setAnr($newAnr)
                    ->setLabels([
                        'label1' => $theme->getLabel(1),
                        'label2' => $theme->getLabel(2),
                        'label3' => $theme->getLabel(3),
                        'label4' => $theme->getLabel(4),
                    ])
                    ->setCreator($this->getConnectedUser()->getEmail());
                $this->themeCliTable->save($newTheme, false);
                $themesNewIds[$theme->getId()] = $newTheme;
            }

            // duplicate assets
            $assetsNewIds = [];
            if ($isSourceCommon) {
                // TODO: do the same as for vulnerabilities...
                $assets1 = [];
                if (!$model->isRegulator()) {
                    $assets1 = $this->get('assetTable')->getEntityByFields(['mode' => Asset::MODE_GENERIC]);
                }
                $assets2 = [];
                if (!$model->isGeneric()) {
                    // We fetch all the assets related to the specific model and linked to its configured anr.
                    $assets2 = $model->getAssets()->toArray();
                }
                $assets = array_merge($assets1, $assets2);
            } else {
                $assets = $this->get('assetCliTable')->getEntityByFields(['anr' => $anr->id]);
            }
            foreach ($assets as $asset) {
                // TODO: use service
                $newAsset = new Asset($asset);
                $newAsset->setAnr($newAnr);
                $newAsset->setMode(0); // force to generic
                $this->get('assetCliTable')->save($newAsset, false);
                $assetsNewIds[$asset->getUuid()] = $newAsset;
            }

            // duplicate threats
            $threatsNewIds = [];
            if ($isSourceCommon) {
                $threats = [];
                // TODO: make the same as for vulns ....
                if (!$model->isRegulator()) {
                    $threats = $this->threatTable->findByMode(ThreatSuperClass::MODE_GENERIC);
                }
                if (!$model->isGeneric()) {
                    // We fetch all the threats related to the specific model and linked to its configured anr.
                    $threats2 = array_merge(
                        $model->getThreats()->toArray(),
                        $this->get('threatTable')->findByAnr($model->getAnr())
                    );
                    foreach ($threats2 as $t) {
                        $threats[] = $t;
                    }
                    unset($threats2);
                }
            } else {
                $threats = $this->threatCliTable->findByAnr($anr);
            }
            foreach ($threats as $threat) {
                // TODO: use service
                $newThreat = new Threat($threat);
                $newThreat->setAnr($newAnr);
                if ($threat->getTheme()) {
                    $newThreat->setTheme($themesNewIds[$threat->getTheme()->getId()]);
                }
                $newThreat->setMode(0); // force to generic
                $this->threatCliTable->save($newThreat, false);
                $threatsNewIds[$threat->getUuid()] = $newThreat;
            }

            $vulnerabilitiesNewIds = $this->duplicateVulnerabilities($anr, $newAnr, $model, $isSourceCommon);

            // duplicate categories, referentials and measures
            $measuresNewIds = [];
            if ($isSourceCommon) {
                foreach ($data['referentials'] as $referential_array) {
                    $referential = $this->get('referentialTable')->getEntity($referential_array['uuid']);
                    $measures = $referential->getMeasures();
                    $referential->setMeasures(null);

                    // duplicate the referential
                    $newReferential = new Referential($referential);
                    $newReferential->setAnr($newAnr);

                    // duplicate categories
                    $categoryNewIds = [];
                    $category = $this->get('soaCategoryTable')
                        ->getEntityByFields(['referential' => $referential->getUuid()]);
                    foreach ($category as $cat) {
                        $newCategory = new SoaCategory($cat);
                        $newCategory->set('id', null);
                        $newCategory->setAnr($newAnr);
                        $newCategory->setMeasures(null);
                        $newCategory->setReferential($newReferential);
                        $categoryNewIds[$cat->id] = $newCategory;
                    }
                    $newReferential->setCategories($categoryNewIds);

                    foreach ($measures as $measure) {
                        // duplicate and link the measures to the current referential
                        $newMeasure = (new Measure($measure))
                            ->setAnr($newAnr)
                            ->setReferential($newReferential)
                            ->setCategory($categoryNewIds[$measure->category->id]);
                        foreach ($measure->getLinkedMeasures() as $linkedMeasure) {
                            $newMeasureMeasure = (new MeasureMeasure([
                                'father' => $measure->getUuid(),
                                'child' => $linkedMeasure->getUuid(),
                            ]))->setAnr($newAnr);
                            $this->get('measureMeasureCliTable')->save($newMeasureMeasure, false);
                        }
                        $measuresNewIds[$measure->getUuid()] = $newMeasure;
                    }
                    //$newReferential->setMeasures(null);
                    $this->get('referentialCliTable')->save($newReferential);
                }
            } else { // copy from an existing anr (or a snapshot)
                $referentialTable = $this->get('referentialCliTable');
                $referentials = $referentialTable->getEntityByFields(['anr' => $anr->id]);
                foreach ($referentials as $referential) {
                    // duplicate referentials
                    $measures = $referential->getMeasures();
                    $categories = $referential->getCategories();
                    $referential->setMeasures(null);
                    $referential->setCategories(null);
                    $newReferential = new Referential($referential);
                    $newReferential->setAnr($newAnr);

                    $categoryNewIds = [];
                    foreach ($categories as $cat) {
                        $newCategory = new SoaCategory($cat);
                        $newCategory->set('id', null);
                        $newCategory->setAnr($newAnr);
                        $newCategory->setMeasures(null);
                        $newCategory->setReferential($newReferential);
                        $categoryNewIds[$cat->id] = $newCategory;
                    }
                    $newReferential->setCategories($categoryNewIds);

                    $newMeasures = [];
                    foreach ($measures as $measure) {
                        // duplicate and link the measures to the current referential
                        $newMeasure = (new Measure($measure))
                            ->setAnr($newAnr)
                            ->setReferential($newReferential)
                            ->setCategory($categoryNewIds[$measure->category->id])
                        $measuresNewIds[$measure->getUuid()] = $newMeasure;
                        $newMeasures[] = $newMeasure;
                    }
                    $newReferential->setMeasures($newMeasures);

                    $referentialTable->save($newReferential);
                }

                // duplicate measures-measures
                $measuresmeasures = $this->get('measureMeasureCliTable')->getEntityByFields(['anr' => $anr->id]);
                foreach ($measuresmeasures as $mm) {
                    $newMeasureMeasure = new MeasureMeasure($mm);
                    $newMeasureMeasure->setAnr($newAnr);
                    $this->get('measureMeasureCliTable')->save($newMeasureMeasure, false);
                }
            }

            //duplicate SoaScaleComment
            $anrSoaScaleCommentOldIdsToNewObjectsMap = $this->createSoaScaleCommentFromSource(
                $newAnr,
                $anr,
                $isSourceCommon,
                $connectedUser
            );

            // duplicate soas
            if ($isSourceCommon) {
                foreach ($measuresNewIds as $key => $value) {
                    $newSoa = new Soa();
                    $newSoa->set('id', null);
                    $newSoa->setAnr($newAnr);
                    $newSoa->setMeasure($value);
                    $newSoa->setSoaScaleComment(null);
                    $this->get('soaTable')->save($newSoa, false);
                }
            } else {
                $soas = $this->get('soaTable')->getEntityByFields(['anr' => $anr->id]);
                foreach ($soas as $soa) {
                    $newSoa = new Soa($soa);
                    $newSoa->set('id', null);
                    $newSoa->setAnr($newAnr);
                    $newSoa->setMeasure($measuresNewIds[$soa->measure->getUuid()]);
                    if ($soa->getSoaScaleComment()!== null) {
                        $newSoa->setSoaScaleComment(
                            $anrSoaScaleCommentOldIdsToNewObjectsMap[$soa->getSoaScaleComment()->getId()]
                        );
                    } else {
                        $newSoa->setSoaScaleComment(null);
                    }
                    $this->get('soaTable')->save($newSoa, false);
                }
            }

            // duplicate amvs
            $amvsNewIds = [];
            $amvs = $isSourceCommon
                ? $this->get('amvTable')->fetchAllObject()
                : $this->get('amvCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($amvs as $key => $amv) {
                if (!isset(
                    $assetsNewIds[$amv->asset->getUuid()],
                    $threatsNewIds[$amv->threat->getUuid()],
                    $vulnerabilitiesNewIds[$amv->vulnerability->getUuid()]
                )) {
                    unset($amvs[$key]);
                }
            }
            foreach ($amvs as $amv) {
                $newAmv = new Amv($amv);
                $newAmv->setAnr($newAnr);
                $newAmv->setAsset($assetsNewIds[$amv->getAsset()->getUuid()]);
                $newAmv->setThreat($threatsNewIds[$amv->getThreat()->getUuid()]);
                $newAmv->setVulnerability($vulnerabilitiesNewIds[$amv->getVulnerability()->getUuid()]);
                $newAmv->setMeasures(null);
                foreach ($amv->getMeasures() as $measure) {
                    if (isset($measuresNewIds[$measure->getUuid()])) {
                        $measuresNewIds[$measure->getUuid()]->addAmv($newAmv);
                    }
                }
                $this->get('amvCliTable')->save($newAmv, false);
                $amvsNewIds[$amv->getUuid()] = $newAmv;
            }

            // duplicate rolf tags
            $rolfTagsNewIds = [];
            $rolfTags = $isSourceCommon
                ? $this->get('rolfTagTable')->fetchAllObject()
                : $this->get('rolfTagCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($rolfTags as $rolfTag) {
                $newRolfTag = new RolfTag($rolfTag);
                $newRolfTag->set('id', null);
                $newRolfTag->setAnr($newAnr);
                $newRolfTag->set('risks', []);
                $this->get('rolfTagCliTable')->save($newRolfTag, false);
                $rolfTagsNewIds[$rolfTag->id] = $newRolfTag;
            }

            // duplicate rolf risk
            $rolfRisksNewIds = [];
            $rolfRisks = $isSourceCommon
                ? $this->get('rolfRiskTable')->fetchAllObject()
                : $this->get('rolfRiskCliTable')->getEntityByFields(['anr' => $anr->getId()]);
            foreach ($rolfRisks as $rolfRisk) {
                $newRolfRisk = new RolfRisk($rolfRisk);
                $newRolfRisk->setId(null);
                $newRolfRisk->setAnr($newAnr);
                // Link tags
                foreach ($rolfRisk->getTags() as $key => $tag) {
                    if (!empty($rolfTagsNewIds[$tag->id])) {
                        $newRolfRisk->addTag($tag);
                    }
                }
                //link the measures
                foreach ($rolfRisk->getMeasures() as $m) {
                    try {
                        $measure = $this->get('measureCliTable')->getEntity([
                            'anr' => $newAnr->getId(),
                            'uuid' => $m->getUuid()
                        ]);
                        $measure->addOpRisk($newRolfRisk);
                    } catch (Exception $e) {
                    } //needed if the measures don't exist in the client ANR
                }
                $this->get('rolfRiskCliTable')->save($newRolfRisk, false);
                $rolfRisksNewIds[$rolfRisk->getId()] = $newRolfRisk;
            }

            // duplicate objects categories
            /** @var ObjectSuperClass[] $objects */
            $objects = $isSourceCommon
                ? $this->get('MonarcObjectTable')->fetchAllObject()
                : $this->get('objectCliTable')->getEntityByFields(['anr' => $anr->getId()]);
            if ($source === MonarcObject::SOURCE_COMMON) {
                foreach ($objects as $key => $object) {
                    $existInAnr = false;
                    foreach ($object->getAnrs() as $anrObject) {
                        if ($anrObject->getId() === $anr->getId()) {
                            $existInAnr = true;
                        }
                    }
                    if (!$existInAnr) {
                        unset($objects[$key]);
                    }
                }
            }
            $categoriesIds = [];
            foreach ($objects as $object) {
                if ($object->getCategory()) {
                    $categoriesIds[] = $object->getCategory()->getId();
                    $this->getParentsCategoryIds($object->getCategory(), $categoriesIds);
                }
            }

            $objectsCategoriesNewIds = [];
            /** @var ObjectCategorySuperClass[] $objectsCategories */
            $objectsCategories = $isSourceCommon
                ? $this->get('objectCategoryTable')->fetchAllObject()
                : $this->get('objectCategoryCliTable')->getEntityByFields(
                    ['anr' => $anr->getId()],
                    ['parent' => 'ASC']
                );
            foreach ($objectsCategories as $objectCategory) {
                if (\in_array($objectCategory->getId(), $categoriesIds, true)) {
                    $newObjectCategory = new ObjectCategory($objectCategory);
                    $newObjectCategory->set('id', null);
                    $newObjectCategory->setAnr($newAnr);
                    if ($objectCategory->getParent()) {
                        $newObjectCategory->setParent($objectsCategoriesNewIds[$objectCategory->getParent()->getId()]);
                    }
                    if ($objectCategory->getRoot()) {
                        $newObjectCategory->setRoot($objectsCategoriesNewIds[$objectCategory->getRoot()->getId()]);
                    }
                    if (!$objectCategory->getObjects()->isEmpty()) {
                        $newObjectCategory->resetObjects();
                    }
                    $this->get('objectCategoryCliTable')->save($newObjectCategory, false);

                    $objectsCategoriesNewIds[$objectCategory->getId()] = $newObjectCategory;
                }
            }

            // duplicate objects
            $objectsNewIds = [];
            $objectsRootCategories = [];
            /** @var CoreObjectCategoryTable|ObjectCategoryTable $objectCategoryTable */
            $objectCategoryTable = $isSourceCommon
                ? $this->get('objectCategoryTable')
                : $this->get('objectCategoryCliTable');
            foreach ($objects as $object) {
                // TODo: we refactored the class, now it doesn't work to pass in the constructor the other obj.
                $newObject = new MonarcObject($object);
                $newObject->setAnr($newAnr);
                $newObject->resetAnrs();
                $newObject->addAnr($newAnr);
                if ($object->getCategory() !== null) {
                    $newObject->setCategory($objectsCategoriesNewIds[$object->getCategory()->getId()]);

                    $objectCategory = $objectCategoryTable->findById($object->getCategory()->getId());
                    $rootCategoryId = $objectCategory->getRoot() !== null
                        ? $objectCategory->getRoot()->getId()
                        : $objectCategory->getId();
                    if (!\in_array($rootCategoryId, $objectsRootCategories, true)) {
                        $objectsRootCategories[] = $rootCategoryId;
                    }
                }
                $newObject->setAsset($assetsNewIds[$object->getAsset()->getUuid()]);
                if ($object->getRolfTag()) {
                    $newObject->setRolfTag($rolfTagsNewIds[$object->getRolfTag()->getId()]);
                }
                //in FO all the objects are generic
                $newObject->setMode(0); //force to be generic
                $this->get('objectCliTable')->save($newObject, false);
                $objectsNewIds[$object->getUuid()] = $newObject;
            }

            // TODO: the table and entity are redundant, we can manage them via links.
            /** @var AnrObjectCategoryTable|CoreAnrObjectCategoryTable $anrObjectCategoryTable */
//            $anrObjectCategoryTable = $isSourceCommon
//                ? $this->get('anrObjectCategoryTable')
//                : $this->get('anrObjectCategoryCliTable');
//            $anrObjectsCategories = $anrObjectCategoryTable->findByAnrOrderedByPosition($anr);
//            foreach ($anrObjectsCategories as $key => $anrObjectCategory) {
//                if (!\in_array($anrObjectCategory->getCategory()->getId(), $objectsRootCategories, true)) {
//                    unset($anrObjectsCategories[$key]);
//                }
//            }
//            foreach ($anrObjectsCategories as $key => $anrObjectCategory) {
//                $newAnrObjectCategory = new AnrObjectCategory($anrObjectCategory);
//                $newAnrObjectCategory->set('id', null);
//                $newAnrObjectCategory->setAnr($newAnr);
//                $newAnrObjectCategory
//                    ->setCategory($objectsCategoriesNewIds[$anrObjectCategory->getCategory()->getId()]);
//                $this->get('anrObjectCategoryCliTable')->save($newAnrObjectCategory, false);
//            }

            // duplicate objects objects
            $objectsObjects = $isSourceCommon
                ? $this->get('objectObjectTable')->fetchAllObject()
                : $this->get('objectObjectCliTable')->getEntityByFields(['anr' => $anr->id]);
            foreach ($objectsObjects as $key => $objectObject) {
                if (!($objectObject->getParent() && isset($objectsNewIds[$objectObject->getParent()->getUuid()])
                    && $objectObject->getChild() && isset($objectsNewIds[$objectObject->getChild()->getUuid()]))
                ) {
                    unset($objectsObjects[$key]);
                }
            }
            foreach ($objectsObjects as $objectObject) {
                $newObjectObject = new ObjectObject($objectObject);
                $newObjectObject->setAnr($newAnr);
                $newObjectObject->setParent($objectsNewIds[$objectObject->getParent()->getUuid()]);
                $newObjectObject->setChild($objectsNewIds[$objectObject->getChild()->getUuid()]);
                $this->get('objectObjectCliTable')->save($newObjectObject, false);
            }

            //duplicate AnrMetadatasOnInstances
            $anrMetadatasOnInstancesOldIdsToNewObjectsMap = $this->createAnrMetadatasOnInstancesFromSource(
                $newAnr,
                $anr,
                $source,
                $connectedUser
            );

            // duplicate instances
            $instancesNewIds = [];
            /** @var InstanceTable|CoreInstanceTable $instanceTable */
            $instanceTable = $isSourceCommon
                ? $this->get('instanceTable')
                : $this->get('instanceCliTable');
            $instances = $instanceTable->getEntityByFields(['anr' => $anr->getId()], ['parent' => 'ASC']);
            foreach ($instances as $instance) {
                // TODO: replace with normal object setup.
                $newInstance = new Instance($instance);
                $newInstance->set('id', null);
                $newInstance->setAnr($newAnr);
                $newInstance->setAsset($assetsNewIds[$instance->getAsset()->getUuid()]);
                $newInstance->setObject($objectsNewIds[$instance->getObject()->getUuid()]);
                $newInstance->setRoot(null);
                $newInstance->setParent(null);
                /*
                 * TODO: remove the reset method when all the entities creation from another entities wil be forbidden.
                 * Currently we link the core related classes, that's why have to clean up the relations.
                 * -> should be dropped now !!!
                 */
                $newInstance->resetInstanceRisks();
                $newInstance->resetInstanceConsequences();

                if (!$isSourceCommon) {
                    $this->createInstanceMetadatasFromSource(
                        $connectedUser,
                        $instance,
                        $newInstance,
                        $anr,
                        $newAnr,
                        $anrMetadatasOnInstancesOldIdsToNewObjectsMap
                    );
                }

                $this->get('instanceCliTable')->save($newInstance, false);
                $instancesNewIds[$instance->id] = $newInstance;
            }
            foreach ($instances as $instance) {
                if ($instance->getRoot() || $instance->getParent()) {
                    $newInstance = $instancesNewIds[$instance->getId()];
                    if ($instance->getRoot()) {
                        $newInstance->setRoot($instancesNewIds[$instance->getRoot()->getId()]);
                    }
                    if ($instance->getParent()) {
                        $newInstance->setParent($instancesNewIds[$instance->getParent()->getId()]);
                    }
                    $this->get('instanceCliTable')->save($newInstance, false);
                }
            }

            $scalesImpactTypesOldIdsToNewObjectsMap = $this->createScalesFromSourceAnr(
                $newAnr,
                $anr,
                $source,
                $connectedUser
            );

            $operationalScaleTypesOldIdsToNewObjectsMap = $this->createOperationalRiskScalesFromSourceAnr(
                $newAnr,
                $anr,
                $source,
                $connectedUser
            );

            // duplicate instances risks
            /** @var InstanceRiskTable $instanceRiskCliTable */
            $instanceRiskCliTable = $this->get('instanceRiskCliTable');
            /** @var InstanceRiskTable|CoreInstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $isSourceCommon
                ? $this->get('instanceRiskTable')
                : $instanceRiskCliTable;
            /** @var InstanceRiskSuperClass[] $instancesRisks */
            $instancesRisks = $instanceRiskTable->findByAnr($anr);
            $instancesRisksNewIds = [];
            foreach ($instancesRisks as $instanceRisk) {
                $newInstanceRisk = new InstanceRisk($instanceRisk);
                $newInstanceRisk->set('id', null);
                $newInstanceRisk->setAnr($newAnr);
                if ($instanceRisk->getAmv()) {
                    $newInstanceRisk->setAmv($amvsNewIds[$instanceRisk->getAmv()->getUuid()]);
                }
                if ($instanceRisk->getAsset()) {
                    $newInstanceRisk->setAsset($assetsNewIds[$instanceRisk->getAsset()->getUuid()]);
                }
                if ($instanceRisk->getThreat()) {
                    $newInstanceRisk->setThreat($threatsNewIds[$instanceRisk->getThreat()->getUuid()]);
                }
                if ($instanceRisk->getVulnerability()) {
                    $newInstanceRisk->setVulnerability(
                        $vulnerabilitiesNewIds[$instanceRisk->getVulnerability()->getUuid()]
                    );
                }
                if ($instanceRisk->getInstance()) {
                    $newInstanceRisk->setInstance($instancesNewIds[$instanceRisk->getInstance()->getId()]);
                }
                if ($instanceRisk->getInstanceRiskOwner()) {
                    $instanceRiskOwner = $this->instanceRiskOwnerService->getOrCreateInstanceRiskOwner(
                        $newAnr,
                        $instanceRisk->getInstanceRiskOwner()->getName(),
                    );
                    $newInstanceRisk->setInstanceRiskOwner($instanceRiskOwner);
                }
                $newInstanceRisk->setContext($instanceRisk->getContext());

                $instanceRiskCliTable->save($newInstanceRisk, false);
                $instancesRisksNewIds[$instanceRisk->getId()] = $newInstanceRisk;
            }

            // duplicate instances risks op
            /** @var InstanceRiskOpTable $instanceRiskOpCliTable */
            $instanceRiskOpCliTable = $this->get('instanceRiskOpCliTable');
            /** @var CoreInstanceRiskOpTable|InstanceRiskOpTable $instanceRiskOpTable */
            $instanceRiskOpTable = $isSourceCommon
                ? $this->get('instanceRiskOpTable')
                : $instanceRiskOpCliTable;
            $instancesRisksOp = $instanceRiskOpTable->findByAnr($anr);
            $instancesRisksOpNewIds = [];
            foreach ($instancesRisksOp as $instanceRiskOp) {
                $newInstanceRiskOp = (new InstanceRiskOp())
                    ->setAnr($newAnr)
                    ->setInstance($instancesNewIds[$instanceRiskOp->getInstance()->getId()])
                    ->setObject($objectsNewIds[$instanceRiskOp->getObject()->getUuid()])
                    ->setKindOfMeasure($instanceRiskOp->getKindOfMeasure())
                    ->setBrutProb($instanceRiskOp->getBrutProb())
                    ->setNetProb($instanceRiskOp->getNetProb())
                    ->setTargetedProb($instanceRiskOp->getTargetedProb())
                    ->setCacheBrutRisk($instanceRiskOp->getCacheBrutRisk())
                    ->setCacheNetRisk($instanceRiskOp->getCacheNetRisk())
                    ->setCacheTargetedRisk($instanceRiskOp->getCacheTargetedRisk())
                    ->setMitigation($instanceRiskOp->getMitigation())
                    ->setIsSpecific($instanceRiskOp->isSpecific())
                    ->setRiskCacheCode($instanceRiskOp->getRiskCacheCode())
                    ->setRiskCacheLabels([
                        'riskCacheLabel1' => $instanceRiskOp->getRiskCacheLabel(1),
                        'riskCacheLabel2' => $instanceRiskOp->getRiskCacheLabel(2),
                        'riskCacheLabel3' => $instanceRiskOp->getRiskCacheLabel(3),
                        'riskCacheLabel4' => $instanceRiskOp->getRiskCacheLabel(4),
                    ])
                    ->setRiskCacheDescriptions([
                        'riskCacheDescription1' => $instanceRiskOp->getRiskCacheDescription(1),
                        'riskCacheDescription2' => $instanceRiskOp->getRiskCacheDescription(2),
                        'riskCacheDescription3' => $instanceRiskOp->getRiskCacheDescription(3),
                        'riskCacheDescription4' => $instanceRiskOp->getRiskCacheDescription(4),
                    ])
                    ->setComment($instanceRiskOp->getComment())
                    ->setContext($instanceRiskOp->getContext())
                    ->setCreator($connectedUser->getEmail())
                    ->resetUpdatedAtValue();
                if ($instanceRiskOp->getRolfRisk()) {
                    $newInstanceRiskOp->setRolfRisk($rolfRisksNewIds[$instanceRiskOp->getRolfRisk()->getId()]);
                }

                if ($instanceRiskOp->getInstanceRiskOwner()) {
                    $instanceRiskOwner = $this->instanceRiskOwnerService->getOrCreateInstanceRiskOwner(
                        $newAnr,
                        $instanceRiskOp->getInstanceRiskOwner()->getName(),
                    );
                    $newInstanceRiskOp->setInstanceRiskOwner($instanceRiskOwner);
                }

                $instanceRiskOpCliTable->save($newInstanceRiskOp, false);

                $this->createOperationalInstanceRiskScalesFromSource(
                    $instanceRiskOp,
                    $operationalScaleTypesOldIdsToNewObjectsMap,
                    $newAnr,
                    $newInstanceRiskOp,
                    $connectedUser
                );

                $instancesRisksOpNewIds[$instanceRiskOp->getId()] = $newInstanceRiskOp;
            }

            //duplicate instances consequences
            /** @var InstanceConsequenceTable|CoreInstanceConsequenceTable $instanceConsequenceTable */
            $instanceConsequenceTable = $isSourceCommon
                ? $this->get('instanceConsequenceTable')
                : $this->get('instanceConsequenceCliTable');
            $instancesConsequences = $instanceConsequenceTable->findByAnr($anr);
            foreach ($instancesConsequences as $instanceConsequence) {
                $newInstanceConsequence = new InstanceConsequence($instanceConsequence);
                $newInstanceConsequence->set('id', null);
                $newInstanceConsequence->setAnr($newAnr);
                $newInstanceConsequence->setInstance($instancesNewIds[$instanceConsequence->getInstance()->getId()]);
                $newInstanceConsequence->setObject($objectsNewIds[$instanceConsequence->getObject()->getUuid()]);
                $newInstanceConsequence->setScaleImpactType(
                    $scalesImpactTypesOldIdsToNewObjectsMap[$instanceConsequence->getScaleImpactType()->getId()]
                );
                $this->get('instanceConsequenceCliTable')->save($newInstanceConsequence, false);
            }

            // duplicate questions & choices
            /** @var QuestionSuperClass[] $questions */
            $questions = $isSourceCommon
                ? $this->get('questionTable')->fetchAllObject()
                : $this->get('questionCliTable')->getEntityByFields(['anr' => $anr->id]);
            $questionsNewIds = [];
            foreach ($questions as $q) {
                $newQuestion = new Question($q);
                $newQuestion->setId(null);
                $newQuestion->setAnr($newAnr);
                $this->get('questionCliTable')->save($newQuestion, false);
                $questionsNewIds[$q->getId()] = $newQuestion;
            }
            /** @var QuestionChoiceSuperClass[] $questionChoices */
            $questionChoices = $isSourceCommon
                ? $this->get('questionChoiceTable')->fetchAllObject()
                : $this->get('questionChoiceCliTable')->getEntityByFields(['anr' => $anr->getId()]);
            foreach ($questionChoices as $qc) {
                $newQuestionChoice = new QuestionChoice($qc);
                $newQuestionChoice->setId(null);
                $newQuestionChoice->setAnr($newAnr);
                $newQuestionChoice->setQuestion($questionsNewIds[$qc->getQuestion()->getId()]);
                $this->get('questionChoiceCliTable')->save($newQuestionChoice, false);
            }

            //if we are duplicating an analysis do the following
            if (!$isSourceCommon) {
                //duplicate interviews
                $interviews = $this->get('interviewCliTable')->getEntityByFields(['anr' => $anr->id]);
                foreach ($interviews as $interview) {
                    $newInterview = new Interview($interview);
                    $newInterview->set('id', null);
                    $newInterview->setAnr($newAnr);
                    $this->get('interviewCliTable')->save($newInterview, false);
                }

                //duplicate record actors
                $recordActors = $this->get('recordActorCliTable')->getEntityByFields(['anr' => $anr->id]);
                $actorNewIds = [];
                foreach ($recordActors as $a) {
                    $newActor = new RecordActor($a);
                    $newActor->set('id', null);
                    $newActor->setAnr($newAnr);
                    $this->get('recordActorCliTable')->save($newActor, false);
                    $actorNewIds[$a->id] = $newActor;
                }

                //duplicate record data categories
                $recordDataCategoryTable = $this->get('recordDataCategoryCliTable');
                $recordDataCategories = $recordDataCategoryTable->getEntityByFields(['anr' => $anr->id]);
                $dataCategoryNewIds = [];
                foreach ($recordDataCategories as $dc) {
                    $newDataCategory = new RecordDataCategory($dc);
                    $newDataCategory->set('id', null);
                    $newDataCategory->setAnr($newAnr);
                    $recordDataCategoryTable->save($newDataCategory, true);
                    $dataCategoryNewIds[$dc->id] = $newDataCategory;
                }

                //duplicate record processors
                $recordProcessors = $this->get('recordProcessorCliTable')->getEntityByFields(['anr' => $anr->id]);
                $processorNewIds = [];
                foreach ($recordProcessors as $p) {
                    $newProcessor = new RecordProcessor($p);
                    $newProcessor->set('id', null);
                    $newProcessor->setAnr($newAnr);
                    $activities = [];
                    $newProcessor->setActivities($activities);
                    $secMeasures = [];
                    $newProcessor->setSecMeasures($secMeasures);
                    if ($p->representative != null) {
                        $newProcessor->setRepresentative($actorNewIds[$p->representative->id]);
                    }
                    if ($p->dpo != null) {
                        $newProcessor->setDpo($actorNewIds[$p->dpo->id]);
                    }
                    $this->get('recordProcessorCliTable')->save($newProcessor, false);
                    $processorNewIds[$p->id] = $newProcessor;
                }

                //duplicate record recipients
                $recordRecipients = $this->get('recordRecipientCliTable')->getEntityByFields(['anr' => $anr->id]);
                $recipientNewIds = [];
                foreach ($recordRecipients as $r) {
                    $newRecipient = new RecordRecipient($r);
                    $newRecipient->set('id', null);
                    $newRecipient->setAnr($newAnr);
                    $this->get('recordRecipientCliTable')->save($newRecipient, false);
                    $recipientNewIds[$r->id] = $newRecipient;
                }
                //duplicate record
                $records = $this->get('recordCliTable')->getEntityByFields(['anr' => $anr->id]);
                $recordNewIds = [];
                foreach ($records as $record) {
                    $newRecord = new Record($record);
                    $newRecord->set('id', null);
                    $newRecord->setAnr($newAnr);
                    if ($record->controller != null) {
                        $newRecord->setController($actorNewIds[$record->controller->id]);
                    }
                    if ($record->representative != null) {
                        $newRecord->setRepresentative($actorNewIds[$record->representative->id]);
                    }
                    if ($record->dpo != null) {
                        $newRecord->setDpo($actorNewIds[$record->dpo->id]);
                    }

                    $jointControllerNewIds = [];
                    $jointControllers = $record->getJointControllers();
                    foreach ($jointControllers as $jc) {
                        $jointControllerNewIds[] = $actorNewIds[$jc->id];
                    }
                    $newRecord->setJointControllers($jointControllerNewIds);

                    $processorIds = [];
                    $processors = $record->getProcessors();
                    foreach ($processors as $p) {
                        $processorIds[] = $processorNewIds[$p->id];
                    }
                    $newRecord->setProcessors($processorIds);

                    $recipientIds = [];
                    $recipients = $record->getRecipients();
                    foreach ($recipients as $r) {
                        $recipientIds[$r->id] = $recipientNewIds[$r->id];
                    }
                    $newRecord->setRecipients($recipientIds);

                    $this->get('recordCliTable')->save($newRecord, false);
                    $recordNewIds[$record->id] = $newRecord;
                }

                foreach ($recordProcessors as $p) {
                    $data = [];
                    $activities = $p->getActivities();
                    foreach ($activities as $recordId => $value) {
                        $data["activities"][$recordNewIds[$recordId]->getId()] = $value;
                    }
                    $secMeasures = $p->getSecMeasures();
                    foreach ($secMeasures as $recordId => $value) {
                        $data["secMeasures"][$recordNewIds[$recordId]->getId()] = $value;
                    }
                    $this->recordProcessorService->patch($processorNewIds[$p->id]->getId(), $data);
                }

                //duplicate record personal data
                $recordPersonalData = $this->get('recordPersonalDataCliTable')->getEntityByFields(['anr' => $anr->id]);
                $personalDataNewIds = [];
                foreach ($recordPersonalData as $pd) {
                    $newPersonalData = new RecordPersonalData($pd);
                    $newPersonalData->set('id', null);
                    $newPersonalData->setAnr($newAnr);
                    $newPersonalData->setRecord($recordNewIds[$pd->record->id]);
                    $newDataCategoryIds = [];
                    $dataCategories = $pd->getDataCategories();
                    foreach ($dataCategories as $dc) {
                        $newDataCategoryIds[] = $dataCategoryNewIds[$dc->id];
                    }
                    $newPersonalData->setDataCategories($newDataCategoryIds);
                    $this->get('recordPersonalDataCliTable')->save($newPersonalData, false);
                    $personalDataNewIds[$pd->id] = $newPersonalData;
                }

                //duplicate record international transfers
                $recordInternationalTransfers = $this->get('recordInternationalTransferCliTable')
                    ->getEntityByFields(['anr' => $anr->getId()]);
                $internationalTransferNewIds = [];
                foreach ($recordInternationalTransfers as $it) {
                    $newInternationalTransfer = new RecordInternationalTransfer($it);
                    $newInternationalTransfer->set('id', null);
                    $newInternationalTransfer->setAnr($newAnr);
                    $newInternationalTransfer->setRecord($recordNewIds[$it->record->id]);
                    $this->get('recordInternationalTransferCliTable')->save($newInternationalTransfer, false);
                    $internationalTransferNewIds[$it->id] = $newInternationalTransfer;
                }

                $recommendationsNewIds = [];
                // duplicate recommendations sets and recommendations
                /** @var Entity\RecommendationSet[] $recommendationsSets */
                $recommendationsSets = $this->get('recommendationSetCliTable')->getEntityByFields(['anr' => $anr->id]);
                foreach ($recommendationsSets as $recommendationSet) {
                    $recommendationSetRecommendations = [];

                    // TODO: ... can't work like that
                    $recommendations = $recommendationSet->getRecommendations();
                    $recommendationSet->setRecommendations(null);
                    $newRecommendationSet = new Entity\RecommendationSet($recommendationSet);
                    $newRecommendationSet->setAnr($newAnr);

                    foreach ($recommendations as $recommendation) {
                        // TODO ...
                        $newRecommendation = new Entity\Recommendation($recommendation);
                        $newRecommendation->setAnr($newAnr);
                        $newRecommendation->setRecommendationSet($newRecommendationSet);
                        $this->recommendationTable->save($newRecommendation, false);
                        $recommendationSetRecommendations[] = $newRecommendation;
                        $recommendationsNewIds[$recommendation->getUuid()] = $newRecommendation;
                    }

                    // TODO use addRecommendation recommendations above
                    $newRecommendationSet->setRecommendations($recommendationSetRecommendations);
                    $this->get('recommendationSetCliTable')->save($newRecommendationSet, false);
                }

                // duplicate recommendations history
                $recommendationsHistory = $this->get('recommendationHistoryCliTable')
                    ->getEntityByFields(['anr' => $anr->getId()]);
                foreach ($recommendationsHistory as $recommendationHistory) {
                    // TODO: currently impossible, do a proper createFromObject.
                    $newRecommendationHistory = new Entity\RecommendationHistory($recommendationHistory);
                    $newRecommendationHistory->setAnr($newAnr);
                    $this->get('recommendationHistoryCliTable')->save($newRecommendationHistory, false);
                }

                //duplicate recommendations risks
                /** @var Table\RecommendationRiskTable $recommendationRiskTable */
                $recommendationRiskTable = $this->get('recommendationRiskCliTable');
                $recommendationsRisks = $recommendationRiskTable->findByAnr($anr);
                foreach ($recommendationsRisks as $recommendationRisk) {
                    $newRecommendationRisk = (new Entity\RecommendationRisk())
                        ->setAnr($newAnr)
                        ->setCommentAfter($recommendationRisk->getCommentAfter())
                        ->setRecommendation(
                            $recommendationsNewIds[$recommendationRisk->getRecommendation()->getUuid()]
                        )
                        ->setInstance($instancesNewIds[$recommendationRisk->getInstance()->getId()]);

                    if ($recommendationRisk->getInstanceRisk()) {
                        $newRecommendationRisk->setInstanceRisk(
                            $instancesRisksNewIds[$recommendationRisk->getInstanceRisk()->getId()]
                        );
                    } elseif ($recommendationRisk->getInstanceRiskOp()) {
                        $newRecommendationRisk->setInstanceRiskOp(
                            $instancesRisksOpNewIds[$recommendationRisk->getInstanceRiskOp()->getId()]
                        );
                        // TODO: remove when #240 is done. Test if it works without.
                        // $newRecommendationRisk->setAnr(null);
                    }
                    if ($recommendationRisk->getGlobalObject()
                        && isset($objectsNewIds[$recommendationRisk->getGlobalObject()->getUuid()])
                    ) {
                        $newRecommendationRisk->setGlobalObject(
                            $objectsNewIds[$recommendationRisk->getGlobalObject()->getUuid()]
                        );
                    }
                    if ($recommendationRisk->getAsset()) {
                        $newRecommendationRisk->setAsset($assetsNewIds[$recommendationRisk->getAsset()->getUuid()]);
                    }
                    if ($recommendationRisk->getThreat()) {
                        $newRecommendationRisk->setThreat(
                            $threatsNewIds[$recommendationRisk->getThreat()->getUuid()]
                        );
                    }
                    if ($recommendationRisk->getVulnerability()) {
                        $newRecommendationRisk->setVulnerability(
                            $vulnerabilitiesNewIds[$recommendationRisk->getVulnerability()->getUuid()]
                        );
                    }
                    /*
                     * We do this trick becasue the other relations (asset, threat, vulnerability)
                     * in case of operation risks are null and the anr will be force reset to null.
                     * TODO: remove when #240 is done.
                     */
//                    if ($newRecommendationRisk->getAnr() === null) {
//                        $recommendationRiskTable->save($newRecommendationRisk);
//                        $newRecommendationRisk->setAnr($newAnr);
//                    }

                    $recommendationRiskTable->save($newRecommendationRisk, false);
                }
            }

        $this->anrTable->flush();

        if ($snapshotMode !== 'create') {
            $this->setCurrentAnrToConnectedUser($newAnr);
        }
//        } catch (\Exception $e) {
//            if (!empty($newAnr)) {
//                $anrCliTable->remove($newAnr);
//            }
//
//            throw $e;
//        }

        return $newAnr;
    }

    public function setCurrentAnrToConnectedUser(Anr $anr): void
    {
        /** @var UserTable $userCliTable */
        $userCliTable = $this->get('userCliTable');

        /** @var User $connectedUser */
        $connectedUser = $this->getConnectedUser();
        $connectedUser->setCurrentAnr($anr);

        $userCliTable->save($connectedUser);
    }

    /**
     * Recursively retrieves parent categories IDs
     * @param AnrObjectCategory $category The category for which we want the parents
     * @param array $categoriesIds Reference to an array of categories
     * @return array The IDs of the categories of all parents
     */
    public function getParentsCategoryIds($category, &$categoriesIds)
    {
        if ($category->parent) {
            $categoriesIds[] = $category->parent->id;
            $this->getParentsCategoryIds($category->parent, $categoriesIds);

            return $categoriesIds;
        }

        return [];
    }

    public function delete(Entity\Anr $anr): void
    {
        /* Try to drop the stats. */
        try {
            $this->statsAnrService->deleteStatsForAnr($anr->getUuid());
        } catch (Throwable $e) {
        }

        $this->anrTable->remove($anr);
    }

    /**
     * Returns an array that specifies in which languages the model can be instantiated.
     *
     * @return array The array of languages that are available for the model.
     *                  e.g. [1 => true, 2 => true, 3 => false, 4 => true]
     */
    public function getModelAvailableLanguages(int $modelId): array
    {
        // TODO: Use the list from the config.
        $languages = [1, 2, 3, 4];
        $success = [];
        foreach ($languages as $lang) {
            $success[$lang] = true;
        }

        /** @var Model $model */
        $model = $this->modelTable->findById($modelId);
        foreach ($languages as $lang) {
            if (empty($model->getLabel($lang))) {
                $success[$lang] = false;
            }
        }

        //themes, measures, rolf tags, rolf risks, questions and questions choices
        $array = [
            'theme' => 'label',
            'measure' => 'label',
            'rolfRisk' => 'label',
            'rolfTag' => 'label',
            'question' => 'label',
            'questionChoice' => 'label',
        ];
        foreach ($array as $key => $value) {
            $entities = $this->get($key . 'Table')->fetchAllObject();
            foreach ($entities as $entity) {
                foreach ($languages as $lang) {
                    if (empty($entity->get($value . $lang))) {
                        $success[$lang] = false;
                    }
                }
            }
        }

        //instances
        /** @var CoreInstanceTable $coreinstanceTable */
        $coreinstanceTable = $this->get('instanceTable');
        $instances = $coreinstanceTable->findByAnr($model->getAnr());
        foreach ($instances as $instance) {
            foreach ($languages as $lang) {
                if ($instance->getName($lang) === '' || $instance->getLabel($lang) === '') {
                    $success[$lang] = false;
                }
            }
        }

        //scales impact types
        $scalesImpactsTypes = $this->get('scaleImpactTypeTable')
            ->getEntityByFields(['anr' => $model->get('anr')->get('id')]);
        foreach ($scalesImpactsTypes as $scaleImpactType) {
            foreach ($languages as $lang) {
                if (empty($scaleImpactType->get('label' . $lang))) {
                    $success[$lang] = false;
                }
            }
        }

        //scales impact types
        /** @var CoreScaleImpactTypeTable $coreScaleImpactTypeTable */
        $coreScaleImpactTypeTable = $this->get('scaleImpactTypeTable');
        $scalesImpactsTypes = $coreScaleImpactTypeTable->getEntityByFields(['anr' => $model->getAnr()->getId()]);
        foreach ($scalesImpactsTypes as $scaleImpactType) {
            foreach ($languages as $lang) {
                if (empty($scaleImpactType->get('label' . $lang))) {
                    $success[$lang] = false;
                }
            }
        }

        /** @var CoreVulnerabilityTable $coreVulnerabilityTable */
        $coreVulnerabilityTable = $this->get('vulnerabilityTable');
        $vulnerabilities = [];
        if (!$model->isRegulator()) {
            $vulnerabilities = $coreVulnerabilityTable->findByMode(VulnerabilitySuperClass::MODE_GENERIC);
        }
        if (!$model->isGeneric()) {
            $vulnerabilities = array_merge(
                $vulnerabilities,
                $coreVulnerabilityTable->findByMode(VulnerabilitySuperClass::MODE_SPECIFIC)
            );
        }
        foreach ($vulnerabilities as $vulnerability) {
            foreach ($languages as $lang) {
                if ($vulnerability->getLabel($lang) === '') {
                    $success[$lang] = false;
                }
            }
        }
        // TODO: make the same for asset and threat as for vulnerability above.
        foreach (['asset', 'threat'] as $value) {
            $entities1 = [];
            if (!$model->isRegulator()) {
                $entities1 = $this->get($value . 'Table')->getEntityByFields(['mode' => Asset::MODE_GENERIC]);
            }
            $entities2 = [];
            if (!$model->isGeneric()) {
                $entities2 = $this->get($value . 'Table')->getEntityByFields(['mode' => Asset::MODE_SPECIFIC]);
            }
            $entities = $entities1 + $entities2;
            foreach ($entities as $entity) {
                foreach ($languages as $lang) {
                    if (empty($entity->get('label' . $lang))) {
                        $success[$lang] = false;
                    }
                }
            }
        }

        //objects
        $objects = $this->get('MonarcObjectTable')->fetchAllObject();
        foreach ($objects as $key => $object) {
            $existInAnr = false;
            foreach ($object->anrs as $anrObject) {
                if ($anrObject->getId() === $model->getAnr()->getId()) {
                    $existInAnr = true;
                }
            }
            if (!$existInAnr) {
                unset($objects[$key]);
            }
        }
        foreach ($objects as $object) {
            foreach ($languages as $lang) {
                if (empty($object->getLabel($lang))
                    || empty($object->getName($lang))
                    || ($object->getCategory() !== null
                        && empty($object->getCategory()->getLabel($lang))
                    )
                ) {
                    $success[$lang] = false;
                }
            }
        }

        return $success;
    }

    private function createScalesFromSourceAnr(
        Anr $newAnr,
        AnrSuperClass $sourceAnr,
        string $sourceName,
        UserSuperClass $connectedUser
    ): array {
        $scalesImpactTypesOldIdsToNewObjectsMap = [];
        /** @var ScaleTable $scaleCliTable */
        $scaleCliTable = $this->get('scaleCliTable');
        /** @var ScaleTable|CoreScaleTable $scaleTable */
        $scaleTable = $sourceName === MonarcObject::SOURCE_COMMON
            ? $this->get('scaleTable')
            : $scaleCliTable;
        /** @var ScaleImpactTypeTable $scaleImpactTypeCliTable */
        $scaleImpactTypeCliTable = $this->get('scaleImpactTypeCliTable');

        $scales = $scaleTable->findByAnr($sourceAnr);
        foreach ($scales as $scale) {
            $newScale = (new Scale())
                ->setAnr($newAnr)
                ->setType($scale->getType())
                ->setMin($scale->getMin())
                ->setMax($scale->getMax())
                ->setCreator($connectedUser->getFirstname() . ' ' . $connectedUser->getLastname());

            $scaleCliTable->save($newScale, false);

            foreach ($scale->getScaleImpactTypes() as $scaleImpactType) {
                $newScaleImpactType = (new ScaleImpactType())
                    ->setAnr($newAnr)
                    ->setScale($newScale)
                    ->setIsHidden($scaleImpactType->isHidden())
                    ->setIsSys($scaleImpactType->isSys())
                    ->setLabels([
                        'label1' => $scaleImpactType->getLabel(1),
                        'label2' => $scaleImpactType->getLabel(2),
                        'label3' => $scaleImpactType->getLabel(3),
                        'label4' => $scaleImpactType->getLabel(4),
                    ])
                    ->setType($scaleImpactType->getType())
                    ->setPosition($scaleImpactType->getPosition())
                    ->setCreator($connectedUser->getFirstname() . ' ' . $connectedUser->getLastname());

                $scaleImpactTypeCliTable->save($newScaleImpactType, false);

                $scalesImpactTypesOldIdsToNewObjectsMap[$scaleImpactType->getId()] = $newScaleImpactType;

                foreach ($scaleImpactType->getScaleComments() as $scaleComment) {
                    $this->createScaleCommentsFromSource(
                        $newAnr,
                        $newScale,
                        $newScaleImpactType,
                        $scaleComment,
                        $connectedUser
                    );
                }
            }

            foreach ($scale->getScaleComments() as $scaleComment) {
                if ($scaleComment->getScaleImpactType() === null) {
                    $this->createScaleCommentsFromSource($newAnr, $newScale, null, $scaleComment, $connectedUser);
                }
            }
        }

        return $scalesImpactTypesOldIdsToNewObjectsMap;
    }

    private function createScaleCommentsFromSource(
        Anr $newAnr,
        ScaleSuperClass $newScale,
        ?ScaleImpactTypeSuperClass $newScaleImpactType,
        ScaleCommentSuperClass $sourceScaleComment,
        UserSuperClass $connectedUser
    ): void {
        /** @var ScaleCommentTable $scaleCommentCliTable */
        $scaleCommentCliTable = $this->get('scaleCommentCliTable');

        $newScaleComment = (new ScaleComment())
            ->setAnr($newAnr)
            ->setScale($newScale)
            ->setScaleIndex($sourceScaleComment->getScaleIndex())
            ->setScaleValue($sourceScaleComment->getScaleValue())
            ->setComments([
                'comment1' => $sourceScaleComment->getComment(1),
                'comment2' => $sourceScaleComment->getComment(2),
                'comment3' => $sourceScaleComment->getComment(3),
                'comment4' => $sourceScaleComment->getComment(4),
            ])
            ->setCreator($connectedUser->getFirstname() . ' ' . $connectedUser->getLastname());
        if ($newScaleImpactType !== null) {
            $newScaleComment->setScaleImpactType($newScaleImpactType);
        }

        $scaleCommentCliTable->save($newScaleComment, false);
    }

    private function createOperationalRiskScalesFromSourceAnr(
        Anr $newAnr,
        AnrSuperClass $sourceAnr,
        string $sourceName,
        UserSuperClass $connectedUser
    ): array {
        $operationalScaleTypesOldIdsToNewObjectsMap = [];
        /** @var OperationalRiskScaleTable $operationalRiskScaleCliTable */
        $operationalRiskScaleCliTable = $this->get('operationalRiskScaleCliTable');
        /** @var OperationalRiskScaleTable|CoreOperationalRiskScaleTable $sourceOperationalRiskScaleTable */
        $sourceOperationalRiskScaleTable = $sourceName === MonarcObject::SOURCE_COMMON
            ? $this->get('operationalRiskScaleTable')
            : $operationalRiskScaleCliTable;
        /** @var OperationalRiskScaleTypeTable $operationalRiskScaleTypeCliTable */
        $operationalRiskScaleTypeCliTable = $this->get('operationalRiskScaleTypeCliTable');
        /** @var TranslationTable|CoreTranslationTable $sourceTranslationTable */
        $sourceTranslationTable = $sourceName === MonarcObject::SOURCE_COMMON
            ? $this->get('translationTable')
            : $this->get('translationCliTable');

        $anrLanguageCode = $this->getAnrLanguageCode($newAnr);

        $sourceTranslations = $sourceTranslationTable->findByAnrTypesAndLanguageIndexedByKey(
            $sourceAnr,
            [Translation::OPERATIONAL_RISK_SCALE_TYPE, Translation::OPERATIONAL_RISK_SCALE_COMMENT],
            $anrLanguageCode
        );

        $operationalRiskScales = $sourceOperationalRiskScaleTable->findByAnr($sourceAnr);
        foreach ($operationalRiskScales as $operationalRiskScale) {
            $newOperationalRiskScale = (new OperationalRiskScale())
                ->setAnr($newAnr)
                ->setType($operationalRiskScale->getType())
                ->setMin($operationalRiskScale->getMin())
                ->setMax($operationalRiskScale->getMax())
                ->setCreator($connectedUser->getEmail());

            foreach ($operationalRiskScale->getOperationalRiskScaleTypes() as $operationalRiskScaleType) {
                $newOperationalRiskScaleType = (new OperationalRiskScaleType())
                    ->setAnr($newAnr)
                    ->setOperationalRiskScale($newOperationalRiskScale)
                    ->setLabelTranslationKey($operationalRiskScaleType->getLabelTranslationKey())
                    ->setIsHidden($operationalRiskScaleType->isHidden())
                    ->setCreator($connectedUser->getEmail());

                $operationalRiskScaleTypeCliTable->save($newOperationalRiskScaleType, false);

                $operationalScaleTypesOldIdsToNewObjectsMap[$operationalRiskScaleType->getId()]
                    = $newOperationalRiskScaleType;

                $this->createTranslationFromSource(
                    $newAnr,
                    $sourceTranslations[$operationalRiskScaleType->getLabelTranslationKey()]
                        ?? (new Translation())
                            ->setType(Translation::OPERATIONAL_RISK_SCALE_TYPE)
                            ->setKey($operationalRiskScaleType->getLabelTranslationKey())
                            ->setLang($anrLanguageCode)
                            ->setValue(''),
                    $connectedUser
                );

                foreach ($operationalRiskScaleType->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                    $this->createOperationalRiskScaleCommentsFromSource(
                        $newAnr,
                        $newOperationalRiskScale,
                        $newOperationalRiskScaleType,
                        $operationalRiskScaleComment,
                        $sourceTranslations[$operationalRiskScaleComment->getCommentTranslationKey()]
                            ?? (new Translation())
                            ->setType(Translation::OPERATIONAL_RISK_SCALE_COMMENT)
                            ->setKey($operationalRiskScaleComment->getCommentTranslationKey())
                            ->setLang($anrLanguageCode)
                            ->setValue(''),
                        $connectedUser
                    );
                }
            }

            foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                if ($operationalRiskScaleComment->getOperationalRiskScaleType() !== null) {
                    continue;
                }

                $this->createOperationalRiskScaleCommentsFromSource(
                    $newAnr,
                    $newOperationalRiskScale,
                    null,
                    $operationalRiskScaleComment,
                    $sourceTranslations[$operationalRiskScaleComment->getCommentTranslationKey()]
                        ?? (new Translation())
                            ->setType(Translation::OPERATIONAL_RISK_SCALE_COMMENT)
                            ->setKey($operationalRiskScaleComment->getCommentTranslationKey())
                            ->setLang($anrLanguageCode)
                            ->setValue(''),
                    $connectedUser
                );
            }

            $operationalRiskScaleCliTable->save($newOperationalRiskScale, false);
        }

        return $operationalScaleTypesOldIdsToNewObjectsMap;
    }

    private function createOperationalRiskScaleCommentsFromSource(
        Anr $newAnr,
        OperationalRiskScaleSuperClass $newOperationalRiskScale,
        ?OperationalRiskScaleTypeSuperClass $newOperationalRiskScaleType,
        OperationalRiskScaleCommentSuperClass $sourceOperationalRiskScaleComment,
        TranslationSuperClass $sourceTranslation,
        UserSuperClass $connectedUser
    ): void {
        /** @var OperationalRiskScaleCommentTable $operationalRiskScaleCommentCliTable */
        $operationalRiskScaleCommentCliTable = $this->get('operationalRiskScaleCommentCliTable');

        $newOperationalRiskScaleComment = (new OperationalRiskScaleComment())
            ->setAnr($newAnr)
            ->setScaleIndex($sourceOperationalRiskScaleComment->getScaleIndex())
            ->setScaleValue($sourceOperationalRiskScaleComment->getScaleValue())
            ->setCommentTranslationKey($sourceOperationalRiskScaleComment->getCommentTranslationKey())
            ->setOperationalRiskScale($newOperationalRiskScale)
            ->setIsHidden($sourceOperationalRiskScaleComment->isHidden())
            ->setCreator($connectedUser->getEmail());
        if ($newOperationalRiskScaleType !== null) {
            $newOperationalRiskScaleComment->setOperationalRiskScaleType($newOperationalRiskScaleType);
        }

        $operationalRiskScaleCommentCliTable->save($newOperationalRiskScaleComment, false);

        $this->createTranslationFromSource($newAnr, $sourceTranslation, $connectedUser);
    }

    private function createTranslationFromSource(
        Anr $newAnr,
        TranslationSuperClass $sourceTranslation,
        UserSuperClass $connectedUser
    ): void {
        /** @var TranslationTable $translationCliTable */
        $translationCliTable = $this->get('translationCliTable');

        $newTranslation = (new Translation())
            ->setAnr($newAnr)
            ->setType($sourceTranslation->getType())
            ->setKey($sourceTranslation->getKey())
            ->setLang($sourceTranslation->getLang())
            ->setValue($sourceTranslation->getValue())
            ->setCreator($connectedUser->getEmail());

        $translationCliTable->save($newTranslation, false);
    }

    private function createOperationalInstanceRiskScalesFromSource(
        InstanceRiskOpSuperClass $instanceRiskOp,
        array $operationalScaleTypesOldIdsToNewObjectsMap,
        Anr $newAnr,
        InstanceRiskOp $newInstanceRiskOp,
        UserSuperClass $connectedUser
    ): void {
        /** @var OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleCliTable */
        $operationalInstanceRiskScaleCliTable = $this->get('operationalInstanceRiskScaleCliTable');
        foreach ($instanceRiskOp->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
            $operationalRiskScaleType = $operationalScaleTypesOldIdsToNewObjectsMap[
                $operationalInstanceRiskScale->getOperationalRiskScaleType()->getId()
            ];

            $operationalInstanceRiskScale = (new OperationalInstanceRiskScale())
                ->setAnr($newAnr)
                ->setOperationalInstanceRisk($newInstanceRiskOp)
                ->setOperationalRiskScaleType($operationalRiskScaleType)
                ->setBrutValue($operationalInstanceRiskScale->getBrutValue())
                ->setNetValue($operationalInstanceRiskScale->getNetValue())
                ->setTargetedValue($operationalInstanceRiskScale->getTargetedValue())
                ->setCreator($connectedUser->getEmail());
            $operationalInstanceRiskScaleCliTable->save($operationalInstanceRiskScale, false);
        }
    }

    private function duplicateVulnerabilities(
        AnrSuperClass $anr,
        AnrSuperClass $newAnr,
        Model $model,
        bool $isSourceCommon
    ): array {
        $vulnerabilitiesNewIds = [];
        /** @var CoreVulnerabilityTable $vulnerabilityCoreTable */
        $vulnerabilityCoreTable = $this->get('vulnerabilityTable');
        /** @var VulnerabilityTable $vulnerabilityClientTable */
        $vulnerabilityClientTable = $this->get('vulnerabilityCliTable');

        if ($isSourceCommon) {
            $vulnerabilities = [];
            if (!$model->isRegulator()) {
                $vulnerabilities = $vulnerabilityCoreTable->findByMode(Vulnerability::MODE_GENERIC);
            }
            if (!$model->isGeneric()) {
                $vulnerabilities = array_merge($vulnerabilities, $model->getVulnerabilities());
            }
        } else {
            $vulnerabilities = $vulnerabilityClientTable->findByAnr($anr);
        }

        foreach ($vulnerabilities as $vulnerability) {
            $newVulnerability = (new Vulnerability())
                ->setAnr($newAnr)
                ->setUuid($vulnerability->getUuid())
                ->setCode($vulnerability->getCode())
                ->setLabels([
                    'label1' => $vulnerability->getLabel(1),
                    'label2' => $vulnerability->getLabel(2),
                    'label3' => $vulnerability->getLabel(3),
                    'label4' => $vulnerability->getLabel(4),
                ])
                ->setDescriptions([
                    'description1' => $vulnerability->getDescription(1),
                    'description2' => $vulnerability->getDescription(2),
                    'description3' => $vulnerability->getDescription(3),
                    'description4' => $vulnerability->getDescription(4),
                ])
                ->setMode(VulnerabilitySuperClass::MODE_GENERIC)
                ->setStatus($vulnerability->getStatus())
                ->setCreator($this->getConnectedUser()->getEmail());

            $vulnerabilityClientTable->save($newVulnerability, false);
            $vulnerabilitiesNewIds[$vulnerability->getUuid()] = $newVulnerability;
        }

        return $vulnerabilitiesNewIds;
    }

    private function createAnrMetadatasOnInstancesFromSource(
        Anr $newAnr,
        AnrSuperClass $sourceAnr,
        string $sourceName,
        UserSuperClass $connectedUser
    ): array {

        $anrMetadatasOnInstancesOldIdsToNewObjectsMap = [];

        /** @var AnrMetadatasOnInstancesCliTable $anrMetadatasOnInstancesCliTable */
        $anrMetadatasOnInstancesCliTable = $this->get('anrMetadatasOnInstancesCliTable');

        /** @var AnrMetadatasOnInstancesCliTable|CoreAnrMetadatasOnInstancesCliTable $scaleTable */
        $anrMetadatasOnInstancesTable = $sourceName === MonarcObject::SOURCE_COMMON
            ? $this->get('anrMetadatasOnInstancesTable')
            : $anrMetadatasOnInstancesCliTable;

        /** @var TranslationTable|CoreTranslationTable $sourceTranslationTable */
        $sourceTranslationTable = $sourceName === MonarcObject::SOURCE_COMMON
            ? $this->get('translationTable')
            : $this->get('translationCliTable');

        $anrLanguageCode = $this->getAnrLanguageCode($newAnr);

        $oldAnrMetadatasOnInstances = $anrMetadatasOnInstancesTable->findByAnr($sourceAnr);
        foreach ($oldAnrMetadatasOnInstances as $oldAnrMetadata) {
            $newAnrMetadataOnInstance = (new AnrInstanceMetadataField())
                ->setAnr($newAnr)
                ->setLabelTranslationKey($oldAnrMetadata->getLabelTranslationKey())
                ->setIsDeletable($sourceName === MonarcObject::SOURCE_COMMON ? false : $oldAnrMetadata->isDeletable())
                ->setCreator($connectedUser->getEmail());
            $anrMetadatasOnInstancesCliTable->save($newAnrMetadataOnInstance, false);
            $anrMetadatasOnInstancesOldIdsToNewObjectsMap[$oldAnrMetadata->getId()] = $newAnrMetadataOnInstance;
        }

        $sourceTranslations = $sourceTranslationTable->findByAnrTypesAndLanguageIndexedByKey(
            $sourceAnr,
            [Translation::ANR_METADATAS_ON_INSTANCES],
            $anrLanguageCode
        );

        foreach ($sourceTranslations as $sourceTranslation) {
            $this->createTranslationFromSource($newAnr, $sourceTranslation, $connectedUser);
        }

        return $anrMetadatasOnInstancesOldIdsToNewObjectsMap;
    }

    private function createInstanceMetadatasFromSource(
        UserSuperClass $connectedUser,
        Instance $oldInstance,
        Instance $instance,
        Anr $sourceAnr,
        Anr $newAnr,
        array $anrMetadatasOnInstancesOldIdsToNewObjectsMap
    ) :void {
        $translations = [];
        $anrLanguageCode = $this->getAnrLanguageCode($newAnr);

        $sourceTranslationTable = $this->get('translationCliTable');
        $oldInstanceMetadatas = $oldInstance->getInstanceMetadata();
        foreach ($oldInstanceMetadatas as $oldInstanceMetadata) {
            $translationKey = $oldInstanceMetadata->getCommentTranslationKey();
            $instanceMetada = (new InstanceMetadata())
                ->setInstance($instance)
                ->setAnrInstanceMetadataField($anrMetadatasOnInstancesOldIdsToNewObjectsMap[
                                              $oldInstanceMetadata->getAnrInstanceMetadataFields()->getId()])
                ->setCommentTranslationKey($translationKey)
                ->setCreator($connectedUser->getEmail());

            $this->get('instanceMetadataCliTable')->save($instanceMetada, false);
            $instance->addInstanceMetadata($instanceMetada);

            $translations = $sourceTranslationTable->findByAnrTypesAndLanguageIndexedByKey(
                $sourceAnr,
                [Translation::INSTANCE_METADATA],
                $anrLanguageCode
            );
        }

        foreach ($translations as $translation) {
            $this->createTranslationFromSource($newAnr, $translation, $connectedUser);
        }
    }

    private function createSoaScaleCommentFromSource(Anr $newAnr, AnrSuperClass $sourceAnr, bool $isSourceCommon): array
    {
        $anrSoaScaleCommentOldIdsToNewObjectsMap = [];

        /** @var SoaScaleCommentCliTable $soaScaleCommentCliTable */
        $soaScaleCommentCliTable = $this->get('soaScaleCommentCliTable');

        /** @var SoaScaleCommentCliTable|SoaScaleCommentTable $scaleTable */
        $soaScaleCommentTable = $isSourceCommon
            ? $this->get('soaScaleCommentTable')
            : $soaScaleCommentCliTable;

        $oldSoaScaleComments = $soaScaleCommentTable->findByAnrOrderByIndex($sourceAnr);
        foreach ($oldSoaScaleComments as $oldSoaScaleComment) {
            // TODO: use the translation key of the Core entity to fetch from the translations + $newAnr->getLanguageCode();
            $comment = $isSourceCommon
                ? $oldSoaScaleComment->getComment($newAnr->getLanguage())
                : $oldSoaScaleComment->getComment();
            $newSoaScaleComment = (new SoaScaleComment())
                ->setAnr($newAnr)
                ->setComment($comment)
                ->setScaleIndex($oldSoaScaleComment->getScaleIndex())
                ->setColour($oldSoaScaleComment->getColour())
                ->setIsHidden($oldSoaScaleComment->isHidden())
                ->setCreator($this->connectedUser->getEmail());
            $soaScaleCommentCliTable->save($newSoaScaleComment, false);
            $anrSoaScaleCommentOldIdsToNewObjectsMap[$oldSoaScaleComment->getId()] = $newSoaScaleComment;
        }

        return $anrSoaScaleCommentOldIdsToNewObjectsMap;
    }
}
