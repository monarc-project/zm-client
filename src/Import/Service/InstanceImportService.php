<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Service;

use DateTime;
use Doctrine\ORM;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity as CoreEntity;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Helper\EncryptDecryptHelperTrait;
use Monarc\Core\Service\Traits\RiskCalculationTrait;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Processor;
use Monarc\FrontOffice\Import\Traits\EvaluationConverterTrait;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Service;

class InstanceImportService
{
    use EncryptDecryptHelperTrait;
    use EvaluationConverterTrait;
    use RiskCalculationTrait;

    private string $monarcVersion;

    private int $currentAnalyseMaxRecommendationPosition;

    private int $currentMaxInstancePosition;

    private CoreEntity\UserSuperClass $connectedUser;

    private string $importType;

    private array $cachedData = [];

    public function __construct(
        private Processor\AssetImportProcessor $assetImportProcessor,
        private Processor\ThreatImportProcessor $threatImportProcessor,
        private Processor\VulnerabilityImportProcessor $vulnerabilityImportProcessor,
        private Processor\ReferentialImportProcessor $referentialImportProcessor,
        private Processor\InformationRiskImportProcessor $informationRiskImportProcessor,
        private Processor\RolfTagImportProcessor $rolfTagImportProcessor,
        private Processor\OperationalRisksImportProcessor $operationalRisksImportProcessor,
        private Processor\RecommendationImportProcessor $recommendationImportProcessor,
        private Processor\ObjectCategoryImportProcessor $objectCategoryImportProcessor,
        private Processor\AnrInstanceMetadataFieldImportProcessor $anrInstanceMetadataFieldImportProcessor,
        private Processor\AnrMethodStepImportProcessor $anrMethodStepImportProcessor,
        private Processor\InstanceImportProcessor $instanceImportProcessor,
        private Processor\ScaleImportProcessor $scaleImportProcessor,
        private Processor\OperationalRiskScaleImportProcessor $operationalRiskScaleImportProcessor,
        private Processor\OperationalInstanceRiskImportProcessor $operationalInstanceRiskImportProcessor,
        private Processor\SoaScaleCommentImportProcessor $soaScaleCommentImportProcessor,
        private Service\AnrInstanceRiskService $anrInstanceRiskService,
        private Service\InstanceRiskOwnerService $instanceRiskOwnerService,
        private Service\AnrInstanceService $anrInstanceService,
        private Service\AnrInstanceConsequenceService $anrInstanceConsequenceService,
        private ObjectImportService $objectImportService,
        private Service\AnrRecordService $anrRecordService,
        private Service\AnrInstanceRiskOpService $anrInstanceRiskOpService,
        private Table\InstanceTable $instanceTable,
        private Table\AnrTable $anrTable,
        private Table\InstanceConsequenceTable $instanceConsequenceTable,
        private Table\ScaleTable $scaleTable,
        private Table\ThreatTable $threatTable,
        private Table\VulnerabilityTable $vulnerabilityTable,
        private Table\RecommendationTable $recommendationTable,
        private Table\RecommendationSetTable $recommendationSetTable,
        private Service\AnrRecommendationSetService $anrRecommendationSetService,
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private Table\RecommendationRiskTable $recommendationRiskTable,
        private DeprecatedTable\QuestionTable $questionTable,
        private DeprecatedTable\QuestionChoiceTable $questionChoiceTable,
        private Table\SoaTable $soaTable,
        private Table\MeasureTable $measureTable,
        private Table\ThemeTable $themeTable,
        private Table\ReferentialTable $referentialTable,
        private DeprecatedTable\InterviewTable $interviewTable,
        private Table\DeliveryTable $deliveryTable,
        private Table\ScaleImpactTypeTable $scaleImpactTypeTable,
        private Table\ScaleCommentTable $scaleCommentTable,
        private Table\OperationalRiskScaleTable $operationalRiskScaleTable,
        private Table\OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        private Table\OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable,
        private Table\OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        private Table\AnrInstanceMetadataFieldTable $anrInstanceMetadataFieldTable,
        private Table\InstanceMetadataTable $instanceMetadataTable,
        private Table\SoaScaleCommentTable $soaScaleCommentTable,
        private ConfigService $configService,
        private ImportCacheHelper $importCacheHelper,
        private Service\AnrThemeService $anrThemeService,
        private Service\SoaCategoryService $soaCategoryService,
        private Service\AnrRecommendationRiskService $anrRecommendationRiskService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /**
     * Available import modes: 'merge', which will update the existing instances using the file's data,
     * or 'duplicate' which will create a new instance using the data.
     *
     * @return array An array where the first key is the generated IDs, and the second are import errors
     */
    public function importFromFile(Entity\Anr $anr, array $importParams): array
    {
        // Mode may either be 'merge' or 'duplicate'
        $mode = empty($importParams['mode']) ? 'merge' : $importParams['mode'];

        /*
         * The object may be imported at the root, or under an existing instance in the ANR instances tree
         */
        $parentInstance = null;
        if (!empty($importParams['idparent'])) {
            /** @var Entity\Instance $parentInstance */
            $parentInstance = $this->instanceTable->findById((int)$importParams['idparent']);
        }

        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($importParams['file'])) {
            throw new Exception('File missing', 412);
        }

        // TODO: remove this!!!
//        ini_set('max_execution_time', '0');
//        ini_set('memory_limit', '-1');

        $createdInstancesIds = [];
        $importErrors = [];
        foreach ($importParams['file'] as $key => $file) {
            // Ensure the file has been uploaded properly, silently skip the files that are erroneous
            if (isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && file_exists($file['tmp_name'])) {
                if (empty($importParams['password'])) {
                    $data = json_decode(trim(file_get_contents($file['tmp_name'])), true, 512, JSON_THROW_ON_ERROR);
                } else {
                    $decryptedResult = $this->decrypt(file_get_contents($file['tmp_name']), $importParams['password']);
                    if ($decryptedResult === false) {
                        throw new Exception('Password is not correct.', 412);
                    }
                    $data = json_decode(trim($decryptedResult), true, 512, JSON_THROW_ON_ERROR);
                    unset($decryptedResult);
                }

                if ($data !== false) {
                    $createdInstancesIds = array_merge(
                        $createdInstancesIds,
                        $this->importFromArray($data, $anr, $parentInstance, $mode)
                    );
                } else {
                    $importErrors[] = 'The file "' . $file['name'] . '" can\'t be imported';
                }
            }

            // Free up the memory in case of big files.
            unset($importParams['file'][$key]);
        }

        return [$createdInstancesIds, $importErrors];
    }

