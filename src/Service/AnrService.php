<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Monarc\Core\Entity as CoreEntity;
use Monarc\Core\Model\Table as CoreDeprecatedTable;
use Monarc\Core\Table as CoreTable;
use Monarc\Core\Service as CoreService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Table;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Throwable;

class AnrService
{
    private Entity\User $connectedUser;

    public function __construct(
        private Table\AnrTable $anrTable,
        private Table\UserAnrTable $userAnrTable,
        private Table\UserTable $userTable,
        private Table\InstanceTable $instanceTable,
        private Table\AmvTable $amvTable,
        private Table\MonarcObjectTable $monarcObjectTable,
        private Table\ObjectObjectTable $objectObjectTable,
        private Table\ScaleTable $scaleTable,
        private Table\ScaleImpactTypeTable $scaleImpactTypeTable,
        private Table\ScaleCommentTable $scaleCommentTable,
        private Table\AssetTable $assetTable,
        private Table\ThreatTable $threatTable,
        private Table\VulnerabilityTable $vulnerabilityTable,
        private Table\SoaScaleCommentTable $soaScaleCommentTable,
        private Table\InstanceMetadataTable $instanceMetadataTable,
        private Table\InstanceConsequenceTable $instanceConsequenceTable,
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private Table\OperationalRiskScaleTable $operationalRiskScaleTable,
        private Table\OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        private Table\OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable,
        private Table\OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        private Table\RecommendationRiskTable $recommendationRiskTable,
        private Table\RecommendationHistoryTable $recommendationHistoryTable,
        private Table\ReferentialTable $referentialTable,
        private Table\SoaCategoryTable $soaCategoryTable,
        private Table\MeasureTable $measureTable,
        private Table\RolfRiskTable $rolfRiskTable,
        private Table\RolfTagTable $rolfTagTable,
        private Table\SoaTable $soaTable,
        private DeprecatedTable\QuestionTable $questionTable,
        private DeprecatedTable\QuestionChoiceTable $questionChoiceTable,
        private DeprecatedTable\InterviewTable $interviewTable,
        private DeprecatedTable\RecordActorTable $recordActorTable,
        private DeprecatedTable\RecordDataCategoryTable $recordDataCategoryTable,
        private DeprecatedTable\RecordProcessorTable $recordProcessorTable,
        private DeprecatedTable\RecordRecipientTable $recordRecipientTable,
        private DeprecatedTable\RecordTable $recordTable,
        private DeprecatedTable\RecordPersonalDataTable $recordPersonalDataTable,
        private DeprecatedTable\RecordInternationalTransferTable $recordInternationalTransferTable,
        private CoreTable\ModelTable $modelTable,
        private CoreTable\ScaleTable $coreScaleTable,
        private CoreTable\AmvTable $coreAmvTable,
        private CoreTable\AssetTable $coreAssetTable,
        private CoreTable\ThreatTable $coreThreatTable,
        private CoreTable\VulnerabilityTable $coreVulnerabilityTable,
        private CoreTable\TranslationTable $coreTranslationTable,
        private CoreTable\SoaScaleCommentTable $coreSoaScaleCommentTable,
        private CoreTable\OperationalRiskScaleTable $coreOperationalRiskScaleTable,
        private CoreTable\ReferentialTable $coreReferentialTable,
        private CoreTable\RolfRiskTable $coreRolfRiskTable,
        private CoreTable\RolfTagTable $coreRolfTagTable,
        private CoreDeprecatedTable\QuestionTable $coreQuestionTable,
        private CoreDeprecatedTable\QuestionChoiceTable $coreQuestionChoiceTable,
        private AnrModelService $anrModelService,
        private AnrAmvService $anrAmvService,
        private AnrAssetService $anrAssetService,
        private AnrThreatService $anrThreatService,
        private AnrThemeService $anrThemeService,
        private AnrVulnerabilityService $anrVulnerabilityService,
        private AnrObjectService $anrObjectService,
        private AnrObjectCategoryService $anrObjectCategoryService,
        private AnrInstanceMetadataFieldService $anrInstanceMetadataFieldService,
        private InstanceRiskOwnerService $instanceRiskOwnerService,
        private AnrRecommendationSetService $anrRecommendationSetService,
        private AnrRecommendationService $anrRecommendationService,
        private AnrRecommendationRiskService $anrRecommendationRiskService,
        private SoaScaleCommentService $soaScaleCommentService,
        private CronTaskService $cronTaskService,
        private StatsAnrService $statsAnrService,
        private AnrRecordProcessorService $anrRecordProcessorService,
        private CoreService\ConfigService $configService,
        CoreService\ConnectedUserService $connectedUserService,
    ) {
        /** @var Entity\User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
    }

    public function getList(): array
    {
        /* For SUPER_ADMIN_FO all the analysis are fetched to be able to update the permissions. */
        $isSuperAdmin = $this->connectedUser->hasRole(Entity\UserRole::SUPER_ADMIN_FO);

        $anrData = [];
        if ($isSuperAdmin) {
            foreach ($this->anrTable->findAll() as $anr) {
                if (!$anr->isAnrSnapshot()) {
                    $anrData[] = $this->getPreparedAnrData(
                        $anr,
                        $this->userAnrTable->findByAnrAndUser($anr, $this->connectedUser)
                    );
                }
            }
        } else {
            foreach ($this->connectedUser->getUserAnrs() as $userAnr) {
                $anrData[] = $this->getPreparedAnrData($userAnr->getAnr(), $userAnr);
            }
        }

        return $anrData;
    }

    public function getAnrData(Entity\Anr $anr): array
    {
        $realAnrInUse = $anr;
        if ($anr->isAnrSnapshot()) {
            $realAnrInUse = $anr->getSnapshot()->getAnrReference();
        }
        $userAnr = $this->userAnrTable->findByAnrAndUser($realAnrInUse, $this->connectedUser);

        if ($realAnrInUse->getId() !== $this->connectedUser->getCurrentAnr()?->getId()) {
            $this->setCurrentAnrToConnectedUser($realAnrInUse, true);
        }

        return $this->getPreparedAnrData($anr, $userAnr, true);
    }

    /**
     * Creates a new analysis from a model that is in the common database.
     */
    public function createBasedOnModel(array $data): Entity\Anr
    {
        /** @var CoreEntity\Model $model */
        $model = $this->modelTable->findById((int)$data['model']);

        $availableLanguages = $this->anrModelService->getAvailableLanguages($model->getId());
        if (empty($availableLanguages[$data['language']])) {
            throw new Exception('Selected model\'s language is not supported', 412);
        }

        return $this->duplicateAnr($model->getAnr(), $data);
    }