    /**
     * Imports instances ot the whole analysis from an exported data (json) array.
     *
     * @param array $data The instance data
     * @param Entity\Anr $anr The target ANR
     * @param null|Entity\Instance $parentInstance The parent instance, that should be imported, null if it's root.
     * @param string $importMode Import mode, either 'merge' or 'duplicate'
     *
     * @return array An array of created instances IDs, or false in case of error
     */
    private function importFromArray(
        array $data,
        Entity\Anr $anr,
        ?Entity\Instance $parentInstance,
        string $importMode
    ): array {
        $this->setAndValidateMonarcVersion($data);
        $this->validateIfImportIsPossible($anr, $parentInstance, $data);

        $result = [];
        if (!$this->isMonarcVersionLowerThen('2.13.1')) {
            /* New structure of the import data. */
            if ($data['type'] === 'anr') {
                $result = $this->processAnrImport($anr, $data, $parentInstance, $importMode);
            } elseif ($data['type'] === 'instance') {
                $result = $this->processInstanceImport($anr, $data, $parentInstance, $importMode);
            }

            // TODO: remove the temporary test case
            $this->anrTable->flush();

            return $result;
        }

        $this->currentMaxInstancePosition = $this->instanceTable->findMaxPosition(
            ['anr' => $anr, 'parent' => $parentInstance]
        );

        if ($data['type'] === 'instance') {
            $this->importType = 'instance';
            $result = $this->importInstanceFromArray($data, $anr, $parentInstance, $importMode);
        } elseif ($data['type'] === 'anr') {
            $this->importType = 'anr';
            $result = $this->importAnrFromArray($data, $anr, $parentInstance, $importMode);
        }

        return $result;
    }

    /* START new import structure process. */

    private function processAnrImport(
        Entity\Anr $anr,
        array $data,
        ?Entity\Instance $parentInstance,
        string $importMode
    ): array {
        $result = [];
        if ($data['withMethodSteps']) {
            /* Process all the method's steps. */
            $this->anrMethodStepImportProcessor->processAnrMethodStepsData($anr, $data['method']);
        }
        if ($data['withEval']) {
            /* Process all the analysis' thresholds. */
            $this->anrMethodStepImportProcessor->processThresholdsData($anr, $data['thresholds']);

            /* Apply the importing information risks scales. */
            $this->scaleImportProcessor->applyNewScalesFromData($anr, $data['scales']);

            /* Apply the importing operational risks scales. */
            $this->operationalRiskScaleImportProcessor->adjustOperationalRisksScaleValuesBasedOnNewScales($anr, $data);

        }
        if ($data['withInterviews']) {
            /* Process the interviews' data. */
            $this->anrMethodStepImportProcessor->processInterviewsData($anr, $data['interviews']);
        }
        if ($data['withKnowledgeBase']) {
            /* Process the Knowledge Base data. */
            $this->processKnowledgeBaseData($anr, $data['knowledgeBase']);
        }
        if ($data['withLibrary']) {
            /* Process the Assets Library data. */
            $this->processLibraryData($anr, $data['library'], $importMode);
        }
        /* Process the analysis metadata fields data. */
        $this->anrInstanceMetadataFieldImportProcessor->processAnrInstanceMetadataFields(
            $anr,
            $data['anrInstanceMetadataFields']
        );

        /* Process the Instances, Instance Risks, Consequences and evaluations data. */
        $this->instanceImportProcessor
            ->processInstancesData($anr, $data['instances'], $parentInstance, $importMode, $data['withEval']);

        // TODO: check if 'scales' and 'operationalRiskScales' have to be processed before or after the instances.
        // perhaps they have to be cached first to be able to compare or the old ones preloaded.
        // TODO: If we update the scales later, then the post update or right during the process have to be done.

        // TODO: soaScaleComments & SOAs

        // TODO: process RoPA.

        return $result;
    }

    private function processKnowledgeBaseData(Entity\Anr $anr, array $knowledgeBaseData): void
    {
        $this->assetImportProcessor->processAssetsData($anr, $knowledgeBaseData['assets']);
        $this->threatImportProcessor->processThreatsData($anr, $knowledgeBaseData['threats']);
        $this->vulnerabilityImportProcessor->processVulnerabilitiesData($anr, $knowledgeBaseData['vulnerabilities']);
        $this->referentialImportProcessor->processReferentialsData($anr, $knowledgeBaseData['referentials']);
        $this->informationRiskImportProcessor->processInformationRisksData(
            $anr,
            $knowledgeBaseData['informationRisks']
        );
        $this->rolfTagImportProcessor->processRolfTagsData($anr, $knowledgeBaseData['rolfTags']);
        $this->operationalRisksImportProcessor->processOperationalRisksData(
            $anr,
            $knowledgeBaseData['operationalRisks']
        );
        $this->recommendationImportProcessor->processRecommendationSetsData(
            $anr,
            $knowledgeBaseData['recommendationSets']
        );
    }

    private function processLibraryData(Entity\Anr $anr, array $libraryData, string $importMode): void
    {
        $this->objectCategoryImportProcessor->processObjectCategoriesData(
            $anr,
            $libraryData['categories'],
            $importMode
        );
    }

    private function processInstanceImport(
        Entity\Anr $anr,
        array $data,
        ?Entity\Instance $parentInstance = null,
        string $importMode = 'merge'
    ): array {
        $result = [];

        return $result;
    }

    /* END new import structure process. */

    private function isImportTypeAnr(): bool
    {
        return $this->importType === 'anr';
    }

    /**
     * @param array $data
     * @param Entity\Anr $anr
     * @param CoreEntity\InstanceSuperClass|null $parentInstance
     * @param string $importMode
     *
     * @return bool|int
     */
    private function importInstanceFromArray(
        array $data,
        Entity\Anr $anr,
        ?CoreEntity\InstanceSuperClass $parentInstance,
        string $importMode
    ) {
        $monarcObject = $this->objectImportService->importFromArray($data['object'], $anr, $importMode);
        if ($monarcObject === null) {
            return false;
        }

        $instance = $this->createInstance($data, $anr, $parentInstance, $monarcObject);

        // TODO: The instance risks are processed later again and considered that here we save or not...
        // 1. why do we need to do it twice processInstanceRisks and what is going on inside that method...
        $this->anrInstanceRiskService->createInstanceRisks($instance, $monarcObject, $data, false);

        $this->instanceImportProcessor->processInstanceMetadata($anr, $instance, $data['instancesMetadatas']);

        $includeEval = !empty($data['with_eval']);

        $this->prepareInstanceConsequences($data, $anr, $instance, $monarcObject, $includeEval);

        $this->updateInstanceImpactsFromBrothers($instance, $importMode);

        $this->instanceTable->save($instance->refreshInheritedImpact(), false);

        $this->createSetOfRecommendations($data, $anr);

        $this->processInstanceRisks($data, $anr, $instance, $monarcObject, $includeEval, $importMode);

        $this->operationalInstanceRiskImportProcessor->processOperationalInstanceRisks(
            $data,
            $anr,
            $instance,
            $monarcObject,
            $includeEval,
            $this->isImportTypeAnr()
        );

        if (!empty($data['children'])) {
            usort($data['children'], function ($a, $b) {
                return $a['instance']['position'] <=> $b['instance']['position'];
            });
            foreach ($data['children'] as $child) {
                if ($data['with_eval'] && isset($data['scales'])) {
                    $child['with_eval'] = $data['with_eval'];
                    $child['scales'] = $data['scales'];
                    if (isset($data['operationalRiskScales'])) {
                        $child['operationalRiskScales'] = $data['operationalRiskScales'];
                    }
                }
                $this->importInstanceFromArray($child, $anr, $instance, $importMode);
            }
            $this->anrInstanceService->updateChildrenImpactsAndRisks($instance);
        }

        $this->instanceTable->flush();

        return $instance->getId();
    }