    /**
     * Creates (Duplicates) an analysis based on existing one on the client's side or a model's anr
     * in the common database, and performs the biggest part of creation/restoring snapshots.
     */
    public function duplicateAnr(
        CoreEntity\AnrSuperClass $sourceAnr,
        array $data = [],
        bool $isSnapshotMode = false
    ): Entity\Anr {
        $isSourceCommon = $sourceAnr instanceof CoreEntity\Anr;
        if (!$isSourceCommon && !$isSnapshotMode) {
            /* Validate id the duplicated anr accessible for the user. */
            if (!$this->connectedUser->hasRole(Entity\UserRole::USER_ROLE_SYSTEM)
                && $this->userAnrTable->findByAnrAndUser($sourceAnr, $this->connectedUser) === null
            ) {
                throw new Exception('You are not authorized to duplicate this analysis', 412);
            }
        }

        if ($isSourceCommon) {
            /* Determine the language code when an analysis is created from a model. */
            $data['languageCode'] = strtolower($this->configService->getLanguageCodes()[$data['language']]);
        }

        $newAnr = Entity\Anr::constructFromObjectAndData($sourceAnr, $data)
            ->setCreator($this->connectedUser->getEmail());
        if ($isSnapshotMode) {
            /* The "[SNAP]" prefix is added for snapshots. */
            $newAnr->setLabel('[SNAP] ' . $newAnr->getLabel());
        }

        $this->anrTable->save($newAnr, false);

        /* Not needed for snapshots creation or restoring. */
        if (!$isSnapshotMode) {
            $userAnr = (new Entity\UserAnr())
                ->setUser($this->connectedUser)
                ->setAnr($newAnr)
                ->setRwd(Entity\UserAnr::FULL_PERMISSIONS_RWD)
                ->setCreator($this->connectedUser->getEmail());

            $this->userAnrTable->save($userAnr, false);
        }

        /* Recreates assets */
        $assetsOldIdsToNewObjects = $this->duplicateAssets($sourceAnr, $newAnr, $isSourceCommon);
        /* Recreates threats */
        $threatsOldIdsToNewObjects = $this->duplicateThreats($sourceAnr, $newAnr, $isSourceCommon);
        /* Recreates vulnerabilities */
        $vulnerabilitiesOldIdsToNewObjects = $this->duplicateVulnerabilities($sourceAnr, $newAnr, $isSourceCommon);

        /* Recreates Referential, SoaCategories, Measures and links from an existing anr, a snapshot or core. */
        $referentialsUuidsToCreate = !empty($data['referentials']) ? array_column($data['referentials'], 'uuid') : [];
        if ($sourceAnr instanceof Entity\Anr) {
            foreach ($sourceAnr->getReferentials() as $referential) {
                $referentialsUuidsToCreate[] = $referential->getUuid();
            }
        }
        $createdMeasuresUuidsToObjects = [];
        if (!empty($referentialsUuidsToCreate)) {
            $createdMeasuresUuidsToObjects = $this->updateReferentialsFromSource(
                $newAnr,
                $sourceAnr instanceof Entity\Anr ? $sourceAnr : null,
                $referentialsUuidsToCreate,
                false
            );
        }

        /* Recreate AMVs. */
        $amvsOldIdsToNewObjects = $this->duplicateAmvs(
            $sourceAnr,
            $newAnr,
            $isSourceCommon,
            $assetsOldIdsToNewObjects,
            $threatsOldIdsToNewObjects,
            $vulnerabilitiesOldIdsToNewObjects,
            $createdMeasuresUuidsToObjects
        );

        /* Recreate rolf tags and risks. */
        $rolfTagsOldIdsToNewObjects = $this->duplicateRolfTags($sourceAnr, $newAnr);
        $rolfRisksOldIdsToNewObjects = $this
            ->duplicateRolfRisks($sourceAnr, $newAnr, $rolfTagsOldIdsToNewObjects, $createdMeasuresUuidsToObjects);

        /* Recreate SOAs */
        $this->duplicateSoasAndSoaScaleComments($sourceAnr, $newAnr, $createdMeasuresUuidsToObjects, $isSourceCommon);

        /* Recreate Monarc objects and categories. */
        $monarcObjectsOldIdsToNewObjects = $this->duplicateObjectsAndCategories(
            $sourceAnr,
            $newAnr,
            $assetsOldIdsToNewObjects,
            $rolfTagsOldIdsToNewObjects,
            $isSourceCommon
        );

        /* Recreate AnrInstanceMetadataFields */
        $anrInstanceMetadataFieldOldIdsToNewObjects = $this
            ->duplicateAnrMetadataInstanceFields($sourceAnr, $newAnr, $isSourceCommon);

        /* Recreate Instances, InstanceRisks, InstanceConsequences and InstanceMetadata. */
        $this->duplicateInstancesTreeRisksSequencesRecommendationsMetadataAndScales(
            $sourceAnr,
            $newAnr,
            $amvsOldIdsToNewObjects,
            $assetsOldIdsToNewObjects,
            $threatsOldIdsToNewObjects,
            $vulnerabilitiesOldIdsToNewObjects,
            $monarcObjectsOldIdsToNewObjects,
            $anrInstanceMetadataFieldOldIdsToNewObjects,
            $rolfRisksOldIdsToNewObjects,
            $isSourceCommon
        );

        /* Recreate questions & choices. */
        $this->duplicateQuestions($sourceAnr, $newAnr, $isSourceCommon);

        if (!$isSourceCommon) {
            /* Recreate interviews. */
            $this->duplicateInterviews($sourceAnr, $newAnr);

            /* Recreate all the ROPA's related entities. */
            $this->duplicateRopa($sourceAnr, $newAnr);
        }

        if (!$isSnapshotMode) {
            $this->setCurrentAnrToConnectedUser($newAnr);
        }

        $this->anrTable->save($newAnr);

        return $newAnr;
    }

    public function patch(Entity\Anr $anr, array $data): Entity\Anr
    {
        /* Steps checkboxes setup. */
        if (isset($data['initAnrContext'])) {
            $anr->setInitAnrContext($data['initAnrContext']);
        }
        if (isset($data['initEvalContext'])) {
            $anr->setInitEvalContext($data['initEvalContext']);
        }
        if (isset($data['initRiskContext'])) {
            $anr->setInitRiskContext($data['initRiskContext']);
        }
        if (isset($data['initDefContext'])) {
            $anr->setInitDefContext($data['initDefContext']);
        }
        if (isset($data['modelImpacts'])) {
            $anr->setModelImpacts($data['modelImpacts']);
        }
        if (isset($data['modelSummary'])) {
            $anr->setModelSummary($data['modelSummary']);
        }
        if (isset($data['evalRisks'])) {
            $anr->setEvalRisks($data['evalRisks']);
        }
        if (isset($data['evalPlanRisks'])) {
            $anr->setEvalPlanRisks($data['evalPlanRisks']);
        }
        if (isset($data['manageRisks'])) {
            $anr->setManageRisks($data['manageRisks']);
        }
        /* Context establishment texts. */
        if (isset($data['contextAnaRisk'])) {
            $anr->setContextAnaRisk($data['contextAnaRisk']);
        }
        if (isset($data['synthThreat'])) {
            $anr->setSynthThreat($data['synthThreat']);
        }
        if (isset($data['contextGestRisk'])) {
            $anr->setContextGestRisk($data['contextGestRisk']);
        }
        if (isset($data['synthAct'])) {
            $anr->setSynthAct($data['synthAct']);
        }
        /* Label, description update */
        if (isset($data['label']) && $anr->getLabel() !== $data['label']) {
            $anr->setLabel($data['label']);
        }
        if (isset($data['description']) && $anr->getDescription() !== $data['description']) {
            $anr->setDescription($data['description']);
        }
        /* Update the thresholds. */
        if (isset($data['seuil1']) && $anr->getSeuil1() !== $data['seuil1']) {
            $anr->setSeuil1($data['seuil1']);
        }
        if (isset($data['seuil2']) && $anr->getSeuil2() !== $data['seuil2']) {
            $anr->setSeuil2($data['seuil2']);
        }
        if (isset($data['seuilRolf1']) && $anr->getSeuilRolf1() !== $data['seuilRolf1']) {
            $anr->setSeuilRolf1($data['seuilRolf1']);
        }
        if (isset($data['seuilRolf2']) && $anr->getSeuilRolf2() !== $data['seuilRolf2']) {
            $anr->setSeuilRolf2($data['seuilRolf2']);
        }

        if (isset($data['referentials'])) {
            $this->updateReferentialsFromSource($anr, null, array_column($data['referentials'], 'uuid'));
        }

        $anr->setUpdater($this->connectedUser->getEmail());

        $this->anrTable->save($anr);

        return $anr;
    }


    public function delete(Entity\Anr $anr): void
    {
        /* Try to drop the stats. */
        try {
            $this->statsAnrService->deleteStatsForAnr($anr->getUuid());
        } catch (Throwable) {
        }

        $this->anrTable->remove($anr);
    }

    private function getPreparedAnrData(
        Entity\Anr $anr,
        ?Entity\UserAnr $userAnr,
        bool $includeSnapshotDetails = false
    ): array {
        $referentialData = [];
        foreach ($anr->getReferentials() as $referential) {
            $referentialData[] = [
                'uuid' => $referential->getUuid(),
                'label' . $anr->getLanguage() => $referential->getLabel($anr->getLanguage()),
            ];
        }

        $anrData = [
            'id' => $anr->getId(),
            'uuid' => $anr->getUuid(),
            'label' => $anr->getLabel(),
            'description' => $anr->getDescription(),
            'rwd' => $userAnr === null ? -1 : $userAnr->getRwd(),
            'referentials' => $referentialData,
            'isCurrentAnr' => (int)($this->connectedUser->getCurrentAnr() !== null
                && $this->connectedUser->getCurrentAnr()->getId() === $anr->getId()),
            'status' => $anr->getStatus(),
            'creator' => $anr->getCreator(),
            'createdAt' => $anr->getCreatedAt()->format('d/m/Y H:i'),
            'language' => $anr->getLanguage(),
            'languageCode' => $anr->getLanguageCode(),
            'cacheModelAreScalesUpdatable' => (int)$anr->getCacheModelAreScalesUpdatable(),
            'cacheModelShowRolfBrut' => (int)$anr->getCacheModelShowRolfBrut(),
            'contextAnaRisk' => $anr->getContextAnaRisk(),
            'contextGestRisk' => $anr->getContextGestRisk(),
            'evalLivrableDone' => $anr->getEvalLivrableDone(),
            'evalPlanRisks' => $anr->getEvalPlanRisks(),
            'evalRisks' => $anr->getEvalRisks(),
            'initAnrContext' => $anr->getInitAnrContext(),
            'initDefContext' => $anr->getInitDefContext(),
            'initEvalContext' => $anr->getInitEvalContext(),
            'initLivrableDone' => $anr->getInitLivrableDone(),
            'initRiskContext' => $anr->getInitRiskContext(),
            'isSnapshot' => (int)$anr->isAnrSnapshot(),
            'isStatsCollected' => (int)$anr->isStatsCollected(),
            'isVisibleOnDashboard' => $anr->isVisibleOnDashboard(),
            'manageRisks' => $anr->getManageRisks(),
            'model' => $anr->getModelId(),
            'modelImpacts' => $anr->getModelImpacts(),
            'modelLivrableDone' => $anr->getModelLivrableDone(),
            'modelSummary' => $anr->getModelSummary(),
            'seuil1' => $anr->getSeuil1(),
            'seuil2' => $anr->getSeuil2(),
            'seuilRolf1' => $anr->getSeuilRolf1(),
            'seuilRolf2' => $anr->getSeuilRolf2(),
            'showRolfBrut' => (int)$anr->showRolfBrut(),
            'seuilTraitement' => $anr->getSeuilTraitement(),
            'synthAct' => $anr->getSynthAct(),
            'synthThreat' => $anr->getSynthThreat(),
        ];

        /* Check if the Anr is under background import. */
        $anrData['importStatus'] = [];
        if ($anr->getStatus() === CoreEntity\AnrSuperClass::STATUS_UNDER_IMPORT) {
            $importCronTask = $this->cronTaskService->getLatestTaskByNameWithParam(
                Entity\CronTask::NAME_INSTANCE_IMPORT,
                ['anrId' => $anr->getId()]
            );
            if ($importCronTask !== null && $importCronTask->getStatus() === Entity\CronTask::STATUS_IN_PROGRESS) {
                $timeDiff = $importCronTask->getUpdatedAt() !== null
                    ? $importCronTask->getUpdatedAt()->diff(new DateTime())
                    : $importCronTask->getCreatedAt()->diff(new DateTime());
                $instancesNumber = $this->instanceTable->countByAnrIdFromDate(
                    $anr->getId(),
                    $importCronTask->getUpdatedAt() ?? $importCronTask->getCreatedAt()
                );
                $anrData['importStatus'] = [
                    'executionTime' => $timeDiff->h . ' hours ' . $timeDiff->i . ' min ' . $timeDiff->s . ' sec',
                    'createdInstances' => $instancesNumber,
                ];
            }
        }

        if ($includeSnapshotDetails) {
            $anrData['isSnapshot'] = (int)$anr->isAnrSnapshot();
            $anrData['snapshotParent'] = $anr->isAnrSnapshot()
                ? $anr->getSnapshot()?->getAnrReference()->getId()
                : null;
            if ($anr->isAnrSnapshot()) {
                $anrData['rwd'] = 0;
            }
        }

        return $anrData;
    }

    /**
     * @param string[] $referentialUuids
     *
     * @return Entity\Measure[] Returns list of created measures with uuids as keys ['UUID' => Measure].
     */
    private function updateReferentialsFromSource(
        Entity\Anr $anr,
        ?Entity\Anr $sourceAnr,
        array $referentialUuids,
        bool $recreateAmvRolfRiskAndSoaLinks = true
    ): array {
        $linkedReferentialUuids = [];
        /* Removes already linked referentials from the list and unlink if not presented. */
        foreach ($anr->getReferentials() as $referential) {
            $foundUuidKey = array_search($referential->getUuid(), $referentialUuids, true);
            if ($foundUuidKey !== false) {
                unset($referentialUuids[$foundUuidKey]);
                $linkedReferentialUuids[] = $referential->getUuid();
            } else {
                /* The operation of removal is not supported in the UI. */
                $anr->removeReferential($referential);
                $this->referentialTable->remove($referential, false);
            }
        }

        $createdMeasuresUuidsToObjects = [];
        /* Links new referential to the analysis from core or the source anr. */
        foreach ($referentialUuids as $referentialUuid) {
            if ($sourceAnr === null) {
                $referentialFromSource = $this->coreReferentialTable->findByUuid($referentialUuid);
            } else {
                $referentialFromSource = $this->referentialTable->findByUuidAndAnr($referentialUuid, $sourceAnr);
            }

            /* Recreate the source's or core's referential in the analysis.  */
            $referential = (new Entity\Referential())
                ->setUuid($referentialUuid)
                ->setAnr($anr)
                ->setLabels($referentialFromSource->getLabels())
                ->setUuid($referentialFromSource->getUuid())
                ->setCreator($this->connectedUser->getEmail());

            $this->referentialTable->save($referential, false);
            $linkedReferentialUuids[] = $referential->getUuid();

            $categoriesBySourceIds = [];
            foreach ($referentialFromSource->getCategories() as $categoryFromSource) {
                $soaCategory = (new Entity\SoaCategory())
                    ->setAnr($anr)
                    ->setReferential($referential)
                    ->setLabels($categoryFromSource->getLabels());
                $this->soaCategoryTable->save($soaCategory, false);
                $categoriesBySourceIds[$categoryFromSource->getId()] = $soaCategory;
            }

            /* Recreates the measures in the analysis. */
            foreach ($referentialFromSource->getMeasures() as $measureFromSource) {
                $measure = (new Entity\Measure())
                    ->setUuid($measureFromSource->getUuid())
                    ->setAnr($anr)
                    ->setCode($measureFromSource->getCode())
                    ->setLabels($measureFromSource->getLabels())
                    ->setStatus($measureFromSource->getStatus())
                    ->setReferential($referential)
                    ->setCategory($categoriesBySourceIds[$measureFromSource->getCategory()->getId()])
                    ->setCreator($this->connectedUser->getEmail());
                /* Recreate measures (controls) mapping links across different referential. */
                foreach ($measureFromSource->getLinkedMeasures() as $linkedMeasureFromSource) {
                    /* Validate if the referential of the linked measure is already create,
                    otherwise it will be linked later if the referential is added to the analysis. */
                    if (!\in_array(
                        $linkedMeasureFromSource->getReferential()->getUuid(),
                        $linkedReferentialUuids,
                        true
                    )) {
                        continue;
                    }

                    /* Validates if the linked measure from the common DB presented in the client's DB. */
                    $linkedMeasure = $createdMeasuresUuidsToObjects[$linkedMeasureFromSource->getUuid()]
                        ?? $this->measureTable->findByUuidAndAnr($linkedMeasureFromSource->getUuid(), $anr);
                    if ($linkedMeasure === null) {
                        continue;
                    }
                    /* Recreates the bi-directional links that are defined on the BackOffice side / common DB. */
                    $measure->addLinkedMeasure($linkedMeasure);
                }

                if ($recreateAmvRolfRiskAndSoaLinks) {
                    /* Recreate links with AMVs (information risks) and rolf risks (operation) from source's controls */
                    foreach ($measureFromSource->getAmvs() as $amvFromSource) {
                        $amv = $this->amvTable->findByAmvItemsUuidsAndAnr(
                            $amvFromSource->getAsset()->getUuid(),
                            $amvFromSource->getThreat()->getUuid(),
                            $amvFromSource->getVulnerability()->getUuid(),
                            $anr
                        );
                        if ($amv !== null) {
                            $measure->addAmv($amv);
                        }
                    }
                    foreach ($measureFromSource->getRolfRisks() as $rolfRiskFromSource) {
                        $rolfRisk = $this->rolfRiskTable->findByAnrAndCode($anr, $rolfRiskFromSource->getCode());
                        if ($rolfRisk !== null
                            && $rolfRisk->getLabel($anr->getLanguage()) === $rolfRiskFromSource
                                ->getLabel($anr->getLanguage())
                        ) {
                            $measure->addRolfRisk($rolfRisk);
                        }
                    }

                    /* Recreate SOA link with measure. */
                    $this->soaTable->save((new Entity\Soa())->setAnr($anr)->setMeasure($measure), false);
                }

                $this->measureTable->save($measure, false);
                $createdMeasuresUuidsToObjects[$measure->getUuid()] = $measure;
            }
        }

        return $createdMeasuresUuidsToObjects;
    }

    private function setCurrentAnrToConnectedUser(Entity\Anr $anr, bool $saveInDb = false): void
    {
        $this->userTable->save($this->connectedUser->setCurrentAnr($anr), $saveInDb);
    }

    private function duplicateScales(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        bool $isSourceCommon
    ): array {
        $scalesImpactTypesOldIdsToNewObjects = [];
        $scaleTable = $isSourceCommon ? $this->coreScaleTable : $this->scaleTable;
        /** @var CoreEntity\ScaleSuperClass $sourceScale */
        foreach ($scaleTable->findByAnr($sourceAnr) as $sourceScale) {
            $newScale = (new Entity\Scale($newAnr, [
                'min' => $sourceScale->getMin(),
                'max' => $sourceScale->getMax(),
                'type' => $sourceScale->getType(),
            ]))->setCreator($this->connectedUser->getEmail());
            $this->scaleTable->save($newScale, false);

            foreach ($sourceScale->getScaleImpactTypes() as $sourceScaleImpactType) {
                $newScaleImpactType = (new Entity\ScaleImpactType())
                    ->setAnr($newAnr)
                    ->setScale($newScale)
                    ->setIsHidden($sourceScaleImpactType->isHidden())
                    ->setIsSys($sourceScaleImpactType->isSys())
                    ->setLabels($sourceScaleImpactType->getLabels())
                    ->setType($sourceScaleImpactType->getType())
                    ->setCreator($this->connectedUser->getEmail());
                $this->scaleImpactTypeTable->save($newScaleImpactType, false);

                $scalesImpactTypesOldIdsToNewObjects[$sourceScaleImpactType->getId()] = $newScaleImpactType;

                foreach ($sourceScaleImpactType->getScaleComments() as $sourceScaleComment) {
                    $this->duplicateScaleComments($newAnr, $newScale, $newScaleImpactType, $sourceScaleComment);
                }
            }

            foreach ($sourceScale->getScaleComments() as $sourceScaleComment) {
                if ($sourceScaleComment->getScaleImpactType() === null) {
                    $this->duplicateScaleComments($newAnr, $newScale, null, $sourceScaleComment);
                }
            }
        }

        return $scalesImpactTypesOldIdsToNewObjects;
    }

    private function duplicateScaleComments(
        Entity\Anr $newAnr,
        Entity\Scale $newScale,
        ?Entity\ScaleImpactType $newScaleImpactType,
        CoreEntity\ScaleCommentSuperClass $sourceScaleComment
    ): void {
        $newScaleComment = (new Entity\ScaleComment())
            ->setAnr($newAnr)
            ->setScale($newScale)
            ->setScaleIndex($sourceScaleComment->getScaleIndex())
            ->setScaleValue($sourceScaleComment->getScaleValue())
            ->setComments($sourceScaleComment->getComments())
            ->setCreator($this->connectedUser->getEmail());
        if ($newScaleImpactType !== null) {
            $newScaleComment->setScaleImpactType($newScaleImpactType);
        }

        $this->scaleCommentTable->save($newScaleComment, false);
    }