    private function importAnrFromArray(
        array $data,
        Entity\Anr $anr,
        ?CoreEntity\InstanceSuperClass $parentInstance,
        string $modeImport
    ): array {
        if (!empty($data['method'])) {
            $this->anrMethodStepImportProcessor->processAnrMethodStepsData($anr, $data['method']);
        }

        if (!empty($data['referentials'])) {
            $this->referentialImportProcessor->processReferentialsData($anr, $data['referentials']);
        }

        /*
         * Import measures and soa categories.
         */
        foreach ($data['measures'] ?? [] as $measureData) {
            $referential = $this->referentialImportProcessor->getReferentialFromCache($measureData['referential']);
            if ($referential !== null) {
                $measure = $this->referentialImportProcessor->processMeasureData($anr, $referential, $measureData);
                if (!isset($data['soas'])) {
                    $this->soaTable->save((new Entity\Soa())->setAnr($anr)->setMeasure($measure), false);
                }
            }
        }

        foreach ($data['measuresMeasures'] ?? [] as $measureMeasureData) {
            $measure = $this->referentialImportProcessor->getMeasureFromCache($measureMeasureData['father']);
            if ($measure !== null) {
                $this->referentialImportProcessor->processLinkedMeasures(
                    $measure,
                    ['linkedMeasures' => [['uuid' => $measureMeasureData['child']]]]
                );
            }
        }

        /* Import SOA Scale Comments if they are passed. Only in the new structure, when the functionality exists. */
        if (isset($data['soaScaleComment'])) {
            $currentSoaScaleCommentData = $this->soaScaleCommentImportProcessor
                ->prepareCacheAndGetCurrentSoaScaleCommentsData($anr);
            $maxSoaScaleCommentDestination = \count($currentSoaScaleCommentData) - 1;
            $maxSoaScaleCommentOrigin = \count($data['soaScaleComment']) - 1;
            $this->soaScaleCommentImportProcessor->mergeSoaScaleComment($anr, $data['soaScaleComment']);
            if ($maxSoaScaleCommentDestination !== $maxSoaScaleCommentOrigin) {
                /** @var Entity\Soa $existedSoa */
                foreach ($this->soaTable->findByAnr($anr) as $existedSoa) {
                    $soaComment = $existedSoa->getSoaScaleComment();
                    if ($soaComment !== null) {
                        $newScaleIndex = $this->scaleImportProcessor->convertValueWithinNewScalesRange(
                            $soaComment->getScaleIndex(),
                            0,
                            $maxSoaScaleCommentDestination,
                            0,
                            $maxSoaScaleCommentOrigin,
                            0
                        );
                        $soaScaleComment = $this->importCacheHelper->getItemFromArrayCache(
                            'newSoaScaleCommentIndexedByScale',
                            $newScaleIndex
                        );
                        if ($soaScaleComment !== null) {
                            $existedSoa->setSoaScaleComment($soaScaleComment);
                        }
                        $this->soaTable->save($existedSoa, false);
                    }
                }
            }
        }

        // import the SOAs
        if (!empty($data['soas'])) {
            foreach ($data['soas'] as $soaData) {
                $measure = $this->importCacheHelper->getItemFromArrayCache('measures', $soaData['measure_id'])
                    ?: $this->measureTable->findByUuidAndAnr($soaData['measure_id'], $anr);
                if ($measure !== null) {
                    $soa = $this->soaTable->findByAnrAndMeasureUuid($anr, $soaData['measure_id']);
                    if ($soa === null) {
                        $soa = (new Entity\Soa($soaData))
                            ->setAnr($anr)
                            ->setMeasure($measure);
                    } else {
                        $soa->setRemarks($soaData['remarks'])
                            ->setEvidences($soaData['evidences'])
                            ->setActions($soaData['actions'])
                            ->setEX($soaData['EX'])
                            ->setLR($soaData['LR'])
                            ->setCO($soaData['CO'])
                            ->setBR($soaData['BR'])
                            ->setBP($soaData['BP'])
                            ->setRRA($soaData['RRA']);
                    }
                    if (isset($soaData['soaScaleComment'])) {
                        $soaScaleComment = $this->importCacheHelper->getItemFromArrayCache(
                            'soaScaleCommentExternalIdMapToNewObject',
                            $soaData['soaScaleComment']
                        );
                        if ($soaScaleComment !== null) {
                            $soa->setSoaScaleComment($soaScaleComment);
                        }
                    }
                    $this->soaTable->save($soa, false);
                }
            }
        }

        // import the GDPR records
        if (!empty($data['records'])) { //Data of records
            foreach ($data['records'] as $v) {
                $this->anrRecordService->importFromArray($v, $anr->getId());
            }
        }

        /*
         * Import AnrInstanceMetadataFields.
         */
        if (!empty($data['anrMetadatasOnInstances'])) {
            $this->anrInstanceMetadataFieldImportProcessor->processAnrInstanceMetadataFields(
                $anr,
                $data['anrMetadatasOnInstances']
            );
        }

        /*
         * Import scales.
         * TODO: use the processor!!!
         */
        if (!empty($data['scales'])) {
            /* Approximate values based on the scales from the destination/importing analysis. */

            $scalesData = $this->getCurrentAndExternalScalesData($anr, $data);

            // TODO: adjust the code. Instance and InstanceConsequence are refactored.
            $ts = ['c', 'i', 'd'];
            $instances = $this->instanceTable->findByAnr($anr);
            $consequences = $this->instanceConsequenceTable->findByAnr($anr);

            // Instances
            foreach ($ts as $t) {
                foreach ($instances as $instance) {
                    if ($instance->get($t . 'h')) {
                        $instance->set($t . 'h', 1);
                        $instance->set($t, -1);
                    } else {
                        $instance->set($t . 'h', 0);
                        $instance->set($t, $this->convertValueWithinNewScalesRange(
                            $instance->get($t),
                            $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['min'],
                            $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['max'],
                            $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['min'],
                            $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['max']
                        ));
                    }

                    $this->instanceTable->save($instance->refreshInheritedImpact(), false);
                }
                // Impacts & Consequences.
                foreach ($consequences as $conseq) {
                    $conseq->set($t, $conseq->isHidden() ? -1 : $this->convertValueWithinNewScalesRange(
                        $conseq->get($t),
                        $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['min'],
                        $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['max'],
                        $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['min'],
                        $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['max']
                    ));
                    $this->instanceConsequenceTable->save($conseq, false);
                }
            }

            /* Threats qualification. */
            foreach ($this->threatTable->findByAnr($anr) as $threat) {
                $threat->setQualification($this->convertValueWithinNewScalesRange(
                    $threat->getQualification(),
                    $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['min'],
                    $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['max'],
                    $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['min'],
                    $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['max'],
                ));
                $this->threatTable->save($threat, false);
            }

            /** @var Entity\InstanceRisk $instancesRisk */
            foreach ($this->instanceRiskTable->findByAnr($anr) as $instanceRisk) {
                $instanceRisk->setThreatRate($this->convertValueWithinNewScalesRange(
                    $instanceRisk->getThreatRate(),
                    $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['min'],
                    $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['max'],
                    $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['min'],
                    $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['max'],
                ));
                $oldVulRate = $instanceRisk->getVulnerabilityRate();
                $instanceRisk->setVulnerabilityRate($this->convertValueWithinNewScalesRange(
                    $instanceRisk->getVulnerabilityRate(),
                    $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_VULNERABILITY]['min'],
                    $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_VULNERABILITY]['max'],
                    $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_VULNERABILITY]['min'],
                    $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_VULNERABILITY]['max'],
                ));
                if ($instanceRisk->getReductionAmount() !== 0) {
                    $newVulRate = $instanceRisk->getVulnerabilityRate();
                    $instanceRisk->setReductionAmount($this->convertValueWithinNewScalesRange(
                        $instanceRisk->getReductionAmount(),
                        0,
                        $oldVulRate,
                        0,
                        $newVulRate,
                        0
                    ));
                }

                $this->recalculateRiskRates($instanceRisk);
                $this->instanceRiskTable->save($instanceRisk, false);
            }

            /* Adjust the values of operational risks scales. */
            $this->operationalRiskScaleImportProcessor->adjustOperationalRisksScaleValuesBasedOnNewScales($anr, $data);

            $this->scaleImportProcessor->updateScalesAndComments($anr, $data);

            $this->operationalRiskScaleImportProcessor->updateOperationalRisksScalesAndRelatedInstances($anr, $data);
        }

        $first = true;
        $instanceIds = [];
        usort($data['instances'], function ($a, $b) {
            return $a['instance']['position'] <=> $b['instance']['position'];
        });
        $this->assetImportProcessor->prepareAssetsAndCodesCache($anr);
        $this->threatImportProcessor->prepareThreatsAndCodesCache($anr);
        $this->threatImportProcessor->prepareThemesCache($anr);
        $this->vulnerabilityImportProcessor->prepareVulnerabilitiesAndCodesCache($anr);
        foreach ($data['instances'] as $inst) {
            if ($first) {
                if ($data['with_eval'] && isset($data['scales'])) {
                    $inst['with_eval'] = $data['with_eval'];
                    $inst['scales'] = $data['scales'];
                    if (isset($data['operationalRiskScales'])) {
                        $inst['operationalRiskScales'] = $data['operationalRiskScales'];
                    }
                }
                $first = false;
            }
            $instanceId = $this->importInstanceFromArray($inst, $anr, $parentInstance, $modeImport);
            if ($instanceId !== false) {
                $instanceIds[] = $instanceId;
            }
        }

        //Add user consequences to all instances
        $instances = $this->instanceTable->findByAnr($anr);
        $scaleImpactTypes = $this->scaleImpactTypeTable->findByAnr($anr);
        foreach ($instances as $instance) {
            foreach ($scaleImpactTypes as $siType) {
                $instanceConsequence = $this->instanceConsequenceTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'instance' => $instance->getId(),
                    'scaleImpactType' => $siType->getId(),
                ]);
                if (empty($instanceConsequence)) {
                    $consequence = (new Entity\InstanceConsequence())
                        ->setAnr($anr)
                        ->setInstance($instance)
                        ->setScaleImpactType($siType)
                        ->setCreator($this->connectedUser->getEmail());

                    $this->instanceConsequenceTable->save($consequence, false);
                }
            }
        }

        $this->instanceConsequenceTable->flush();

        return $instanceIds;
    }

    // TODO: use the processor.
    private function createSetOfRecommendations(array $data, Entity\Anr $anr): void
    {
        if (!empty($data['recSets'])) {
            foreach ($data['recSets'] as $recSetUuid => $recommendationSetData) {
                if (!isset($this->cachedData['recSets'][$recSetUuid])) {
                    $recommendationsSet = $this->recommendationSetTable->findByUuidAndAnr($recSetUuid, $anr, false);
                    if ($recommendationsSet === null) {
                        $this->anrRecommendationSetService->create($anr, [
                            'uuid' => $recSetUuid,
                            'label' => $recommendationSetData['label']
                                ?? $recommendationSetData['label' . $anr->getLanguage()],
                        ], false);
                    }

                    $this->cachedData['recSets'][$recSetUuid] = $recommendationsSet;
                }
            }
        } elseif ($this->isMonarcVersionLowerThen('2.8.4')) {
            /* Recommendation sets did not exist prior the version, so a custom one is created / used. */
            $label = Entity\RecommendationSet::getCustomImportLabelByLanguageCode($anr->getLanguageCode());
            $recommendationsSet = $this->recommendationSetTable->findByAnrAndLabel($anr, $label);
            if ($recommendationsSet === null) {
                $recommendationSet = $this->anrRecommendationSetService->create($anr, ['label' => $label], false);
            }

            $this->cachedData['recSets'][$recommendationSet->getUuid()] = $recommendationSet;
        }

        // Create recommendations not linked with recommendation risks.
        if (!empty($data['recs'])) {
            foreach ($data['recs'] as $recUuid => $recommendationData) {
                if (!isset($this->cachedData['recs'][$recUuid])) {
                    try {
                        $recommendation = $this->recommendationTable->findByUuidAndAnr($recUuid, $anr);
                    } catch (ORM\EntityNotFoundException) {
                        $recommendationSetUuid = $recommendationData['recommandationSet']
                            ?? $recommendationData['recommendationSet'];
                        $recommendation = (new Entity\Recommendation())
                            ->setUuid($recommendationData['uuid'])
                            ->setAnr($anr)
                            ->setRecommendationSet($this->cachedData['recSets'][$recommendationSetUuid])
                            ->setComment($recommendationData['comment'] ?? '')
                            ->setResponsible($recommendationData['responsable'] ?? '')
                            ->setStatus($recommendationData['status'])
                            ->setImportance($recommendationData['importance'])
                            ->setCode($recommendationData['code'])
                            ->setDescription($recommendationData['description'] ?? '')
                            ->setCounterTreated($recommendationData['counterTreated'])
                            ->setCreator($this->connectedUser->getEmail());

                        if (!empty($recommendationData['duedate']['date'])) {
                            $recommendation->setDueDate(new DateTime($recommendationData['duedate']['date']));
                        }

                        $this->recommendationTable->save($recommendation, false);
                    }

                    $this->cachedData['recs'][$recUuid] = $recommendation;
                }
            }
            $this->recommendationTable->flush();
        }
    }

    private function prepareInstanceConsequences(
        array $data,
        Entity\Anr $anr,
        Entity\Instance $instance,
        Entity\MonarcObject $monarcObject,
        bool $includeEval
    ): void {
        $labelKey = 'label' . $anr->getLanguage();
        if (!$includeEval) {
            $this->anrInstanceConsequenceService->createInstanceConsequences($instance, $anr, $monarcObject);

            return;
        }

        $scalesData = $this->getCurrentAndExternalScalesData($anr, $data);

        foreach (Entity\Instance::getAvailableScalesCriteria() as $scaleCriteria) {
            if ($instance->{'getInherited' . $scaleCriteria}()) {
                $instance->{'set' . $scaleCriteria}(-1);
            } elseif (!$this->isImportTypeAnr()) {
                $instance->{'set' . $scaleCriteria}($this->convertValueWithinNewScalesRange(
                    $instance->{'get' . $scaleCriteria}(),
                    $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['min'],
                    $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['max'],
                    $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['min'],
                    $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['max']
                ));
            }
        }

        if (!empty($data['consequences'])) {
            $localScaleImpact = $this->scaleTable->findByAnrAndType($anr, CoreEntity\ScaleSuperClass::TYPE_IMPACT);
            $scalesImpactTypes = $this->scaleImpactTypeTable->findByAnr($anr);
            $localScalesImpactTypes = [];
            foreach ($scalesImpactTypes as $scalesImpactType) {
                $localScalesImpactTypes[$scalesImpactType->getLabel($anr->getLanguage())] = $scalesImpactType;
            }

            foreach ($data['consequences'] as $consequenceData) {
                if (!isset($localScalesImpactTypes[$consequenceData['scaleImpactType'][$labelKey]])) {
                    $scaleImpactTypeData = $consequenceData['scaleImpactType'];

                    $scaleImpactType = (new Entity\ScaleImpactType())
                        ->setType($scaleImpactTypeData['type'])
                        ->setLabels($scaleImpactTypeData)
                        ->setIsSys((bool)$scaleImpactTypeData['isSys'])
                        ->setIsHidden((bool)$scaleImpactTypeData['isHidden'])
                        ->setAnr($anr)
                        ->setScale($localScaleImpact)
                        ->setCreator($this->connectedUser->getEmail());

                    $this->scaleImpactTypeTable->save($scaleImpactType, false);

                    $localScalesImpactTypes[$consequenceData['scaleImpactType'][$labelKey]] = $scaleImpactType;
                }

                $instanceConsequence = (new Entity\InstanceConsequence())
                    ->setAnr($anr)
                    ->setInstance($instance)
                    ->setScaleImpactType($localScalesImpactTypes[$consequenceData['scaleImpactType'][$labelKey]])
                    ->setIsHidden((bool)$consequenceData['isHidden'])
                    ->setCreator($this->connectedUser->getEmail());

                foreach (Entity\InstanceConsequence::getAvailableScalesCriteria() as $criteriaKey => $scaleCriteria) {
                    if ($instanceConsequence->isHidden()) {
                        $value = -1;
                    } else {
                        $value = $this->isImportTypeAnr()
                            ? $consequenceData[$criteriaKey]
                            : $this->convertValueWithinNewScalesRange(
                                $consequenceData[$criteriaKey],
                                $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['min'],
                                $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['max'],
                                $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['min'],
                                $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_IMPACT]['max']
                            );
                    }
                    $instanceConsequence->{'set' . $scaleCriteria}($value);
                }

                $this->instanceConsequenceTable->save($instanceConsequence, false);
            }

            $this->instanceConsequenceTable->flush();
        }
    }

    /**
     * The prepared scales cache data is used to convert(approximate) the risks' values of importing instance(s),
     * from external to current scales (in case of instance(s) import).
     * For ANR import, the current analysis risks' values are converted from current to external scales.
     */
    private function getCurrentAndExternalScalesData(Entity\Anr $anr, array $data): array
    {
        if (!$this->importCacheHelper->isCacheKeySet('scales_for_old_structure')) {
            $this->importCacheHelper->addItemToArrayCache('scales_for_old_structure', $data['scales'], 'external');
            $currentScalesData = [];
            /** @var Entity\Scale $scale */
            foreach ($this->scaleTable->findByAnr($anr) as $scale) {
                $currentScalesData[$scale->getType()] = [
                    'min' => $scale->getMin(),
                    'max' => $scale->getMax(),
                ];
            }
            $this->importCacheHelper->addItemToArrayCache('scales_for_old_structure', $currentScalesData, 'current');
        }

        return $this->importCacheHelper->getItemFromArrayCache('scales_for_old_structure');
    }

    private function processInstanceRisks(
        array $data,
        Entity\Anr $anr,
        Entity\Instance $instance,
        Entity\MonarcObject $monarcObject,
        bool $includeEval,
        string $modeImport
    ): void {
        if (empty($data['risks'])) {
            return;
        }

        $scalesData = [];
        if ($includeEval && !$this->isImportTypeAnr()) {
            $scalesData = $this->getCurrentAndExternalScalesData($anr, $data);
        }

        foreach ($data['risks'] as $instanceRiskData) {
            $threatData = $data['threats'][$instanceRiskData['threat']];
            $vulnerabilityData = $data['vuls'][$instanceRiskData['vulnerability']];

            // TODO: why do we need to fetch it if it is not used and redeclared
            $instanceRisk = $this->instanceRiskTable->findByInstanceAssetThreatUuidAndVulnerabilityUuid(
                $instance,
                $monarcObject->getAsset(),
                $threatData['uuid'],
                $vulnerabilityData['uuid']
            );

            if ((int)$instanceRiskData['specific'] === Entity\InstanceRisk::TYPE_SPECIFIC) {
                /** @var ?Entity\Threat $threat */
                $threat = $this->importCacheHelper->getItemFromArrayCache('threats', $threatData['uuid'])
                    ?: $this->threatTable->findByUuidAndAnr($threatData['uuid'], $anr, false);
                if ($threat === null) {
                    /* The code should be unique. */
                    $threatData['code'] =
                        $this->importCacheHelper->getItemFromArrayCache('threats_codes', $threatData['code']) !== null
                        || $this->threatTable->doesCodeAlreadyExist($threatData['code'], $anr)
                            ? $threatData['code'] . '-' . time()
                            : $threatData['code'];
// TODO: inject and use the $this->threatService->create();
                    $threat = (new Entity\Threat())
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

                    /*
                     * Unfortunately we don't add "themes" on the same level as "risks" and "threats",
                     * but only under "asset".
                     * TODO: we should add theme linked to the threat inside of the threat object
                     * data when export later on. after we can set it $threat->setTheme($theme);
                     */

                    $this->threatTable->save($threat, false);

                    $this->importCacheHelper->addItemToArrayCache('threats', $threat, $threat->getUuid());
                    $this->importCacheHelper
                        ->addItemToArrayCache('threats_codes', $threat->getCode(), $threat->getCode());
                }
                /** @var ?Entity\Vulnerability $vulnerability */
                $vulnerability = $this->importCacheHelper
                    ->getItemFromArrayCache('vulnerabilities', $vulnerabilityData['uuid'])
                    ?: $this->vulnerabilityTable->findByUuidAndAnr($vulnerabilityData['uuid'], $anr, false);
                if ($vulnerability === null) {
                    /* The code should be unique. */
                    $vulnerabilityData['code'] =
                        $this->importCacheHelper
                            ->getItemFromArrayCache('vulnerabilities_codes', $vulnerabilityData['code']) !== null
                        || $this->vulnerabilityTable->doesCodeAlreadyExist($vulnerabilityData['code'], $anr)
                            ? $vulnerabilityData['code'] . '-' . time()
                            : $vulnerabilityData['code'];
// TODO: inject and use $this->vulnerabilityService->create($anr, $vulnerabilityData);
                    $vulnerability = (new Entity\Vulnerability())
                        ->setUuid($vulnerabilityData['uuid'])
                        ->setAnr($anr)
                        ->setLabels($vulnerabilityData)
                        ->setDescriptions($vulnerabilityData)
                        ->setCode($vulnerabilityData['code'])
                        ->setMode($vulnerabilityData['mode'])
                        ->setStatus($vulnerabilityData['status'])
                        ->setCreator($this->connectedUser->getEmail());

                    $this->vulnerabilityTable->save($vulnerability, false);

                    $this->importCacheHelper
                        ->addItemToArrayCache('vulnerabilities', $vulnerability, $vulnerability->getUuid());
                    $this->importCacheHelper->addItemToArrayCache(
                        'vulnerabilities_codes',
                        $vulnerability->getCode(),
                        $vulnerability->getCode()
                    );
                }

                $instanceRisk = $this->createInstanceRiskFromData(
                    $instanceRiskData,
                    $anr,
                    $instance,
                    $monarcObject->getAsset(),
                    $threat,
                    $vulnerability
                );

                $this->instanceRiskTable->save($instanceRisk, false);

                /*
                 * Create specific risks linked to the brother instances.
                 */
                $instanceBrothers = $this->getInstanceBrothers($instance);
                foreach ($instanceBrothers as $instanceBrother) {
                    $instanceRiskBrother = $this->createInstanceRiskFromData(
                        $instanceRiskData,
                        $anr,
                        $instanceBrother,
                        $monarcObject->getAsset(),
                        $threat,
                        $vulnerability
                    );

                    $this->instanceRiskTable->save($instanceRiskBrother, false);
                }
            }

            if ($instanceRisk !== null && $includeEval) {
                $instanceRisk->setThreatRate(
                    $this->isImportTypeAnr()
                        ? $instanceRiskData['threatRate']
                        : $this->convertValueWithinNewScalesRange(
                            $instanceRiskData['threatRate'],
                            $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['min'],
                            $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['max'],
                            $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['min'],
                            $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_THREAT]['max']
                        )
                );
                $instanceRisk->setVulnerabilityRate(
                    $this->isImportTypeAnr()
                        ? $instanceRiskData['vulnerabilityRate']
                        : $this->convertValueWithinNewScalesRange(
                            $instanceRiskData['vulnerabilityRate'],
                            $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_VULNERABILITY]['min'],
                            $scalesData['external'][CoreEntity\ScaleSuperClass::TYPE_VULNERABILITY]['max'],
                            $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_VULNERABILITY]['min'],
                            $scalesData['current'][CoreEntity\ScaleSuperClass::TYPE_VULNERABILITY]['max']
                        )
                );
                $instanceRisk->setIsThreatRateNotSetOrModifiedExternally((bool)$instanceRiskData['mh']);
                $instanceRisk->setKindOfMeasure($instanceRiskData['kindOfMeasure']);
                $instanceRisk->setComment($instanceRiskData['comment'] ?? '');
                $instanceRisk->setCommentAfter($instanceRiskData['commentAfter'] ?? '');

                // La valeur -1 pour le reduction_amount n'a pas de sens, c'est 0 le minimum. Le -1 fausse
                // les calculs.
                // Cas particulier, faudrait pas mettre n'importe quoi dans cette colonne si on part d'une scale
                // 1 - 7 vers 1 - 3 on peut pas avoir une réduction de 4, 5, 6 ou 7
                $instanceRisk->setReductionAmount(
                    $instanceRiskData['reductionAmount'] !== -1
                        ? $this->convertValueWithinNewScalesRange(
                            $instanceRiskData['reductionAmount'],
                            0,
                            $instanceRiskData['vulnerabilityRate'],
                            0,
                            $instanceRisk->getVulnerabilityRate(),
                            0
                        )
                        : 0
                );
                $this->instanceRiskTable->save($instanceRisk, false);

                // Merge all fields for global assets.
                if ($modeImport === 'merge'
                    && !$instanceRisk->isSpecific()
                    && $instance->getObject()->isScopeGlobal()
                ) {
                    $objectIdsBrothers = $this->instanceTable->findByAnrAndObject($anr, $instance->getObject());

                    // TODO: findBy...
                    /** @var Entity\InstanceRisk $instanceRiskBrothers */
                    $instanceRiskBrothers = current($this->instanceRiskTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'instance' => ['op' => 'IN', 'value' => $objectIdsBrothers],
                        'amv' => [
                            'anr' => $anr->getId(),
                            'uuid' => $instanceRisk->getAmv()->getUuid(),
                        ],
                    ]));

                    if ($instanceRiskBrothers !== null) {
                        $dataUpdate = [];
                        $dataUpdate['anr'] = $anr->getId();
                        $dataUpdate['threatRate'] = $instanceRiskBrothers->getThreatRate();
                        $dataUpdate['vulnerabilityRate'] = $instanceRiskBrothers->getVulnerabilityRate();
                        $dataUpdate['kindOfMeasure'] = $instanceRiskBrothers->getKindOfMeasure();
                        $dataUpdate['reductionAmount'] = $instanceRiskBrothers->getReductionAmount();
                        // Check if comment is different.
                        if (strcmp($instanceRiskBrothers->getComment(), $instanceRisk->getComment()) !== 0
                            && strpos($instanceRiskBrothers->getComment(), $instanceRisk->getComment()) === false
                        ) {
                            $dataUpdate['comment'] = $instanceRiskBrothers->getComment() . "\n\n"
                                . $instanceRisk->getComment(); // Merge comments
                        } else {
                            $dataUpdate['comment'] = $instanceRiskBrothers->getComment();
                        }

                        // TODO: pass the object to update.
                        $this->anrInstanceRiskService->update($anr, $instanceRisk->getId(), $dataUpdate);
                    }
                }

                // Process recommendations.
                if (!empty($data['recos'][$instanceRiskData['id']])) {
                    $this->recommendationImportProcessor->prepareRecommendationsCache($anr);
                    foreach ($data['recos'][$instanceRiskData['id']] as $reco) {
                        $recommendation = $this->recommendationImportProcessor->processRecommendationDataLinkedToRisk(
                            $anr,
                            $reco,
                            $data['recSets'],
                            $instanceRiskData['kindOfMeasure'] !== CoreEntity\InstanceRiskSuperClass::KIND_NOT_TREATED
                        );

                        /* Avoid linking the recommendation 2 times. */
                        foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                            if ($recommendationRisk->getRecommendation() !== null
                                && $recommendationRisk->getRecommendation()->getUuid() === $recommendation->getUuid()
                            ) {
                                continue 2;
                            }
                        }

                        $recommendationRisk = $this->anrRecommendationRiskService->createRecommendationRisk(
                            $recommendation,
                            $instanceRisk,
                            $reco['commentAfter'] ?? '',
                            false
                        );

                        // Replicate recommendation to brothers.
                        if ($modeImport === 'merge' && $recommendationRisk->hasGlobalObjectRelation()) {
                            $brotherInstances = $this->getInstanceBrothers($instance);
                            if (!empty($brotherInstances)) {
                                foreach ($brotherInstances as $brotherInstance) {
                                    // Get the risks of brothers
                                    /** @var Entity\InstanceRisk[] $brothers */
                                    if ($instanceRisk->isSpecific()) {
                                        $brothers = $this->instanceRiskTable->findByInstanceAndInstanceRiskRelations(
                                            $brotherInstance,
                                            $instanceRisk,
                                            true
                                        );
                                    } else {
                                        $brothers = $this->instanceRiskTable->findByInstanceAndAmv(
                                            $brotherInstance,
                                            $instanceRisk->getAmv()
                                        );
                                    }

                                    foreach ($brothers as $brother) {
                                        $recommendationRiskBrother = $this->recommendationRiskTable
                                            ->findByInstanceRiskAndRecommendation($brother, $recommendation);
                                        if ($recommendationRiskBrother === null) {
                                            $recommendationRiskBrother = (new Entity\RecommendationRisk())
                                                ->setAnr($anr)
                                                ->setInstance($brotherInstance)
                                                ->setInstanceRisk($brother)
                                                ->setGlobalObject(
                                                    $monarcObject->isScopeGlobal() ? $monarcObject : null
                                                )
                                                ->setAsset($instanceRisk->getAsset())
                                                ->setThreat($instanceRisk->getThreat())
                                                ->setVulnerability($instanceRisk->getVulnerability())
                                                ->setCommentAfter((string)$reco['commentAfter'])
                                                ->setRecommendation($recommendation)
                                                ->setCreator($this->connectedUser->getEmail());

                                            $this->recommendationRiskTable
                                                ->save($recommendationRiskBrother, false);
                                        }
                                    }
                                }
                                $this->recommendationRiskTable->flush();
                            }
                        }
                    }
                    $this->recommendationRiskTable->flush();
                }
            }

            // Check recommendations from a brother
            $instanceBrothers = $this->getInstanceBrothers($instance);
            if (!empty($instanceBrothers) && $instanceRisk !== null && !$instanceRisk->isSpecific()) {
                $instanceRiskBrothers = $this->instanceRiskTable->findByInstanceAndAmv(
                    current($instanceBrothers),
                    $instanceRisk->getAmv()
                );

                foreach ($instanceRiskBrothers as $instanceRiskBrother) {
                    /** @var Entity\RecommendationRisk[] $brotherRecoRisks */
                    // Get recommendation of brother
                    $brotherRecoRisks = $this->recommendationRiskTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'instanceRisk' => $instanceRiskBrother->getId(),
                        'instance' => ['op' => '!=', 'value' => $instance->getId()],
                        'globalObject' => [
                            'anr' => $anr->getId(),
                            'uuid' => $monarcObject->getUuid(),
                        ],
                    ]);

                    if (!empty($brotherRecoRisks)) {
                        foreach ($brotherRecoRisks as $brotherRecoRisk) {
                            $recommendationRisk = $this->recommendationRiskTable->findByInstanceRiskAndRecommendation(
                                $instanceRisk,
                                $brotherRecoRisk->getRecommendation()
                            );

                            if ($recommendationRisk === null) {
                                $recommendationRisk = (new Entity\RecommendationRisk())
                                    ->setAnr($anr)
                                    ->setInstance($instance)
                                    ->setInstanceRisk($brotherRecoRisk->getInstanceRisk())
                                    ->setGlobalObject($brotherRecoRisk->getGlobalObject())
                                    ->setAsset($brotherRecoRisk->getAsset())
                                    ->setThreat($brotherRecoRisk->getThreat())
                                    ->setVulnerability($brotherRecoRisk->getVulnerability())
                                    ->setCommentAfter($brotherRecoRisk->getCommentAfter())
                                    ->setRecommendation($brotherRecoRisk->getRecommendation())
                                    ->setCreator($this->connectedUser->getEmail());

                                $this->recommendationRiskTable->save($recommendationRisk, false);
                            }
                        }

                        $this->recommendationRiskTable->flush();
                    }
                }
            }
        }

        // Check recommendations from specific risk of brothers
        $recoToCreate = [];
        // Get all specific risks of instance
        foreach ($instance->getInstanceRisks() as $instanceRisk) {

            $this->recalculateRiskRates($instanceRisk);
            $this->instanceRiskTable->save($instanceRisk, false);

            if (!$instanceRisk->isSpecific()) {
                continue;
            }

            // TODO: replace all the queries with QueryBuilder. Review the logic.
            // Get recommendations of brothers
            /** @var Entity\RecommendationRisk[] $exitingRecoRisks */
            $exitingRecoRisks = $this->recommendationRiskTable->getEntityByFields([
                'anr' => $anr->getId(),
                'asset' => ['anr' => $anr->getId(), 'uuid' => $instanceRisk->getAsset()->getUuid()],
                'threat' => ['anr' => $anr->getId(), 'uuid' => $instanceRisk->getThreat()->getUuid()],
                'vulnerability' => ['anr' => $anr->getId(), 'uuid' => $instanceRisk->getVulnerability()->getUuid()],
            ]);
            foreach ($exitingRecoRisks as $exitingRecoRisk) {
                if ($instance->getId() !== $exitingRecoRisk->getInstance()->getId()) {
                    $recoToCreate[] = $exitingRecoRisk;
                }
            }
        }

        /** @var Entity\RecommendationRisk $recommendationRiskToCreate */
        foreach ($recoToCreate as $recommendationRiskToCreate) {
            // Check if reco-risk link exist
            $recoCreated = $this->recommendationRiskTable->getEntityByFields([
                'recommendation' => [
                    'anr' => $anr->getId(),
                    'uuid' => $recommendationRiskToCreate->getRecommendation()->getUuid(),
                ],
                'instance' => $instance->getId(),
                'asset' => [
                    'anr' => $anr->getId(),
                    'uuid' => $recommendationRiskToCreate->getAsset()->getUuid(),
                ],
                'threat' => [
                    'anr' => $anr->getId(),
                    'uuid' => $recommendationRiskToCreate->getThreat()->getUuid(),
                ],
                'vulnerability' => [
                    'anr' => $anr->getId(),
                    'uuid' => $recommendationRiskToCreate->getVulnerability()->getUuid(),
                ],
            ]);

            if (empty($recoCreated)) {
                // TODO: check if we can get it in different way as it is too heavy.
                $instanceRiskSpecific = current($this->instanceRiskTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'instance' => $instance->getId(),
                    'specific' => 1,
                    'asset' => [
                        'anr' => $anr->getId(),
                        'uuid' => $recommendationRiskToCreate->getAsset()->getUuid(),
                    ],
                    'threat' => [
                        'anr' => $anr->getId(),
                        'uuid' => $recommendationRiskToCreate->getThreat()->getUuid(),
                    ],
                    'vulnerability' => [
                        'anr' => $anr->getId(),
                        'uuid' => $recommendationRiskToCreate->getVulnerability()->getUuid(),
                    ],
                ]));

                $recommendationRisk = (new Entity\RecommendationRisk())
                    ->setAnr($anr)
                    ->setInstance($instance)
                    ->setInstanceRisk($instanceRiskSpecific)
                    ->setGlobalObject($recommendationRiskToCreate->getGlobalObject())
                    ->setAsset($instanceRiskSpecific->getAsset())
                    ->setThreat($instanceRiskSpecific->getThreat())
                    ->setVulnerability($instanceRiskSpecific->getVulnerability())
                    ->setCommentAfter($recommendationRiskToCreate->getCommentAfter())
                    ->setRecommendation($recommendationRiskToCreate->getRecommendation())
                    ->setCreator($this->connectedUser->getEmail());

                $this->recommendationRiskTable->save($recommendationRisk, false);
            }
        }

        $this->recommendationRiskTable->flush();
    }

    /**
     * Update the instance impacts from brothers for global assets.
     */
    private function updateInstanceImpactsFromBrothers(Entity\Instance $instance, string $modeImport): void
    {
        if ($modeImport === 'merge' && $instance->getObject()->isScopeGlobal()) {
            $instanceBrothers = $this->getInstanceBrothers($instance);
            if (!empty($instanceBrothers)) {
                // Update impacts of the instance. We use only one brother global instance as the impacts are the same.
                $instanceBrother = current($instanceBrothers);
                foreach (Entity\InstanceConsequence::getAvailableScalesCriteria() as $scaleCriteria) {
                    if ($instanceBrother->{'getInherited' . $scaleCriteria}() === 0) {
                        $instance->{'setInherited' . $scaleCriteria}(0);
                        $instance->{'set' . $scaleCriteria}($instanceBrother->{'get' . $scaleCriteria}());
                    } elseif ($instance->getParent()) {
                        $instance->{'setInherited' . $scaleCriteria}(1);
                        $instance->{'set' . $scaleCriteria}($instance->getParent()->{'get' . $scaleCriteria}());
                    } else {
                        $instance->{'setInherited' . $scaleCriteria}(1);
                        $instance->{'set' . $scaleCriteria}($instanceBrother->{'get' . $scaleCriteria}());
                    }
                }

                // Update consequences for all brothers.
                foreach ($instanceBrothers as $instanceBrother) {
                    foreach ($instanceBrother->getInstanceConsequences() as $instanceConsequence) {
                        $instanceConsequenceBrothers = $this->instanceConsequenceTable
                            ->findByAnrInstanceAndScaleImpactType(
                                $instance->getAnr(),
                                $instance,
                                $instanceConsequence->getScaleImpactType()
                            );
                        foreach ($instanceConsequenceBrothers as $instanceConsequenceBrother) {
                            $instanceConsequenceBrother->setIsHidden($instanceConsequence->isHidden())
                                ->setConfidentiality($instanceConsequence->getConfidentiality())
                                ->setIntegrity($instanceConsequence->getIntegrity())
                                ->setAvailability($instanceConsequence->getAvailability());

                            $this->instanceConsequenceTable->save($instanceConsequenceBrother, false);
                        }
                    }
                }

                $this->instanceTable->save($instance);
            }
        }
    }

    /**
     * @return Entity\Instance[]
     */
    private function getInstanceBrothers(Entity\Instance $instance): array
    {
        if (!isset($this->cachedData['instanceBrothers'][$instance->getId()])) {
            $this->cachedData['instanceBrothers'][$instance->getId()] = $this->instanceTable
                ->findByAnrAssetAndObjectExcludeInstance(
                    $instance->getAnr(),
                    $instance->getAsset(),
                    $instance->getObject(),
                    $instance
                );
        }

        return $this->cachedData['instanceBrothers'][$instance->getId()];
    }

    private function createInstanceRiskFromData(
        array $instanceRiskData,
        Entity\Anr $anr,
        Entity\Instance $instance,
        Entity\Asset $asset,
        Entity\Threat $threat,
        Entity\Vulnerability $vulnerability
    ): Entity\InstanceRisk {
        $instanceRisk = (new Entity\InstanceRisk())
            ->setAnr($anr)
            ->setInstance($instance)
            ->setAsset($asset)
            ->setThreat($threat)
            ->setVulnerability($vulnerability)
            ->setSpecific($instanceRiskData['specific'])
            ->setIsThreatRateNotSetOrModifiedExternally((bool)$instanceRiskData['mh'])
            ->setThreatRate((int)$instanceRiskData['threatRate'])
            ->setVulnerabilityRate((int)$instanceRiskData['vulnerabilityRate'])
            ->setKindOfMeasure($instanceRiskData['kindOfMeasure'])
            ->setReductionAmount((int)$instanceRiskData['reductionAmount'])
            ->setComment((string)$instanceRiskData['comment'])
            ->setCommentAfter((string)$instanceRiskData['commentAfter'])
            ->setCacheMaxRisk((int)$instanceRiskData['cacheMaxRisk'])
            ->setCacheTargetedRisk((int)$instanceRiskData['cacheTargetedRisk'])
            ->setRiskConfidentiality((int)$instanceRiskData['riskC'])
            ->setRiskIntegrity((int)$instanceRiskData['riskI'])
            ->setRiskAvailability((int)$instanceRiskData['riskD'])
            ->setContext($instanceRiskData['context'] ?? '')
            ->setCreator($this->connectedUser->getEmail());

        if (!empty($instanceRiskData['riskOwner'])) {
            $instanceRiskOwner = $this->instanceRiskOwnerService->getOrCreateInstanceRiskOwner(
                $anr,
                $anr,
                $instanceRiskData['riskOwner']
            );
            $instanceRisk->setInstanceRiskOwner($instanceRiskOwner);
        }

        return $instanceRisk;
    }

    private function createInstance(
        array $data,
        Entity\Anr $anr,
        ?CoreEntity\InstanceSuperClass $parentInstance,
        Entity\MonarcObject $monarcObject
    ): Entity\Instance {
        $instanceData = $data['instance'];
        $instanceData['object'] = $monarcObject;
        $instanceData['position'] = ++$this->currentMaxInstancePosition;
        $instanceData['setOnlyExactPosition'] = true;

        return $this->anrInstanceService->createInstance($anr, $instanceData, $parentInstance === null);
    }

    /**
     * Validates if the data can be imported into the anr.
     */
    private function validateIfImportIsPossible(Entity\Anr $anr, ?Entity\Instance $parent, array $data): void
    {
        if ($parent !== null
            && ($parent->getLevel() === CoreEntity\InstanceSuperClass::LEVEL_INTER || $parent->getAnr() !== $anr)
        ) {
            throw new Exception('Parent instance should be in the node tree and the analysis IDs are matched', 412);
        }

        if ((!empty($data['with_eval']) || !empty($data['withEval'])) && empty($data['scales'])) {
            throw new Exception('The importing file should include evaluation scales.', 412);
        }

        if (!$this->isMonarcVersionLowerThen('2.13.1') && $anr->getLanguageCode() !== $data['languageCode']) {
            throw new Exception(sprintf(
                'The current analysis language "%s" should be the same as importing one "%s"',
                $anr->getLanguageCode(),
                $data['languageCode']
            ), 412);
        }
    }

    /**
     * @throws Exception
     */
    private function setAndValidateMonarcVersion($data): void
    {
        if (isset($data['monarc_version'])) {
            $this->monarcVersion = strpos($data['monarc_version'], 'master') === false ? $data['monarc_version'] : '99';
        }

        if ($this->isMonarcVersionLowerThen('2.8.2')) {
            throw new Exception('Import of files exported from MONARC v2.8.1 or lower are not supported.'
                . ' Please contact us for more details.');
        }
    }

    private function isMonarcVersionLowerThen(string $version): bool
    {
        return version_compare($this->monarcVersion, $version) < 0;
    }
}