    private function duplicateOperationalRiskScales(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        bool $isSourceCommon
    ): array {
        $sourceTranslations = [];
        if ($isSourceCommon) {
            $sourceTranslations = $this->coreTranslationTable->findByAnrTypesAndLanguageIndexedByKey(
                $sourceAnr,
                [
                    CoreEntity\TranslationSuperClass::OPERATIONAL_RISK_SCALE_TYPE,
                    CoreEntity\TranslationSuperClass::OPERATIONAL_RISK_SCALE_COMMENT,
                ],
                $newAnr->getLanguageCode()
            );
        }

        $operationalScaleTypesOldIdsToNewObjects = [];
        $sourceOperationalRiskScaleTable = $isSourceCommon
            ? $this->coreOperationalRiskScaleTable
            : $this->operationalRiskScaleTable;
        /** @var CoreEntity\OperationalRiskScaleSuperClass $sourceOperationalRiskScale */
        foreach ($sourceOperationalRiskScaleTable->findByAnr($sourceAnr) as $sourceOperationalRiskScale) {
            $newOperationalRiskScale = (new Entity\OperationalRiskScale())
                ->setAnr($newAnr)
                ->setType($sourceOperationalRiskScale->getType())
                ->setMin($sourceOperationalRiskScale->getMin())
                ->setMax($sourceOperationalRiskScale->getMax())
                ->setCreator($this->connectedUser->getEmail());

            foreach ($sourceOperationalRiskScale->getOperationalRiskScaleTypes() as $operationalRiskScaleType) {
                if ($isSourceCommon) {
                    /** @var CoreEntity\OperationalRiskScaleType $operationalRiskScaleType */
                    $label = isset($sourceTranslations[$operationalRiskScaleType->getLabelTranslationKey()])
                        ? $sourceTranslations[$operationalRiskScaleType->getLabelTranslationKey()]->getValue()
                        : '';
                } else {
                    /** @var Entity\OperationalRiskScaleType $operationalRiskScaleType */
                    $label = $operationalRiskScaleType->getLabel();
                }
                $newOperationalRiskScaleType = (new Entity\OperationalRiskScaleType())
                    ->setAnr($newAnr)
                    ->setOperationalRiskScale($newOperationalRiskScale)
                    ->setLabel($label)
                    ->setIsHidden($operationalRiskScaleType->isHidden())
                    ->setCreator($this->connectedUser->getEmail());

                $this->operationalRiskScaleTypeTable->save($newOperationalRiskScaleType, false);

                $operationalScaleTypesOldIdsToNewObjects[$operationalRiskScaleType->getId()]
                    = $newOperationalRiskScaleType;

                foreach ($operationalRiskScaleType->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                    $this->duplicateOperationalRiskScaleComments(
                        $newAnr,
                        $newOperationalRiskScale,
                        $newOperationalRiskScaleType,
                        $operationalRiskScaleComment,
                        $sourceTranslations
                    );
                }
            }

            foreach ($sourceOperationalRiskScale->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
                if ($operationalRiskScaleComment->getOperationalRiskScaleType() !== null) {
                    continue;
                }

                $this->duplicateOperationalRiskScaleComments(
                    $newAnr,
                    $newOperationalRiskScale,
                    null,
                    $operationalRiskScaleComment,
                    $sourceTranslations
                );
            }

            $this->operationalRiskScaleTable->save($newOperationalRiskScale, false);
        }

        return $operationalScaleTypesOldIdsToNewObjects;
    }

    /**
     * @param CoreEntity\Translation[] $sourceTranslations
     */
    private function duplicateOperationalRiskScaleComments(
        Entity\Anr $newAnr,
        Entity\OperationalRiskScale $newOperationalRiskScale,
        ?Entity\OperationalRiskScaleType $newOperationalRiskScaleType,
        CoreEntity\OperationalRiskScaleCommentSuperClass $sourceOperationalRiskScaleComment,
        array $sourceTranslations
    ): void {
        if ($sourceOperationalRiskScaleComment instanceof CoreEntity\OperationalRiskScaleComment) {
            $comment = isset($sourceTranslations[$sourceOperationalRiskScaleComment->getLabelTranslationKey()])
                ? $sourceTranslations[$sourceOperationalRiskScaleComment->getLabelTranslationKey()]->getValue()
                : '';
        } else {
            /** @var Entity\OperationalRiskScaleComment $sourceOperationalRiskScaleComment */
            $comment = $sourceOperationalRiskScaleComment->getComment();
        }
        $newOperationalRiskScaleComment = (new Entity\OperationalRiskScaleComment())
            ->setAnr($newAnr)
            ->setScaleIndex($sourceOperationalRiskScaleComment->getScaleIndex())
            ->setScaleValue($sourceOperationalRiskScaleComment->getScaleValue())
            ->setComment($comment)
            ->setOperationalRiskScale($newOperationalRiskScale)
            ->setIsHidden($sourceOperationalRiskScaleComment->isHidden())
            ->setCreator($this->connectedUser->getEmail());
        if ($newOperationalRiskScaleType !== null) {
            $newOperationalRiskScaleComment->setOperationalRiskScaleType($newOperationalRiskScaleType);
        }

        $this->operationalRiskScaleCommentTable->save($newOperationalRiskScaleComment, false);
    }

    private function duplicateAssets(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        bool $isSourceCommon
    ): array {
        $assetsOldIdsToNewObjects = [];
        if ($isSourceCommon) {
            $assets = $this->coreAssetTable->findByMode(CoreEntity\AssetSuperClass::MODE_GENERIC);
            /** @var CoreEntity\Anr $sourceAnr */
            if (!$sourceAnr->getModel()->isGeneric()) {
                $assets = array_merge($assets, $sourceAnr->getModel()->getAssets()->toArray());
            }
        } else {
            $assets = $this->assetTable->findByAnr($sourceAnr);
        }

        foreach ($assets as $asset) {
            $assetUuid = $asset->getUuid();
            if (!isset($assetsOldIdsToNewObjects[$assetUuid])) {
                $assetsOldIdsToNewObjects[$assetUuid] = $this->anrAssetService->create(
                    $newAnr,
                    array_merge([
                        'uuid' => $assetUuid,
                        'code' => $asset->getCode(),
                        'type' => $asset->getType(),
                        'status' => $asset->getStatus(),
                    ], $asset->getLabels(), $asset->getDescriptions()),
                    false
                );
            }
        }

        return $assetsOldIdsToNewObjects;
    }

    private function duplicateThreats(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        bool $isSourceCommon
    ): array {
        $threatsOldIdsToNewObjects = [];
        if ($isSourceCommon) {
            $threats = $this->coreThreatTable->findByMode(CoreEntity\ThreatSuperClass::MODE_GENERIC);
            /** @var CoreEntity\Anr $sourceAnr */
            if (!$sourceAnr->getModel()->isGeneric()) {
                $threats = array_merge($threats, $sourceAnr->getModel()->getThreats()->toArray());
            }
        } else {
            $threats = $this->threatTable->findByAnr($sourceAnr);
        }

        $themesOldIdsToNewObjects = [];
        foreach ($threats as $threat) {
            $threatUuid = $threat->getUuid();
            if (isset($threatsOldIdsToNewObjects[$threatUuid])) {
                continue;
            }
            if ($threat->getTheme() !== null && !isset($themesOldIdsToNewObjects[$threat->getTheme()->getId()])) {
                $themesOldIdsToNewObjects[$threat->getTheme()->getId()] = $this->anrThemeService->create(
                    $newAnr,
                    $threat->getTheme()->getLabels(),
                    false
                );
            }
            $threatsOldIdsToNewObjects[$threatUuid] = $this->anrThreatService->create(
                $newAnr,
                array_merge([
                    'uuid' => $threatUuid,
                    'code' => $threat->getCode(),
                    'theme' => $threat->getTheme() !== null
                        ? $themesOldIdsToNewObjects[$threat->getTheme()->getId()]
                        : null,
                    'comment' => $threat->getComment(),
                    'c' => $threat->getConfidentiality(),
                    'i' => $threat->getIntegrity(),
                    'a' => $threat->getAvailability(),
                    'trend' => $threat->getTrend(),
                    'qualification' => $threat->getQualification(),
                    'status' => $threat->getStatus(),
                ], $threat->getLabels(), $threat->getDescriptions()),
                false
            );
        }

        return $threatsOldIdsToNewObjects;
    }

    private function duplicateVulnerabilities(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        bool $isSourceCommon
    ): array {
        $vulnerabilitiesOldIdsToNewObjects = [];
        if ($isSourceCommon) {
            $vulnerabilities = $this->coreVulnerabilityTable->findByMode(
                CoreEntity\VulnerabilitySuperClass::MODE_GENERIC
            );
            /** @var CoreEntity\Anr $sourceAnr */
            if (!$sourceAnr->getModel()->isGeneric()) {
                $vulnerabilities = array_merge(
                    $vulnerabilities,
                    $sourceAnr->getModel()->getVulnerabilities()->toArray()
                );
            }
        } else {
            $vulnerabilities = $this->vulnerabilityTable->findByAnr($sourceAnr);
        }

        foreach ($vulnerabilities as $vulnerability) {
            $vulnerabilityUuid = $vulnerability->getUuid();
            if (!isset($vulnerabilitiesOldIdsToNewObjects[$vulnerabilityUuid])) {
                $vulnerabilitiesOldIdsToNewObjects[$vulnerabilityUuid] = $this->anrVulnerabilityService->create(
                    $newAnr,
                    array_merge([
                        'uuid' => $vulnerabilityUuid,
                        'code' => $vulnerability->getCode(),
                        'status' => $vulnerability->getStatus(),
                    ], $vulnerability->getLabels(), $vulnerability->getDescriptions()),
                    false
                );
            }
        }

        return $vulnerabilitiesOldIdsToNewObjects;
    }

    private function duplicateAmvs(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        bool $isSourceCommon,
        array $assetsOldIdsToNewObjects,
        array $threatsOldIdsToNewObjects,
        array $vulnerabilitiesOldIdsToNewObjects,
        array $createdMeasuresUuidsToObjects
    ): array {
        $amvsOldIdsToNewObjects = [];
        /** @var CoreEntity\AmvSuperClass[] $sourceAmvs */
        $sourceAmvs = $isSourceCommon ? $this->coreAmvTable->findAll() : $this->amvTable->findByAnr($sourceAnr);
        foreach ($sourceAmvs as $sourceAmv) {
            if (!isset(
                $assetsOldIdsToNewObjects[$sourceAmv->getAsset()->getUuid()],
                $threatsOldIdsToNewObjects[$sourceAmv->getThreat()->getUuid()],
                $vulnerabilitiesOldIdsToNewObjects[$sourceAmv->getVulnerability()->getUuid()]
            )) {
                continue;
            }

            $newAmv = $this->anrAmvService->createAmvFromPreparedData(
                $newAnr,
                $assetsOldIdsToNewObjects[$sourceAmv->getAsset()->getUuid()],
                $threatsOldIdsToNewObjects[$sourceAmv->getThreat()->getUuid()],
                $vulnerabilitiesOldIdsToNewObjects[$sourceAmv->getVulnerability()->getUuid()],
                [
                    'uuid' => $sourceAmv->getUuid(),
                    'status' => $sourceAmv->getStatus(),
                    'setOnlyExactPosition' => true,
                    'position' => $sourceAmv->getPosition(),

                ],
                false,
                false
            );
            foreach ($sourceAmv->getMeasures() as $measure) {
                if (isset($createdMeasuresUuidsToObjects[$measure->getUuid()])) {
                    $newAmv->addMeasure($createdMeasuresUuidsToObjects[$measure->getUuid()]);
                }
            }
            $this->amvTable->save($newAmv, false);
            $amvsOldIdsToNewObjects[$sourceAmv->getUuid()] = $newAmv;
        }

        return $amvsOldIdsToNewObjects;
    }

    private function duplicateRolfTags(CoreEntity\AnrSuperClass $sourceAnr, Entity\Anr $newAnr): array
    {
        $rolfTagsOldIdsToNewObjects = [];

        /** @var CoreEntity\RolfTagSuperClass $sourceRolfTags */
        $sourceRolfTags = $sourceAnr instanceof Entity\Anr
            ? $this->rolfTagTable->findByAnr($sourceAnr)
            : $this->coreRolfTagTable->findAll();
        foreach ($sourceRolfTags as $sourceRolfTag) {
            $newRolfTag = (new Entity\RolfTag())
                ->setAnr($newAnr)
                ->setLabels($sourceRolfTag->getLabels())
                ->setCode($sourceRolfTag->getCode())
                ->setCreator($this->connectedUser->getEmail());

            $this->rolfTagTable->save($newRolfTag, false);
            $rolfTagsOldIdsToNewObjects[$sourceRolfTag->getId()] = $newRolfTag;
        }

        return $rolfTagsOldIdsToNewObjects;
    }

    private function duplicateRolfRisks(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        array $rolfTagsOldIdsToNewObjects,
        array $createdMeasuresUuidsToObjects
    ): array {
        $rolfRisksOldIdsToNewObjects = [];
        /** @var Entity\RolfRisk[] $sourceRolfRisks */
        $sourceRolfRisks = $sourceAnr instanceof Entity\Anr
            ? $this->rolfRiskTable->findByAnr($sourceAnr)
            : $this->coreRolfRiskTable->findAll();
        foreach ($sourceRolfRisks as $sourceRolfRisk) {
            $newRolfRisk = (new Entity\RolfRisk())
                ->setAnr($newAnr)
                ->setCode($sourceRolfRisk->getCode())
                ->setLabels($sourceRolfRisk->getLabels())
                ->setDescriptions($sourceRolfRisk->getDescriptions())
                ->setCreator($this->connectedUser->getEmail());

            foreach ($sourceRolfRisk->getTags() as $sourceRolfTag) {
                if (isset($rolfTagsOldIdsToNewObjects[$sourceRolfTag->getId()])) {
                    $newRolfRisk->addTag($rolfTagsOldIdsToNewObjects[$sourceRolfTag->getId()]);
                }
            }

            foreach ($sourceRolfRisk->getMeasures() as $sourceMeasure) {
                if (isset($createdMeasuresUuidsToObjects[$sourceMeasure->getUuid()])) {
                    $newRolfRisk->addMeasure($createdMeasuresUuidsToObjects[$sourceMeasure->getUuid()]);
                }
            }

            $this->rolfRiskTable->save($newRolfRisk, false);
            $rolfRisksOldIdsToNewObjects[$sourceRolfRisk->getId()] = $newRolfRisk;
        }

        return $rolfRisksOldIdsToNewObjects;
    }

    private function duplicateAnrMetadataInstanceFields(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        bool $isSourceCommon,
    ): array {
        $commonTranslations = [];
        if ($isSourceCommon) {
            $commonTranslations = $this->coreTranslationTable->findByAnrTypesAndLanguageIndexedByKey(
                $sourceAnr,
                [CoreEntity\TranslationSuperClass::ANR_INSTANCE_METADATA_FIELD],
                $newAnr->getLanguageCode()
            );
        }
        $anrInstanceMetadataFieldOldIdsToNewObjects = [];
        foreach ($sourceAnr->getAnrInstanceMetadataFields() as $sourceAnrInstanceMetadataField) {
            $isDeletable = false;
            if ($isSourceCommon) {
                /** @var CoreEntity\AnrInstanceMetadataField $sourceAnrInstanceMetadataField */
                $label = (string)($commonTranslations[$sourceAnrInstanceMetadataField->getLabelTranslationKey()]
                    ?->getValue());
            } else {
                /** @var Entity\AnrInstanceMetadataField $sourceAnrInstanceMetadataField */
                $label = $sourceAnrInstanceMetadataField->getLabel();
                $isDeletable = $sourceAnrInstanceMetadataField->isDeletable();
            }
            $anrInstanceMetadataFieldOldIdsToNewObjects[$sourceAnrInstanceMetadataField->getId()] = $this
                ->anrInstanceMetadataFieldService->create($newAnr, [[
                    $newAnr->getLanguageCode() => $label,
                    'isDeletable' => $isDeletable,
                ]], false);
        }

        return $anrInstanceMetadataFieldOldIdsToNewObjects;
    }

    private function duplicateInstanceMetadata(
        Entity\Instance $sourceInstance,
        Entity\Instance $newInstance,
        array $anrInstanceMetadataFieldOldIdsToNewObjects
    ): void {
        foreach ($sourceInstance->getInstanceMetadata() as $sourceInstanceMetadata) {
            $anrInstanceMetadataField = $anrInstanceMetadataFieldOldIdsToNewObjects[
                $sourceInstanceMetadata->getAnrInstanceMetadataField()->getId()
            ];
            if ($anrInstanceMetadataField !== null) {
                $instanceMetadata = (new Entity\InstanceMetadata())
                    ->setInstance($newInstance)
                    ->setAnrInstanceMetadataField($anrInstanceMetadataField)
                    ->setComment($sourceInstanceMetadata->getComment())
                    ->setCreator($this->connectedUser->getEmail());
                $this->instanceMetadataTable->save($instanceMetadata, false);
            }
        }
    }

    private function duplicateSoasAndSoaScaleComments(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        array $createdMeasuresUuidsToObjects,
        bool $isSourceCommon
    ): void {
        $commonTranslations = [];
        if ($isSourceCommon) {
            $commonTranslations = $this->coreTranslationTable->findByAnrTypesAndLanguageIndexedByKey(
                $sourceAnr,
                [CoreEntity\TranslationSuperClass::SOA_SCALE_COMMENT],
                $newAnr->getLanguageCode()
            );
        }

        $anrSoaScaleCommentOldIdsToNewObjects = [];
        $soaScaleCommentTable = $isSourceCommon ? $this->coreSoaScaleCommentTable : $this->soaScaleCommentTable;
        foreach ($soaScaleCommentTable->findByAnrOrderByIndex($sourceAnr) as $sourceSoaScaleComment) {
            if ($isSourceCommon) {
                /** @var CoreEntity\SoaScaleComment $sourceSoaScaleComment */
                $comment = isset($commonTranslations[$sourceSoaScaleComment->getLabelTranslationKey()])
                    ? $commonTranslations[$sourceSoaScaleComment->getLabelTranslationKey()]->getValue()
                    : '';
            } else {
                /** @var Entity\SoaScaleComment $sourceSoaScaleComment */
                $comment = $sourceSoaScaleComment->getComment();
            }
            $anrSoaScaleCommentOldIdsToNewObjects[$sourceSoaScaleComment->getId()] = $this->soaScaleCommentService
                ->createSoaScaleComment(
                    $newAnr,
                    $sourceSoaScaleComment->getScaleIndex(),
                    $sourceSoaScaleComment->getColour(),
                    $comment,
                    $sourceSoaScaleComment->isHidden()
                );
        }

        if ($isSourceCommon) {
            foreach ($createdMeasuresUuidsToObjects as $measure) {
                $this->soaTable->save((new Entity\Soa())->setAnr($newAnr)->setMeasure($measure), false);
            }
        } else {
            /** @var Entity\Soa $sourceSoa */
            foreach ($this->soaTable->findByAnr($sourceAnr) as $sourceSoa) {
                $measure = $createdMeasuresUuidsToObjects[$sourceSoa->getMeasure()->getUuid()] ?? null;
                if ($measure === null) {
                    continue;
                }
                $newSoa = (new Entity\Soa())
                    ->setAnr($newAnr)
                    ->setMeasure($measure);
                if ($sourceSoa->getSoaScaleComment() !== null) {
                    $newSoa->setSoaScaleComment(
                        $anrSoaScaleCommentOldIdsToNewObjects[$sourceSoa->getSoaScaleComment()->getId()]
                    );
                }
                $this->soaTable->save($newSoa, false);
            }
        }
    }

    private function duplicateObjectsAndCategories(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        array $assetsOldIdsToNewObjects,
        array $rolfTagsOldIdsToNewObjects,
        bool $isSourceCommon
    ): array {
        /** @var CoreEntity\ObjectSuperClass[] $sourceObjects */
        $sourceObjects = $isSourceCommon ? $sourceAnr->getObjects() : $this->monarcObjectTable->findByAnr($sourceAnr);
        $objectCategoryOldIdsToNewObjects = [];
        $monarcObjectsUuidsToNewObjects = [];
        foreach ($sourceObjects as $sourceObject) {
            $newObjectCategory = null;
            if ($sourceObject->getCategory() !== null) {
                if (!isset($objectCategoryOldIdsToNewObjects[$sourceObject->getCategory()->getId()])) {
                    $objectCategoryOldIdsToNewObjects = $this->duplicateObjectCategoryAndItsParents(
                        $newAnr,
                        $sourceObject->getCategory(),
                        $objectCategoryOldIdsToNewObjects
                    );
                }
                $newObjectCategory = $objectCategoryOldIdsToNewObjects[$sourceObject->getCategory()->getId()];
            }
            $rolfTag = null;
            if ($sourceObject->getRolfTag() !== null) {
                $rolfTag = $rolfTagsOldIdsToNewObjects[$sourceObject->getRolfTag()->getId()] ?? null;
            }

            $sourceUuid = $sourceObject->getUuid();
            $monarcObjectsUuidsToNewObjects[$sourceUuid] = $this->anrObjectService->createMonarcObject(
                $newAnr,
                $assetsOldIdsToNewObjects[$sourceObject->getAsset()->getUuid()],
                $newObjectCategory,
                $rolfTag,
                array_merge([
                    'uuid' => $sourceUuid,
                    'scope' => $sourceObject->getScope(),
                    'setOnlyExactPosition' => true,
                ], $sourceObject->getLabels(), $sourceObject->getNames()),
                false
            );
        }

        /* Recreate the object's composition links. */
        foreach ($sourceObjects as $sourceObject) {
            $this->duplicateObjectsCompositions($newAnr, $sourceObject, $monarcObjectsUuidsToNewObjects);
        }

        return $monarcObjectsUuidsToNewObjects;
    }

    private function duplicateObjectsCompositions(
        Entity\Anr $newAnr,
        CoreEntity\ObjectSuperClass $sourceObject,
        array $monarcObjectsUuidsToNewObjects
    ): void {
        foreach ($sourceObject->getChildrenLinks() as $sourceChildLinkObject) {
            $newObjectObject = (new Entity\ObjectObject())
                ->setAnr($newAnr)
                ->setParent($monarcObjectsUuidsToNewObjects[$sourceObject->getUuid()])
                ->setChild($monarcObjectsUuidsToNewObjects[$sourceChildLinkObject->getChild()->getUuid()])
                ->setPosition($sourceChildLinkObject->getPosition())
                ->setCreator($this->connectedUser->getEmail());
            $this->objectObjectTable->save($newObjectObject, false);
        }
    }

    private function duplicateObjectCategoryAndItsParents(
        Entity\Anr $newAnr,
        CoreEntity\ObjectCategorySuperClass $sourceObjectCategory,
        array $objectCategoryOldIdsToNewObjects
    ): array {
        /* Recreate parents recursively. */
        if ($sourceObjectCategory->getParent() !== null
            && !isset($objectCategoryOldIdsToNewObjects[$sourceObjectCategory->getParent()->getId()])
        ) {
            $objectCategoryOldIdsToNewObjects += $this->duplicateObjectCategoryAndItsParents(
                $newAnr,
                $sourceObjectCategory->getParent(),
                $objectCategoryOldIdsToNewObjects
            );
        }

        $objectCategoryOldIdsToNewObjects[$sourceObjectCategory->getId()] = $this->anrObjectCategoryService->create(
            $newAnr,
            array_merge([
                'parent' => $sourceObjectCategory->getParent() !== null
                    ? $objectCategoryOldIdsToNewObjects[$sourceObjectCategory->getParent()->getId()]
                    : null,
                'setOnlyExactPosition' => true,
                'position' => $sourceObjectCategory->getPosition(),
            ], $sourceObjectCategory->getLabels()),
            false
        );

        return $objectCategoryOldIdsToNewObjects;
    }

    private function duplicateInstancesTreeRisksSequencesRecommendationsMetadataAndScales(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        array $amvsOldIdsToNewObjects,
        array $assetsOldIdsToNewObjects,
        array $threatsOldIdsToNewObjects,
        array $vulnerabilitiesOldIdsToNewObjects,
        array $monarcObjectsOldIdsToNewObjects,
        array $anrInstanceMetadataFieldOldIdsToNewObjects,
        array $rolfRisksOldIdsToNewObjects,
        bool $isSourceCommon
    ): void {
        /* Recreate Scales, ScalesComments and ScalesImpactTypes. */
        $scalesImpactTypesOldIdsToNewObjects = $this->duplicateScales($sourceAnr, $newAnr, $isSourceCommon);

        /* Recreate Operational risks Scales, ScalesComments and ScalesTypes. */
        $operationalScaleTypesOldIdsToNewObjects = $this->duplicateOperationalRiskScales(
            $sourceAnr,
            $newAnr,
            $isSourceCommon
        );

        if (!$isSourceCommon) {
            /** @var Entity\Anr $sourceAnr */
            $recommendationsUuidsToNewObjects = $this->duplicateRecommendationsAndSets($sourceAnr, $newAnr);
        }

        $instancesOldIdsToNewObjects = [];
        $instanceRisksOldIdsToNewObjects = [];
        $instanceRisksOpOldIdsToNewObjects = [];
        foreach ($sourceAnr->getInstances() as $sourceInstance) {
            /** @var Entity\Instance $newInstance */
            $newInstance = Entity\Instance::constructFromObject($sourceInstance)
                ->setAnr($newAnr)
                ->setAsset($assetsOldIdsToNewObjects[$sourceInstance->getAsset()->getUuid()])
                ->setObject($monarcObjectsOldIdsToNewObjects[$sourceInstance->getObject()->getUuid()])
                ->setCreator($this->connectedUser->getEmail());
            if (!$isSourceCommon) {
                $this->duplicateInstanceMetadata(
                    $sourceInstance,
                    $newInstance,
                    $anrInstanceMetadataFieldOldIdsToNewObjects
                );
            }

            $this->instanceTable->save($newInstance, false);
            $instancesOldIdsToNewObjects[$sourceInstance->getId()] = $newInstance;

            /* Recreate InstanceRisks. */
            $instanceRisksOldIdsToNewObjects += $this->duplicateInstanceRisks(
                $sourceInstance,
                $newInstance,
                $amvsOldIdsToNewObjects,
                $assetsOldIdsToNewObjects,
                $threatsOldIdsToNewObjects,
                $vulnerabilitiesOldIdsToNewObjects
            );

            /* Recreate OperationalInstanceRisks. */
            $instanceRisksOpOldIdsToNewObjects += $this->duplicateOperationalInstanceRisks(
                $sourceInstance,
                $newInstance,
                $rolfRisksOldIdsToNewObjects,
                $operationalScaleTypesOldIdsToNewObjects
            );

            /* Recreate InstanceConsequences. */
            $this->duplicateInstanceConsequences($sourceInstance, $newInstance, $scalesImpactTypesOldIdsToNewObjects);
        }
        /* Recreate the hierarchy. */
        foreach ($sourceAnr->getInstances() as $sourceInstance) {
            if ($sourceInstance->getRoot() || $sourceInstance->getParent()) {
                $newInstance = $instancesOldIdsToNewObjects[$sourceInstance->getId()];
                if ($sourceInstance->getRoot()) {
                    $newInstance->setRoot($instancesOldIdsToNewObjects[$sourceInstance->getRoot()->getId()]);
                }
                if ($sourceInstance->getParent()) {
                    $newInstance->setParent($instancesOldIdsToNewObjects[$sourceInstance->getParent()->getId()]);
                }
                $this->instanceTable->save($newInstance, false);
            }
        }

        if (!$isSourceCommon) {
            /* Recreate RecommendationRisks and RecommendationsHistory. */
            $this->duplicateRecommendationsRisks(
                $sourceAnr,
                $recommendationsUuidsToNewObjects,
                $instanceRisksOldIdsToNewObjects,
                $instanceRisksOpOldIdsToNewObjects
            );
            $this->duplicateRecommendationsHistory(
                $sourceAnr,
                $newAnr,
                $instanceRisksOldIdsToNewObjects,
                $instanceRisksOpOldIdsToNewObjects
            );
        }
    }

    private function duplicateRecommendationsAndSets(Entity\Anr $sourceAnr, Entity\Anr $newAnr): array
    {
        $recommendationsUuidsToNewObjects = [];
        foreach ($sourceAnr->getRecommendationSets() as $sourceRecommendationSet) {
            $newRecommendationSet = $this->anrRecommendationSetService->create($newAnr, [
                'uuid' => $sourceRecommendationSet->getUuid(),
                'label' => $sourceRecommendationSet->getLabel(),
            ], false);
            foreach ($sourceRecommendationSet->getRecommendations() as $sourceRecommendation) {
                $recommendationUuid = $sourceRecommendation->getUuid();
                $recommendationsUuidsToNewObjects[$recommendationUuid] = $this->anrRecommendationService->create(
                    $newAnr,
                    [
                        'uuid' => $recommendationUuid,
                        'recommendationSet' => $newRecommendationSet,
                        'code' => $sourceRecommendation->getCode(),
                        'description' => $sourceRecommendation->getDescription(),
                        'importance' => $sourceRecommendation->getImportance(),
                        'position' => $sourceRecommendation->getPosition(),
                        'comment' => $sourceRecommendation->getComment(),
                        'status' => $sourceRecommendation->getStatus(),
                        'responsible' => $sourceRecommendation->getResponsible(),
                        'duedate' => $sourceRecommendation->getDueDate(),
                        'counterTreated' => $sourceRecommendation->getCounterTreated(),
                    ],
                    false
                );
            }
        }

        return $recommendationsUuidsToNewObjects;
    }

    private function duplicateRecommendationsHistory(
        Entity\Anr $sourceAnr,
        Entity\Anr $newAnr,
        array $instanceRisksOldIdsToNewObjects,
        array $instanceRisksOpOldIdsToNewObjects,
    ) {
        /** @var Entity\RecommendationHistory $sourceRecommendationHistory */
        foreach ($this->recommendationHistoryTable->findByAnr($sourceAnr) as $sourceRecommendationHistory) {
            $newRecommendationHistory = Entity\RecommendationHistory::constructFromObject($sourceRecommendationHistory)
                ->setAnr($newAnr);
            if ($sourceRecommendationHistory->getInstanceRisk() !== null
                && isset($instanceRisksOldIdsToNewObjects[$sourceRecommendationHistory->getInstanceRisk()->getId()])
            ) {
                $newRecommendationHistory->setInstanceRisk(
                    $instanceRisksOldIdsToNewObjects[$sourceRecommendationHistory->getInstanceRisk()->getId()]
                );
            } elseif ($sourceRecommendationHistory->getInstanceRiskOp() !== null
                && isset($instanceRisksOpOldIdsToNewObjects[$sourceRecommendationHistory->getInstanceRiskOp()->getId()])
            ) {
                $newRecommendationHistory->setInstanceRiskOp(
                    $instanceRisksOpOldIdsToNewObjects[$sourceRecommendationHistory->getInstanceRiskOp()->getId()]
                );
            }
            $this->recommendationHistoryTable->save($newRecommendationHistory, false);
        }
    }

    private function duplicateRecommendationsRisks(
        Entity\Anr $sourceAnr,
        array $recommendationsUuidsToNewObjects,
        array $instanceRisksOldIdsToNewObjects,
        array $instanceRisksOpOldIdsToNewObjects,
    ): void {
        /* Recreate recommendations <-> risks relations. */
        /** @var Entity\RecommendationRisk $sourceRecommendationRisk */
        foreach ($this->recommendationRiskTable->findByAnr($sourceAnr) as $sourceRecommendationRisk) {
            /** @var Entity\Recommendation $newRecommendation */
            $newRecommendation = $recommendationsUuidsToNewObjects[
                $sourceRecommendationRisk->getRecommendation()->getUuid()
            ];
            /** @var Entity\InstanceRisk|Entity\InstanceRiskOp $newInstanceRisk */
            $newInstanceRisk = $sourceRecommendationRisk->getInstanceRisk() !== null
                ? $instanceRisksOldIdsToNewObjects[$sourceRecommendationRisk->getInstanceRisk()->getId()]
                : $instanceRisksOpOldIdsToNewObjects[$sourceRecommendationRisk->getInstanceRiskOp()->getId()];

            $this->anrRecommendationRiskService->createRecommendationRisk(
                $newRecommendation,
                $newInstanceRisk,
                $sourceRecommendationRisk->getCommentAfter(),
                false
            );
        }
    }

    private function duplicateInstanceRisks(
        CoreEntity\InstanceSuperClass $sourceInstance,
        Entity\Instance $newInstance,
        array $amvsOldIdsToNewObjects,
        array $assetsOldIdsToNewObjects,
        array $threatsOldIdsToNewObjects,
        array $vulnerabilitiesOldIdsToNewObjects
    ): array {
        /** @var Entity\Anr $newAnr */
        $newAnr = $newInstance->getAnr();
        $instancesRisksOldIdsToNewObjects = [];
        foreach ($sourceInstance->getInstanceRisks() as $sourceInstanceRisk) {
            /** @var Entity\InstanceRisk $newInstanceRisk */
            $newInstanceRisk = Entity\InstanceRisk::constructFromObject($sourceInstanceRisk)
                ->setAnr($newAnr)
                ->setInstance($newInstance)
                ->setCreator($this->connectedUser->getEmail());
            if ($sourceInstanceRisk->getAmv() !== null) {
                $newInstanceRisk->setAmv($amvsOldIdsToNewObjects[$sourceInstanceRisk->getAmv()->getUuid()]);
            }
            if ($sourceInstanceRisk->getAsset() !== null) {
                $newInstanceRisk->setAsset($assetsOldIdsToNewObjects[$sourceInstanceRisk->getAsset()->getUuid()]);
            }
            if ($sourceInstanceRisk->getThreat() !== null) {
                $newInstanceRisk->setThreat($threatsOldIdsToNewObjects[$sourceInstanceRisk->getThreat()->getUuid()]);
            }
            if ($sourceInstanceRisk->getVulnerability() !== null) {
                $newInstanceRisk->setVulnerability(
                    $vulnerabilitiesOldIdsToNewObjects[$sourceInstanceRisk->getVulnerability()->getUuid()]
                );
            }
            if ($sourceInstanceRisk instanceof Entity\InstanceRisk
                && $sourceInstanceRisk->getInstanceRiskOwner() !== null
            ) {
                $newInstanceRisk->setInstanceRiskOwner($this->instanceRiskOwnerService->getOrCreateInstanceRiskOwner(
                    $newAnr,
                    $sourceInstanceRisk->getInstanceRiskOwner()->getName(),
                    false
                ));
            }

            $this->instanceRiskTable->save($newInstanceRisk, false);
            $instancesRisksOldIdsToNewObjects[$sourceInstanceRisk->getId()] = $newInstanceRisk;
        }

        return $instancesRisksOldIdsToNewObjects;
    }

    private function duplicateOperationalInstanceRisks(
        CoreEntity\InstanceSuperClass $sourceInstance,
        Entity\Instance $newInstance,
        array $rolfRisksOldIdsToNewObjects,
        array $operationalScaleTypesOldIdsToNewObjects
    ): array {
        /** @var Entity\Anr $newAnr */
        $newAnr = $newInstance->getAnr();
        $instancesRisksOpOldIdsToNewObjects = [];
        foreach ($sourceInstance->getOperationalInstanceRisks() as $sourceInstanceRiskOp) {
            /** @var Entity\InstanceRiskOp $newInstanceRiskOp */
            $newInstanceRiskOp = Entity\InstanceRiskOp::constructFromObject($sourceInstanceRiskOp)
                ->setAnr($newAnr)
                ->setInstance($newInstance)
                ->setObject($newInstance->getObject())
                ->setCreator($this->connectedUser->getEmail());
            if ($sourceInstanceRiskOp->getRolfRisk() !== null) {
                $newInstanceRiskOp->setRolfRisk(
                    $rolfRisksOldIdsToNewObjects[$sourceInstanceRiskOp->getRolfRisk()->getId()]
                );
            }
            if ($sourceInstanceRiskOp instanceof Entity\InstanceRiskOp
                && $sourceInstanceRiskOp->getInstanceRiskOwner() !== null
            ) {
                $instanceRiskOwner = $this->instanceRiskOwnerService->getOrCreateInstanceRiskOwner(
                    $newAnr,
                    $sourceInstanceRiskOp->getInstanceRiskOwner()->getName(),
                    false
                );
                $newInstanceRiskOp->setInstanceRiskOwner($instanceRiskOwner);
            }

            $this->instanceRiskOpTable->save($newInstanceRiskOp, false);

            $this->duplicateOperationalInstanceRiskScales(
                $sourceInstanceRiskOp,
                $newInstanceRiskOp,
                $newAnr,
                $operationalScaleTypesOldIdsToNewObjects
            );

            $instancesRisksOpOldIdsToNewObjects[$sourceInstanceRiskOp->getId()] = $newInstanceRiskOp;
        }

        return $instancesRisksOpOldIdsToNewObjects;
    }

    private function duplicateOperationalInstanceRiskScales(
        CoreEntity\InstanceRiskOpSuperClass $sourceInstanceRiskOp,
        Entity\InstanceRiskOp $newInstanceRiskOp,
        Entity\Anr $newAnr,
        array $operationalScaleTypesOldIdsToNewObjectsMap
    ): void {
        foreach ($sourceInstanceRiskOp->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
            $operationalRiskScaleType = $operationalScaleTypesOldIdsToNewObjectsMap[
                $operationalInstanceRiskScale->getOperationalRiskScaleType()->getId()
            ];

            $operationalInstanceRiskScale = (new Entity\OperationalInstanceRiskScale())
                ->setAnr($newAnr)
                ->setOperationalInstanceRisk($newInstanceRiskOp)
                ->setOperationalRiskScaleType($operationalRiskScaleType)
                ->setBrutValue($operationalInstanceRiskScale->getBrutValue())
                ->setNetValue($operationalInstanceRiskScale->getNetValue())
                ->setTargetedValue($operationalInstanceRiskScale->getTargetedValue())
                ->setCreator($this->connectedUser->getEmail());
            $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
        }
    }

    private function duplicateInstanceConsequences(
        CoreEntity\InstanceSuperClass $sourceInstance,
        Entity\Instance $newInstance,
        array $scalesImpactTypesOldIdsToNewObjects
    ): void {
        foreach ($sourceInstance->getInstanceConsequences() as $sourceInstanceConsequence) {
            $newInstanceConsequence = Entity\InstanceConsequence::constructFromObject($sourceInstanceConsequence)
                ->setAnr($newInstance->getAnr())
                ->setInstance($newInstance)
                ->setScaleImpactType(
                    $scalesImpactTypesOldIdsToNewObjects[$sourceInstanceConsequence->getScaleImpactType()->getId()]
                )
                ->setCreator($this->connectedUser->getEmail());
                $this->instanceConsequenceTable->save($newInstanceConsequence, false);
        }
    }

    private function duplicateQuestions(
        CoreEntity\AnrSuperClass $sourceAnr,
        Entity\Anr $newAnr,
        bool $isSourceCommon
    ): void {
        /** @var CoreEntity\QuestionSuperClass[] $questions */
        $questions = $isSourceCommon
            ? $this->coreQuestionTable->fetchAllObject()
            : $this->questionTable->findByAnr($sourceAnr);
        $questionsNewIds = [];
        foreach ($questions as $q) {
            $newQuestion = new Entity\Question($q);
            $newQuestion->setId(null);
            $newQuestion->setAnr($newAnr);
            $this->questionTable->save($newQuestion, false);
            $questionsNewIds[$q->getId()] = $newQuestion;
        }
        /** @var CoreEntity\QuestionChoiceSuperClass[] $questionChoices */
        $questionChoices = $isSourceCommon
            ? $this->coreQuestionChoiceTable->fetchAllObject()
            : $this->questionChoiceTable->getEntityByFields(['anr' => $sourceAnr->getId()]);
        foreach ($questionChoices as $qc) {
            $newQuestionChoice = new Entity\QuestionChoice($qc);
            $newQuestionChoice->setId(null);
            $newQuestionChoice->setAnr($newAnr);
            $newQuestionChoice->setQuestion($questionsNewIds[$qc->getQuestion()->getId()]);
            $this->questionChoiceTable->save($newQuestionChoice, false);
        }
    }

    private function duplicateInterviews(Entity\Anr $sourceAnr, Entity\Anr $newAnr): void
    {
        $interviews = $this->interviewTable->getEntityByFields(['anr' => $sourceAnr->getId()]);
        foreach ($interviews as $interview) {
            $newInterview = new Entity\Interview($interview);
            $newInterview->set('id', null);
            $newInterview->setAnr($newAnr);
            $this->interviewTable->save($newInterview, false);
        }
    }

    private function duplicateRopa(Entity\Anr $sourceAnr, Entity\Anr $newAnr): void
    {
        /* Recreate record actors. */
        /** @var Entity\RecordActor[] $recordActors */
        $recordActors = $this->recordActorTable->getEntityByFields(['anr' => $sourceAnr->getId()]);
        $actorNewIds = [];
        foreach ($recordActors as $a) {
            $newActor = new Entity\RecordActor($a);
            $newActor->set('id', null);
            $newActor->setAnr($newAnr);
            $this->recordActorTable->save($newActor, false);
            $actorNewIds[$a->getId()] = $newActor;
        }

        /* Recreate record data categories. */
        /** @var Entity\RecordDataCategory[] $recordDataCategories */
        $recordDataCategories = $this->recordDataCategoryTable->getEntityByFields(['anr' => $sourceAnr->getId()]);
        $dataCategoryNewIds = [];
        foreach ($recordDataCategories as $dc) {
            $newDataCategory = new Entity\RecordDataCategory($dc);
            $newDataCategory->set('id', null);
            $newDataCategory->setAnr($newAnr);
            $this->recordDataCategoryTable->save($newDataCategory, false);
            $dataCategoryNewIds[$dc->getId()] = $newDataCategory;
        }

        /* Recreate record processors. */
        /** @var Entity\RecordProcessor[] $recordProcessors */
        $recordProcessors = $this->recordProcessorTable->getEntityByFields(['anr' => $sourceAnr->getId()]);
        $processorNewIds = [];
        foreach ($recordProcessors as $p) {
            $newProcessor = new Entity\RecordProcessor($p);
            $newProcessor->set('id', null);
            $newProcessor->setAnr($newAnr);
            if ($p->getRepresentative() !== null) {
                $newProcessor->setRepresentative($actorNewIds[$p->getRepresentative()->getId()]);
            }
            if ($p->getDpo() !== null) {
                $newProcessor->setDpo($actorNewIds[$p->getDpo()->getId()]);
            }
            $this->recordProcessorTable->save($newProcessor, false);
            $processorNewIds[$p->getId()] = $newProcessor;
        }

        /* Recreate record recipients. */
        /** @var Entity\RecordRecipient[] $recordRecipients */
        $recordRecipients = $this->recordRecipientTable->getEntityByFields(['anr' => $sourceAnr->getId()]);
        $recipientNewIds = [];
        foreach ($recordRecipients as $r) {
            $newRecipient = new Entity\RecordRecipient($r);
            $newRecipient->set('id', null);
            $newRecipient->setAnr($newAnr);
            $this->recordRecipientTable->save($newRecipient, false);
            $recipientNewIds[$r->getId()] = $newRecipient;
        }

        /* Recreate record. */
        /** @var Entity\Record[] $records */
        $records = $this->recordTable->getEntityByFields(['anr' => $sourceAnr->getId()]);
        $recordNewIds = [];
        foreach ($records as $record) {
            $newRecord = new Entity\Record($record);
            $newRecord->set('id', null);
            $newRecord->setAnr($newAnr);
            if ($record->getController() !== null) {
                $newRecord->setController($actorNewIds[$record->getController()->getId()]);
            }
            if ($record->getRepresentative() !== null) {
                $newRecord->setRepresentative($actorNewIds[$record->getRepresentative()->getId()]);
            }
            if ($record->getDpo() !== null) {
                $newRecord->setDpo($actorNewIds[$record->getDpo()->getId()]);
            }

            $jointControllerNewIds = [];
            $jointControllers = $record->getJointControllers();
            foreach ($jointControllers as $jc) {
                $jointControllerNewIds[] = $actorNewIds[$jc->getId()];
            }
            $newRecord->setJointControllers($jointControllerNewIds);

            $processorIds = [];
            $processors = $record->getProcessors();
            foreach ($processors as $p) {
                $processorIds[$p->getId()] = $processorNewIds[$p->getId()];
            }
            $newRecord->setProcessors($processorIds);

            $recipientIds = [];
            $recipients = $record->getRecipients();
            foreach ($recipients as $r) {
                $recipientIds[$r->getId()] = $recipientNewIds[$r->getId()];
            }
            $newRecord->setRecipients($recipientIds);

            $this->recordTable->save($newRecord, false);
            $recordNewIds[$record->getId()] = $newRecord;
        }

        //duplicate record personal data
        /** @var Entity\RecordPersonalData[] $recordPersonalData */
        $recordPersonalData = $this->recordPersonalDataTable->getEntityByFields(['anr' => $sourceAnr->getId()]);
        foreach ($recordPersonalData as $pd) {
            $newPersonalData = new Entity\RecordPersonalData($pd);
            $newPersonalData->set('id', null);
            $newPersonalData->setAnr($newAnr);
            $newPersonalData->setRecord($recordNewIds[$pd->getRecord()->getId()]);
            $newDataCategoryIds = [];
            $dataCategories = $pd->getDataCategories();
            foreach ($dataCategories as $dc) {
                $newDataCategoryIds[] = $dataCategoryNewIds[$dc->getId()];
            }
            $newPersonalData->setDataCategories($newDataCategoryIds);
            $this->recordPersonalDataTable->save($newPersonalData, false);
        }

        /* Recreate record international transfers. */
        /** @var Entity\RecordInternationalTransfer[] $recordInternationalTransfers */
        $recordInternationalTransfers = $this->recordInternationalTransferTable
            ->getEntityByFields(['anr' => $sourceAnr->getId()]);
        foreach ($recordInternationalTransfers as $it) {
            $newInternationalTransfer = new Entity\RecordInternationalTransfer($it);
            $newInternationalTransfer->set('id', null);
            $newInternationalTransfer->setAnr($newAnr);
            $newInternationalTransfer->setRecord($recordNewIds[$it->getRecord()->getId()]);
            $this->recordInternationalTransferTable->save($newInternationalTransfer, false);
        }
    }
}
