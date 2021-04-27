<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AssetSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\FrontOffice\Helper\EncryptDecryptHelperTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Delivery;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceConsequence;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\Interview;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\MeasureMeasure;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\Question;
use Monarc\FrontOffice\Model\Entity\QuestionChoice;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Entity\RecommandationRisk;
use Monarc\FrontOffice\Model\Entity\RecommandationSet;
use Monarc\FrontOffice\Model\Entity\Referential;
use Monarc\FrontOffice\Model\Entity\Scale;
use Monarc\FrontOffice\Model\Entity\ScaleImpactType;
use Monarc\FrontOffice\Model\Entity\Soa;
use Monarc\FrontOffice\Model\Entity\SoaCategory;
use Monarc\FrontOffice\Model\Entity\Theme;
use Monarc\FrontOffice\Model\Entity\Threat;
use Monarc\FrontOffice\Model\Entity\Vulnerability;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\DeliveryTable;
use Monarc\FrontOffice\Model\Table\InstanceConsequenceTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\InterviewTable;
use Monarc\FrontOffice\Model\Table\MeasureMeasureTable;
use Monarc\FrontOffice\Model\Table\MeasureTable;
use Monarc\FrontOffice\Model\Table\QuestionChoiceTable;
use Monarc\FrontOffice\Model\Table\QuestionTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\RecommandationSetTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\ReferentialTable;
use Monarc\FrontOffice\Model\Table\ScaleCommentTable;
use Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable;
use Monarc\FrontOffice\Model\Table\ScaleTable;
use Monarc\FrontOffice\Model\Table\SoaCategoryTable;
use Monarc\FrontOffice\Model\Table\SoaTable;
use Monarc\FrontOffice\Model\Table\ThemeTable;
use Monarc\FrontOffice\Model\Table\ThreatTable;
use Monarc\FrontOffice\Model\Table\VulnerabilityTable;
use Ramsey\Uuid\Uuid;

class InstanceImportService
{
    use EncryptDecryptHelperTrait;

    /** @var string|null */
    private $monarcVersion;

    /** @var array */
    private $cachedData = [];

    /** @var int */
    private $currentAnalyseMaxRecommendationPosition;

    /** @var int */
    private $initialAnalyseMaxRecommendationPosition;

    /** @var int */
    private $currentMaxInstancePosition;

    /** @var AnrInstanceRiskService */
    private $anrInstanceRiskService;

    /** @var AnrInstanceService */
    private $anrInstanceService;

    /** @var AssetImportService */
    private $assetImportService;

    /** @var AnrRecordService */
    private $anrRecordService;

    /** @var AnrInstanceRiskOpService */
    private $anrInstanceRiskOpService;

    /** @var InstanceTable */
    private $instanceTable;

    /** @var AnrTable */
    private $anrTable;

    /** @var RecommandationTable */
    private $recommendationTable;

    /** @var InstanceConsequenceTable */
    private $instanceConsequenceTable;

    /** @var ScaleTable */
    private $scaleTable;

    /** @var ScaleImpactTypeTable */
    private $scalesImpactTypeTable;

    /** @var ThreatTable */
    private $threatTable;

    /** @var VulnerabilityTable */
    private $vulnerabilityTable;

    /** @var RecommandationSetTable */
    private $recommendationSetTable;

    /** @var InstanceRiskTable */
    private $instanceRiskTable;

    /** @var InstanceRiskOpTable */
    private $instanceRiskOpTable;

    /** @var RecommandationRiskTable */
    private $recommendationRiskTable;

    /** @var QuestionTable */
    private $questionTable;

    /** @var QuestionChoiceTable */
    private $questionChoiceTable;

    /** @var SoaTable */
    private $soaTable;

    /** @var MeasureTable */
    private $measureTable;

    /** @var MeasureMeasureTable */
    private $measureMeasureTable;

    /** @var ThemeTable */
    private $themeTable;

    /** @var ReferentialTable */
    private $referentialTable;

    /** @var SoaCategoryTable */
    private $soaCategoryTable;

    /** @var ObjectImportService */
    private $objectImportService;

    /** @var InterviewTable */
    private $interviewTable;

    /** @var DeliveryTable */
    private $deliveryTable;

    /** @var ScaleImpactTypeTable */
    private $scaleImpactTypeTable;

    /** @var ScaleCommentTable */
    private $scaleCommentTable;

    /** @var AnrScaleCommentService */
    private $anrScaleCommentService;

    public function __construct(
        AnrInstanceRiskService $anrInstanceRiskService,
        AnrInstanceService $anrInstanceService,
        AssetImportService $assetImportService,
        ObjectImportService $objectImportService,
        AnrRecordService $anrRecordService,
        AnrInstanceRiskOpService $anrInstanceRiskOpService,
        InstanceTable $instanceTable,
        AnrTable $anrTable,
        InstanceConsequenceTable $instanceConsequenceTable,
        ScaleTable $scaleTable,
        ScaleImpactTypeTable $scalesImpactTypeTable,
        ThreatTable $threatTable,
        VulnerabilityTable $vulnerabilityTable,
        RecommandationTable $recommendationTable,
        RecommandationSetTable $recommendationSetTable,
        InstanceRiskTable $instanceRiskTable,
        InstanceRiskOpTable $instanceRiskOpTable,
        RecommandationRiskTable $recommendationRiskTable,
        QuestionTable $questionTable,
        QuestionChoiceTable $questionChoiceTable,
        SoaTable $soaTable,
        MeasureTable $measureTable,
        MeasureMeasureTable $measureMeasureTable,
        ThemeTable $themeTable,
        ReferentialTable $referentialTable,
        SoaCategoryTable $soaCategoryTable,
        InterviewTable $interviewTable,
        DeliveryTable $deliveryTable,
        ScaleImpactTypeTable $scaleImpactTypeTable,
        ScaleCommentTable $scaleCommentTable,
        AnrScaleCommentService $anrScaleCommentService
    ) {
        $this->anrInstanceRiskService = $anrInstanceRiskService;
        $this->anrInstanceService = $anrInstanceService;
        $this->objectImportService = $objectImportService;
        $this->anrRecordService = $anrRecordService;
        $this->anrInstanceRiskOpService = $anrInstanceRiskOpService;
        $this->instanceTable = $instanceTable;
        $this->anrTable = $anrTable;
        $this->instanceConsequenceTable = $instanceConsequenceTable;
        $this->scaleTable = $scaleTable;
        $this->scalesImpactTypeTable = $scalesImpactTypeTable;
        $this->threatTable = $threatTable;
        $this->vulnerabilityTable = $vulnerabilityTable;
        $this->recommendationTable = $recommendationTable;
        $this->recommendationSetTable = $recommendationSetTable;
        $this->instanceRiskTable = $instanceRiskTable;
        $this->instanceRiskOpTable = $instanceRiskOpTable;
        $this->recommendationRiskTable = $recommendationRiskTable;
        $this->questionTable = $questionTable;
        $this->questionChoiceTable = $questionChoiceTable;
        $this->assetImportService = $assetImportService;
        $this->soaTable = $soaTable;
        $this->measureTable = $measureTable;
        $this->measureMeasureTable = $measureMeasureTable;
        $this->themeTable = $themeTable;
        $this->referentialTable = $referentialTable;
        $this->soaCategoryTable = $soaCategoryTable;
        $this->interviewTable = $interviewTable;
        $this->deliveryTable = $deliveryTable;
        $this->scaleImpactTypeTable = $scaleImpactTypeTable;
        $this->scaleCommentTable = $scaleCommentTable;
        // TODO: remove after the usage refactoring.
        $this->anrScaleCommentService = $anrScaleCommentService;
    }

    /**
     *  Available import modes: 'merge', which will update the existing instances using the file's data, or 'duplicate' which
     * will create a new instance using the data.
     *
     * @param int $anrId The ANR ID
     * @param array $data The data that has been posted to the API
     *
     * @return array An array where the first key is the generated IDs, and the second are import errors
     * @throws EntityNotFoundException
     * @throws Exception If the uploaded data is invalid, or the ANR invalid
     * @throws OptimisticLockException
     */
    public function importFromFile($anrId, $data)
    {
        // Mode may either be 'merge' or 'duplicate'
        $mode = empty($data['mode']) ? 'merge' : $data['mode'];

        /*
         * The object may be imported at the root, or under an existing instance in the ANR instances tree
         */
        $parentInstance = null;
        if (!empty($data['idparent'])) {
            $parentInstance = $this->instanceTable->findById((int)$data['idparent']);
        }

        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($data['file'])) {
            throw new Exception('File missing', 412);
        }

        $ids = [];
        $errors = [];
        $anr = $this->anrTable->findById($anrId);

        // TODO: remove this!!!
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', -1);

        foreach ($data['file'] as $keyfile => $f) {
            // Ensure the file has been uploaded properly, silently skip the files that are erroneous
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])) {
                if (empty($data['password'])) {
                    $file = json_decode(trim(file_get_contents($f['tmp_name'])), true);
                    if ($file === false) {
                        // Support legacy export which were base64 encoded.
                        $file = json_decode(trim(base64_decode(file_get_contents($f['tmp_name']))), true);
                    }
                } else {
                    // Decrypt the file and store the JSON data as an array in memory.
                    $key = $data['password'];
                    $file = json_decode(trim($this->decrypt(file_get_contents($f['tmp_name']), $key)), true);
                    if ($file === false) {
                        // Support legacy export which were base64 encoded.
                        $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), $key)), true);
                    }
                }

                if ($file !== false
                    && ($id = $this->importFromArray($file, $anr, $parentInstance, $mode)) !== false) {
                    // Import was successful, store the ID
                    if (is_array($id)) {
                        $ids += array_merge($ids, $id);
                    } else {
                        $ids[] = $id;
                    }
                } else {
                    $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                }
            }

            // Free up the memory in case we're handling big files
            unset($data['file'][$keyfile]);
        }

        return [$ids, $errors];
    }

    /**
     * Imports an instance from an exported data (json) array.
     *
     * @param array $data The instance data
     * @param Anr $anr The target ANR
     * @param null|InstanceSuperClass $parentInstance The parent instance, which should be imported or null if it is root.
     * @param string $modeImport Import mode, either 'merge' or 'duplicate'
     *
     * @return array|bool An array of created instances IDs, or false in case of error
     *
     * @throws Exception
     * @throws OptimisticLockException
     * @throws EntityNotFoundException
     */
    public function importFromArray(
        array $data,
        Anr $anr,
        ?InstanceSuperClass $parentInstance = null,
        string $modeImport = 'merge'
    ) {
        if (!$this->isImportPossible($anr, $parentInstance)) {
            return false;
        }

        if (isset($data['monarc_version'])) {
            $this->monarcVersion = strpos($data['monarc_version'], 'master') === false ? $data['monarc_version'] : '99';
        }

        $this->initialAnalyseMaxRecommendationPosition = $this->recommendationTable->getMaxPositionByAnr($anr);
        $this->currentAnalyseMaxRecommendationPosition = $this->initialAnalyseMaxRecommendationPosition;
        $this->currentMaxInstancePosition = $this->instanceTable->getMaxPositionByAnrAndParent($anr, $parentInstance);

        if (isset($data['type']) && $data['type'] === 'instance') {
            return $this->importInstanceFromArray($data, $anr, $parentInstance, $modeImport);
        }

        if (isset($data['type']) && $data['type'] === 'anr') {
            return $this->importAnrFromArray($data, $anr, $parentInstance, $modeImport);
        }

        return false;
    }

    /**
     * @param array $data
     * @param Anr $anr
     * @param InstanceSuperClass|null $parentInstance
     * @param string $modeImport
     *
     * @return bool|int
     *
     * @throws EntityNotFoundException
     * @throws Exception
     * @throws NonUniqueResultException
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function importInstanceFromArray(
        array $data,
        Anr $anr,
        ?InstanceSuperClass $parentInstance,
        string $modeImport
    ) {
        /*
         * TODO: Global list.
         * 1. sort out the cachedData (cache) usage, save objects instead of id/uuid
         * 2. replace all the usages of getEntityByFields to findBy
         * 3. replace save to saveEntity
         * 4. replace setting array in the entities constructors to setters usage.
         * 5. extract peaces of the code to separate private methods
         * 6. optimize some logic, group maybe.
         * 7. cover with tests.
         */

        $monarcObject = $this->objectImportService->importFromArray($data['object'], $anr, $modeImport);
        if ($monarcObject === null) {
            return false;
        }

        $instanceData = $data['instance'];
        $instance = (new Instance())
            ->setAnr($anr)
            ->setLabels($instanceData)
            ->setNames($instanceData)
            ->setDisponibility((float)$instanceData['disponibility'])
            ->setLevel($parentInstance === null ? Instance::LEVEL_ROOT : $instanceData['level'])
            ->setRoot($parentInstance === null ? null : $parentInstance->getRoot())
            ->setParent($parentInstance)
            ->setAssetType($instanceData['assetType'])
            ->setExportable($instanceData['exportable'])
            ->setPosition(++$this->currentMaxInstancePosition)
            ->setConfidentiality($instanceData['c'])
            ->setIntegrity($instanceData['i'])
            ->setAvailability($instanceData['d'])
            ->setInheritedConfidentiality($instanceData['ch'])
            ->setInheritedIntegrity($instanceData['ih'])
            ->setInheritedAvailability($instanceData['dh'])
            ->setObject($monarcObject)
            ->setAsset($monarcObject->getAsset());

        $this->instanceTable->saveEntity($instance);

        $this->anrInstanceRiskService->createInstanceRisks($instance, $anr, $monarcObject);

        $includeEval = !empty($data['with_eval']);
        $this->prepareInstanceConsequences($data, $anr, $instance, $monarcObject, $includeEval);

        $this->updateInstanceImpactsFromBrothers($instance, $modeImport);

        $this->anrInstanceService->refreshImpactsInherited($instance);

        $this->createSetOfRecommendations($data, $anr);

        $this->processInstanceRisks($data, $anr, $instance, $monarcObject, $includeEval, $modeImport);

        if (!empty($data['risksop'])) {
            $toApproximate = [
                Scale::TYPE_THREAT => [
                    'netProb',
                    'targetedProb',
                ],
                Scale::TYPE_IMPACT => [
                    'netR',
                    'netO',
                    'netL',
                    'netF',
                    'netP',
                    'targetedR',
                    'targetedO',
                    'targetedL',
                    'targetedF',
                    'targetedP',
                ],
            ];
            $toApproximate[Scale::TYPE_THREAT][] = 'brutProb';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutR';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutO';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutL';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutF';
            $toApproximate[Scale::TYPE_IMPACT][] = 'brutP';

            $k = 0;
            foreach ($data['risksop'] as $ro) {
                $instanceRiskOp = new InstanceRiskOp();
                $ro['rolfRisk'] = null;
                $toExchange = $ro;
                unset($toExchange['id']);
                if ($monarcObject->getRolfTag() !== null) {
                    $rolfRisks = $monarcObject->getRolfTag()->getRisks();
                    $toExchange['rolfRisk'] = $rolfRisks[$k];
                    $toExchange['riskCacheCode'] = $rolfRisks[$k]->getCode();
                    $k++;
                }

                // traitement de l'évaluation -> c'est complètement dépendant des échelles locales
                if ($includeEval) {
                    // pas d'impact des subscales, on prend les échelles nominales
                    foreach ($toApproximate as $type => $list) {
                        foreach ($list as $i) {
                            $toExchange[$i] = $this->approximate(
                                $toExchange[$i],
                                $this->cachedData['scales']['orig'][$type]['min'],
                                $this->cachedData['scales']['orig'][$type]['max'],
                                $this->cachedData['scales']['dest'][$type]['min'],
                                $this->cachedData['scales']['dest'][$type]['max']
                            );
                        }
                    }
                }

                $instanceRiskOp->setLanguage($anr->getLanguage());
                $instanceRiskOp->exchangeArray($toExchange);
                $instanceRiskOp->setAnr($anr)
                    ->setInstance($instance)
                    ->setObject($monarcObject);
                if (isset($toExchange['rolfRisk'])) {
                    $instanceRiskOp->setRolfRisk($toExchange['rolfRisk']);
                }

                $this->instanceRiskOpTable->saveEntity($instanceRiskOp, false);

                // Process recommendations related to the operational risk.
                if ($includeEval && !empty($data['recosop'][$ro['id']])) {
                    foreach ($data['recosop'][$ro['id']] as $reco) {
                        $recommendation = $this->processRecommendationDataLinkedToRisk(
                            $anr,
                            $reco,
                            $ro['kindOfMeasure'] !== InstanceRiskOpSuperClass::KIND_NOT_TREATED
                        );

                        $recommendationRisk = (new RecommandationRisk())
                            ->setAnr($anr)
                            ->setInstance($instance)
                            ->setInstanceRiskOp($instanceRiskOp)
                            ->setGlobalObject($monarcObject->isScopeGlobal() ? $monarcObject : null)
                            ->setCommentAfter($reco['commentAfter'])
                            ->setRecommandation($recommendation);

                        $this->recommendationRiskTable->saveEntity($recommendationRisk, false);
                    }
                }

                $this->recommendationRiskTable->getDb()->flush();
            }
        }

        if (!empty($data['children'])) {
            usort($data['children'], function ($a, $b) {
                return $a['instance']['position'] <=> $b['instance']['position'];
            });
            foreach ($data['children'] as $child) {
                $this->importInstanceFromArray($child, $anr, $instance, $modeImport);
            }
            $this->updateChildrenImpacts($instance->getId());
        }

        $this->instanceTable->getDb()->flush();

        return $instance->getId();
    }

    /**
     * TODO: refactor the method entirely.
     *
     * @throws Exception
     * @throws OptimisticLockException
     */
    private function importAnrFromArray(
        array $data,
        Anr $anr,
        ?InstanceSuperClass $parentInstance,
        string $modeImport
    ): array {
        $labelKey = 'label' . $anr->getLanguage();
        // Method information
        if (!empty($data['method'])) { //Steps checkboxes
            if (!empty($data['method']['steps'])) {
                foreach ($data['method']['steps'] as $key => $v) {
                    if ($anr->get($key) === 0) {
                        $anr->set($key, $v);
                        $this->anrTable->saveEntity($anr, false);
                    }
                }
                $this->anrTable->getDb()->flush();
            }

            if (!empty($data['method']['data'])) { //Data of textboxes
                foreach ($data['method']['data'] as $key => $v) {
                    if ($anr->get($key) === null) {
                        $anr->set($key, $v);
                        $this->anrTable->saveEntity($anr, false);
                    }
                }
                $this->anrTable->getDb()->flush();
            }

            if (!empty($data['method']['interviews'])) { //Data of interviews
                foreach ($data['method']['interviews'] as $key => $v) {
                    $toExchange = $data['method']['interviews'][$key];
                    $toExchange['anr'] = $anr->getId();
                    $newInterview = new Interview();
                    $newInterview->setLanguage($anr->getLanguage());
                    $newInterview->exchangeArray($toExchange);
                    $newInterview->setAnr($anr);
                    // TODO: saveEntity
                    $this->interviewTable->save($newInterview, false);
                }
                $this->interviewTable->getDb()->flush();
            }

            if (!empty($data['method']['thresholds'])) { // Value of thresholds
                foreach ($data['method']['thresholds'] as $key => $v) {
                    $anr->set($key, $v);
                    $this->anrTable->saveEntity($anr, false);
                }
                $this->anrTable->getDb()->flush();
            }

            if (!empty($data['method']['deliveries'])) { // Data of deliveries generation
                foreach ($data['method']['deliveries'] as $key => $v) {
                    $toExchange = $data['method']['deliveries'][$key];
                    $toExchange['anr'] = $anr->getId();
                    $newDelivery = new Delivery();
                    $newDelivery->setLanguage($anr->getLanguage());
                    $newDelivery->exchangeArray($toExchange);
                    $newDelivery->setAnr($anr);
                    // TODO: use saveEntity.
                    $this->deliveryTable->save($newDelivery, false);
                }
                $this->deliveryTable->getDb()->flush();
            }

            if (!empty($data['method']['questions'])) { // Questions of trends evaluation
                // TODO: findByAnr
                $questions = $this->questionTable->getEntityByFields(['anr' => $anr->getId()]);
                foreach ($questions as $question) {
                    $this->questionTable->delete($question->id);
                }

                foreach ($data['method']['questions'] as $position => $questionData) {
                    $newQuestion = new Question();
                    $newQuestion->setLanguage($anr->getLanguage());
                    $newQuestion->exchangeArray($questionData);
                    $newQuestion->setAnr($anr);
                    // TODO: use setter.
                    $newQuestion->set('position', $position);
                    $this->questionTable->save($newQuestion, false);

                    if ((int)$questionData['multichoice'] === 1) {
                        foreach ($data['method']['questionChoice'] as $questionChoiceData) {
                            if ($questionChoiceData['question'] === $questionData['id']) {
                                $newQuestionChoice = new QuestionChoice();
                                $newQuestionChoice->setLanguage($anr->getLanguage());
                                $newQuestionChoice->exchangeArray($questionChoiceData);
                                $newQuestionChoice->setAnr($anr)
                                    ->setQuestion($newQuestion);
                                $this->questionChoiceTable->save($newQuestionChoice, false);
                            }
                        }
                    }
                }

                $this->questionTable->getDb()->flush();

                /** @var Question[] $questions */
                // TODO: findByAnr or better use the saved questions before, we don't need to query the db.
                $questions = $this->questionTable->getEntityByFields(['anr' => $anr->getId()]);

                /** @var QuestionChoice[] $questionChoices */
                // TODO: findByAnr or better use the saved questions before, we don't need to query the db.
                $questionChoices = $this->questionChoiceTable->getEntityByFields(['anr' => $anr->getId()]);

                foreach ($data['method']['questions'] as $questionAnswerData) {
                    foreach ($questions as $question) {
                        if ($question->get($labelKey) === $questionAnswerData[$labelKey]) {
                            // TODO: check if the method exists
                            if ($question->isMultiChoice()) {
                                $originQuestionChoices = [];
                                $response = $questionAnswerData['response'] ?? '';
                                if (trim($response, '[]')) {
                                    $originQuestionChoices = explode(',', trim($response, '[]'));
                                }
                                $questionChoicesIds = [];
                                foreach ($originQuestionChoices as $originQuestionChoice) {
                                    $chosenQuestionLabel = $data['method']['questionChoice'][$originQuestionChoice][$labelKey];
                                    foreach ($questionChoices as $questionChoice) {
                                        if ($questionChoice->get($labelKey) === $chosenQuestionLabel) {
                                            $questionChoicesIds[] = $questionChoice->getId();
                                        }
                                    }
                                }
                                $question->response = '[' . implode(',', $questionChoicesIds) . ']';
                            } else {
                                $question->response = $questionAnswerData['response'];
                            }
                            // TODO: saveEntity.
                            $this->questionTable->save($question, false);
                        }
                    }
                }

                $this->questionTable->getDb()->flush();
            }

            /*
             * Process the evaluation of threats.
             * TODO: we process all the threats in themes in AssetImportService, might be we can reuse the data from there.
             */
            if (!empty($data['method']['threats'])) {
                foreach ($data['method']['threats'] as $tId => $v) {
                    if (!empty($data['method']['threats'][$tId]['theme'])) {
                        // TODO: avoid such queries or check to add indexes or fetch all for the ANR and iterate in the code.
                        // TODO: we have findByAnrIdAndLabel() !
                        $themes = $this->themeTable->getEntityByFields([
                            'anr' => $anr->getId(),
                            $labelKey => $data['method']['threats'][$tId]['theme'][$labelKey]
                        ], ['id' => 'ASC']);
                        if (empty($themes)) { // Creation of new theme if no exist
                            $toExchange = $data['method']['threats'][$tId]['theme'];
                            $newTheme = new Theme();
                            $newTheme->setLanguage($anr->getLanguage());
                            $newTheme->exchangeArray($toExchange);
                            $newTheme->setAnr($anr);
                            $this->themeTable->saveEntity($newTheme);
                            // TODO: set objects here to avoid querying the db.
                            $data['method']['threats'][$tId]['theme']['id'] = $newTheme->getId();
                        } else {
                            foreach ($themes as $th) {
                                // TODO: set objects here to avoid querying the db.
                                $data['method']['threats'][$tId]['theme']['id'] = $th->getId();
                            }
                        }
                    }
                    /** @var Threat[] $threats */
                    $threats = $this->threatTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'code' => $data['method']['threats'][$tId]['code']
                    ], ['uuid' => 'ASC']);
                    if (empty($threats)) {
                        $toExchange = $data['method']['threats'][$tId];
                        $toExchange['mode'] = 0;
                        // TODO: use objects here ans use setter.
                        $toExchange['theme'] = $data['method']['threats'][$tId]['theme']['id'];
                        $newThreat = new Threat();
                        // TODO: drop it after setDep is removed.
                        $newThreat->setDbAdapter($this->threatTable->getDb());
                        $newThreat->setLanguage($anr->getLanguage());
                        $newThreat->exchangeArray($toExchange);
                        $this->setDependencies($newThreat, ['theme']);
                        $newThreat->setAnr($anr);
                        // TODO: saveEntity
                        $this->threatTable->saveEntity($newThreat, false);
                    } else {
                        foreach ($threats as $t) {
                            // TODO: use setters.
                            $t->set('trend', $data['method']['threats'][$tId]['trend']);
                            $t->set('comment', $data['method']['threats'][$tId]['comment']);
                            $t->set('qualification', $data['method']['threats'][$tId]['qualification']);
                            $this->threatTable->saveEntity($t, false);
                        }
                        $this->threatTable->getDb()->flush();
                    }
                }
            }
        }

        /*
         * Import the referentials.
         */
        if (isset($data['referentials'])) {
            foreach ($data['referentials'] as $referentialUUID => $referential_array) {
                // check if the referential is not already present in the analysis
                // TODO: findByAnrAndUuid
                $referentials = $this->referentialTable
                    ->getEntityByFields(['anr' => $anr->getId(), 'uuid' => $referentialUUID]);
                if (empty($referentials)) {
                    $newReferential = new Referential($referential_array);
                    $newReferential->setAnr($anr);
                    // TODO: saveEntity
                    $this->referentialTable->saveEntity($newReferential, false);
                }
            }
        }

        /*
         * Import the soa categories.
         */
        if (isset($data['soacategories'])) {
            foreach ($data['soacategories'] as $soaCategory) {
                // load the referential linked to the soacategory
                // TODO: findByAnrAndUuid
                $referentials = $this->referentialTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'uuid' => $soaCategory['referential']
                ]);
                if (!empty($referentials)) {
                    $categories = $this->soaCategoryTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        $labelKey => $soaCategory[$labelKey],
                        'referential' => [
                            'anr' => $anr->getId(),
                            'uuid' => $referentials[0]->getUuid(),
                        ]
                    ]);
                    if (empty($categories)) {
                        $newSoaCategory = new SoaCategory($soaCategory);
                        $newSoaCategory->setAnr($anr);
                        $newSoaCategory->setReferential($referentials[0]);
                        // TODO: saveEntity
                        $this->soaCategoryTable->save($newSoaCategory, false);
                    }
                }
            }
            $this->soaCategoryTable->getDb()->flush();
        }

        /*
         * Import the measures.
         */
        $measuresNewIds = [];
        if (isset($data['measures'])) {
            foreach ($data['measures'] as $measureUuid => $measure_array) {
                // check if the measure is not already in the analysis
                // TODO: findByAnrAndUuid
                $measures = $this->measureTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'uuid' => $measureUuid
                ]);
                if (empty($measures)) {
                    // load the referential linked to the measure
                    // TODO: findByAnrAndUuid
                    $referentials = $this->referentialTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'uuid' => $measure_array['referential']
                    ]);
                    $soaCategories = $this->soaCategoryTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        $labelKey => $measure_array['category']
                    ]);
                    if (!empty($referentials) && !empty($soaCategories)) {
                        // a measure must be linked to a referential and a category
                        $newMeasure = new Measure($measure_array);
                        $newMeasure->setAnr($anr);
                        $newMeasure->setReferential($referentials[0]);
                        $newMeasure->setCategory($soaCategories[0]);
                        $newMeasure->setAmvs(new ArrayCollection()); // need to initialize the amvs link
                        $newMeasure->setRolfRisks(new ArrayCollection());
                        $this->measureTable->saveEntity($newMeasure, false);
                        $measuresNewIds[$measureUuid] = $newMeasure;

                        if (!isset($data['soas'])) {
                            // if no SOAs in the analysis to import, create new ones
                            $newSoa = (new Soa())
                                ->setAnr($anr);
                            // TODO: return $this in setMeasure and join with previous chain calls.
                            $newSoa->setMeasure($newMeasure);
                            $this->soaTable->saveEntity($newSoa, false);
                        }
                    }
                }
            }

            $this->measureTable->getDb()->flush();
        }
        // import the measuresmeasures
        if (isset($data['measuresMeasures'])) {
            foreach ($data['measuresMeasures'] as $measureMeasure) {
                // check if the measuremeasure is not already in the analysis
                // TODO: findByAnrFatherAndChild(), but before get father/child them from previously saved or find in the db
                $measuresMeasures = $this->measureMeasureTable
                    ->getEntityByFields(['anr' => $anr->getId(),
                                         'father' => $measureMeasure['father'],
                                         'child' => $measureMeasure['child']]);
                if (empty($measuresMeasures)) {
                    // TODO: change the part with use object setters ->setFather() ->setChild()
                    $newMeasureMeasure = (new MeasureMeasure($measuresMeasures))
                        ->setAnr($anr);
                    // TODO: saveEntity
                    $this->measureMeasureTable->save($newMeasureMeasure, false);
                }
            }
            $this->measureMeasureTable->getDb()->flush();
        }

        // import the SOAs
        if (isset($data['soas'])) {
            // TODO: findByAnr and replace the map as it won't work.
            $measuresStoredId = $this->measureTable->fetchAllFiltered(['uuid'], 1, 0, null, null, ['anr' => $anr->getId()], null, null);
            $measuresStoredId = array_map(function ($elt) {
                return (string)$elt['uuid'];
            }, $measuresStoredId);
            foreach ($data['soas'] as $soa) {
                // check if the corresponding measure has been created during this import.
                if (array_key_exists($soa['measure_id'], $measuresNewIds)) {
                    $newSoa = (new Soa($soa))
                        ->setAnr($anr);
                    // TODO: return $this from setMeasure and join this with chain calls.
                    $newSoa->setMeasure($measuresNewIds[$soa['measure_id']]);
                    $this->soaTable->saveEntity($newSoa, false);
                } elseif (in_array($soa['measure_id'], $measuresStoredId)) { //measure exist so soa exist (normally)
                    // TODO: findByMeasure or find a measure then $measure->getSoa() if possible
                    $existedSoa = $this->soaTable->getEntityByFields([
                        'measure' => [
                            'anr' => $anr->getId(),
                            'uuid' => $soa['measure_id']
                        ]
                    ]);
                    if (empty($existedSoa)) {
                        $newSoa = (new Soa($soa))
                            ->setAnr($anr);
                        // TODO: join setMeasure with prev chain calls, $measureTable->findByAnrAndUuid
                        $newSoa->setMeasure($this->measureTable->getEntity([
                            'anr' => $anr->getId(),
                            'uuid' => $soa['measure_id']
                        ]));
                        $this->soaTable->saveEntity($newSoa, false);
                    } else {
                        $existedSoa = $existedSoa[0];
                        $existedSoa->remarks = $soa['remarks'];
                        $existedSoa->evidences = $soa['evidences'];
                        $existedSoa->actions = $soa['actions'];
                        $existedSoa->compliance = $soa['compliance'];
                        $existedSoa->EX = $soa['EX'];
                        $existedSoa->LR = $soa['LR'];
                        $existedSoa->CO = $soa['CO'];
                        $existedSoa->BR = $soa['BR'];
                        $existedSoa->BP = $soa['BP'];
                        $existedSoa->RRA = $soa['RRA'];
                        $this->soaTable->saveEntity($existedSoa, false);
                    }
                }
            }

            $this->soaTable->getDb()->flush();
        }

        // import the GDPR records
        if (!empty($data['records'])) { //Data of records
            foreach ($data['records'] as $v) {
                $this->anrRecordService->importFromArray($v, $anr->getId());
            }
        }

        /*
         * Import scales.
         */
        if (!empty($data['scales'])) {
            //Approximate values from destination analyse
            $ts = ['c', 'i', 'd'];
            /** @var InstanceSuperClass[] $instances */
            $instances = $this->instanceTable->findByAnrId($anr->getId());
            $consequences = $this->instanceConsequenceTable->findByAnr($anr);
            $scalesOrig = [];
            $scales = $this->scaleTable->findByAnr($anr);
            foreach ($scales as $sc) {
                $scalesOrig[$sc->get('type')]['min'] = $sc->get('min');
                $scalesOrig[$sc->get('type')]['max'] = $sc->get('max');
            }

            $minScaleImpOrig = $scalesOrig[Scale::TYPE_IMPACT]['min'];
            $maxScaleImpOrig = $scalesOrig[Scale::TYPE_IMPACT]['max'];
            $minScaleImpDest = $data['scales'][Scale::TYPE_IMPACT]['min'];
            $maxScaleImpDest = $data['scales'][Scale::TYPE_IMPACT]['max'];

            //Instances
            foreach ($ts as $t) {
                foreach ($instances as $instance) {
                    if ($instance->get($t . 'h')) {
                        $instance->set($t . 'h', 1);
                        $instance->set($t, -1);
                    } else {
                        $instance->set($t . 'h', 0);
                        $instance->set($t, $this->approximate(
                            $instance->get($t),
                            $minScaleImpOrig,
                            $maxScaleImpOrig,
                            $minScaleImpDest,
                            $maxScaleImpDest
                        ));
                    }

                    $this->anrInstanceService->refreshImpactsInherited($instance);
                }
                //Impacts & Consequences
                foreach ($consequences as $conseq) {
                    $conseq->set($t, $conseq->isHidden ? -1 : $this->approximate(
                        $conseq->get($t),
                        $minScaleImpOrig,
                        $maxScaleImpOrig,
                        $minScaleImpDest,
                        $maxScaleImpDest
                    ));
                    $this->instanceConsequenceTable->saveEntity($conseq, false);
                }
            }

            // Threat Qualification
            $threats = $this->threatTable->findByAnr($anr);
            foreach ($threats as $threat) {
                $threat->set('qualification', $this->approximate(
                    $threat->getQualification(),
                    $scalesOrig[Scale::TYPE_THREAT]['min'],
                    $scalesOrig[Scale::TYPE_THREAT]['max'],
                    $data['scales'][Scale::TYPE_THREAT]['min'],
                    $data['scales'][Scale::TYPE_THREAT]['max']
                ));
                $this->threatTable->saveEntity($threat, false);
            }

            // Information Risks
            $risks = $this->instanceRiskTable->findByAnr($anr);
            foreach ($risks as $r) {
                $r->set('threatRate', $this->approximate(
                    $r->getThreatRate(),
                    $scalesOrig[Scale::TYPE_THREAT]['min'],
                    $scalesOrig[Scale::TYPE_THREAT]['max'],
                    $data['scales'][Scale::TYPE_THREAT]['min'],
                    $data['scales'][Scale::TYPE_THREAT]['max']
                ));
                $oldVulRate = $r->getVulnerabilityRate();
                $r->set('vulnerabilityRate', $this->approximate(
                    $r->getVulnerabilityRate(),
                    $scalesOrig[Scale::TYPE_VULNERABILITY]['min'],
                    $scalesOrig[Scale::TYPE_VULNERABILITY]['max'],
                    $data['scales'][Scale::TYPE_VULNERABILITY]['min'],
                    $data['scales'][Scale::TYPE_VULNERABILITY]['max']
                ));
                $newVulRate = $r->getVulnerabilityRate();
                $r->set(
                    'reductionAmount',
                    $r->getReductionAmount() !== 0
                        ? $this->approximate($r->getReductionAmount(), 0, $oldVulRate, 0, $newVulRate, 0)
                        : 0
                );

                //TODO: find a faster way of updating risks.
                $this->anrInstanceRiskService->update($r->getId(), $risks);
            }

            //Operational Risks
            $risksOp = $this->instanceRiskOpTable->findByAnr($anr);
            if (!empty($risksOp)) {
                foreach ($risksOp as $rOp) {
                    $toApproximate = [
                        Scale::TYPE_THREAT => [
                            'netProb',
                            'targetedProb',
                            'brutProb',
                        ],
                        Scale::TYPE_IMPACT => [
                            'netR',
                            'netO',
                            'netL',
                            'netF',
                            'netP',
                            'brutR',
                            'brutO',
                            'brutL',
                            'brutF',
                            'brutP',
                            'targetedR',
                            'targetedO',
                            'targetedL',
                            'targetedF',
                            'targetedP',
                        ],
                    ];
                    foreach ($toApproximate as $type => $list) {
                        foreach ($list as $i) {
                            $rOp->set($i, $this->approximate(
                                $rOp->get($i),
                                $scalesOrig[$type]['min'],
                                $scalesOrig[$type]['max'],
                                $data['scales'][$type]['min'],
                                $data['scales'][$type]['max']
                            ));
                        }
                    }
                    $this->anrInstanceRiskOpService->update($rOp->getId(), $risksOp);
                }
            }

            // Finally update scales from import
            $scales = $this->scaleTable->findByAnr($anr);
            $types = [
                Scale::TYPE_IMPACT,
                Scale::TYPE_THREAT,
                Scale::TYPE_VULNERABILITY,
            ];
            foreach ($types as $type) {
                foreach ($scales as $s) {
                    if ($s->getType() === $type) {
                        // TODO: use setters.
                        $s->min = $data['scales'][$type]['min'];
                        $s->max = $data['scales'][$type]['max'];

                        $this->scaleTable->saveEntity($s, false);
                    }
                }
            }
        }

        $first = true;
        $instanceIds = [];
        $nbScaleImpactTypes = \count($this->scaleImpactTypeTable->findByAnr($anr));
        usort($data['instances'], function ($a, $b) {
            return $a['instance']['position'] <=> $b['instance']['position'];
        });
        foreach ($data['instances'] as $inst) {
            if ($first) {
                if ($data['with_eval'] && isset($data['scales'])) {
                    $inst['with_eval'] = $data['with_eval'];
                    $inst['scales'] = $data['scales'];
                }
                $first = false;
            }
            $instanceId = $this->importInstanceFromArray($inst, $anr, $parentInstance, $modeImport);
            if ($instanceId !== false) {
                $instanceIds[] = $instanceId;
            }
        }
        if (!empty($data['scalesComments'])) { // Scales comments
            $pos = 1;
            $siId = null;
            $scIds = null;
            $sId = null;

            foreach ($data['scalesComments'] as $sc) {
                $scIds[$pos] = $sc['id'];
                $pos++;
            }
            // TODO: findBy...
            $scaleComment = $this->scaleCommentTable->getEntityByFields(
                ['anr' => $anr->getId()]
            );
            foreach ($scaleComment as $sc) {
                if ($sc->scaleImpactType === null || $sc->scaleImpactType->isSys === 1) {
                    $this->scaleCommentTable->delete($sc->id);
                }
            }
            $nbComment = count($data['scalesComments']);

            for ($pos = 1; $pos <= $nbComment; $pos++) {
                $scale = $this->scaleTable->findByAnrAndType(
                    $anr,
                    $data['scalesComments'][$scIds[$pos]]['scale']['type']
                );
                $OrigPosition = $data['scalesComments'][$scIds[$pos]]['scaleImpactType']['position'] ?? 0;
                $position = ($OrigPosition > 8) ? $OrigPosition + ($nbScaleImpactTypes - 8) : $OrigPosition;

                // TODO: findBy...
                $scaleImpactType = $this->scaleImpactTypeTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'position' => $position
                ]);
                foreach ($scaleImpactType as $si) {
                    $siId = $si->getId();
                }
                $toExchange = $data['scalesComments'][$scIds[$pos]];
                $toExchange['anr'] = $anr->getId();
                $toExchange['scale'] = $scale->getId();
                $toExchange['scaleImpactType'] = $siId;
                // TODO: create it here.
                $this->anrScaleCommentService->create($toExchange);
            }
        }

        //Add user consequences to all instances
        $instances = $this->instanceTable->findByAnrId($anr->getId());
        $scaleImpactTypes = $this->scaleImpactTypeTable->findByAnr($anr);
        foreach ($instances as $instance) {
            foreach ($scaleImpactTypes as $siType) {
                $instanceConsequence = $this->instanceConsequenceTable->getEntityByFields([
                    'anr' => $anr->getId(),
                    'instance' => $instance->getId(),
                    'scaleImpactType' => $siType->getId()
                ]);
                if (empty($instanceConsequence)) {
                    $consequence = (new InstanceConsequence())
                        ->setAnr($anr)
                        ->setInstance($instance)
                        ->setObject($instance->getObject())
                        ->setScaleImpactType($siType);

                    $this->instanceConsequenceTable->saveEntity($consequence, false);
                }
            }
        }

        $this->instanceConsequenceTable->getDb()->flush();

        return $instanceIds;
    }

    /**
     * Method to approximate the value within new bounds, typically when the exported object had a min/max bound
     * bigger than the target's ANR bounds.
     *
     * @param int $x The value to approximate
     * @param int $minorig The source min bound
     * @param int $maxorig The source max bound
     * @param int $mindest The target min bound
     * @param int $maxdest The target max bound
     *
     * @return int|mixed The approximated value
     */
    private function approximate($x, $minorig, $maxorig, $mindest, $maxdest, $defaultvalue = -1)
    {
        if ($x == $maxorig) {
            return $maxdest;
        } elseif ($x != -1 && ($maxorig - $minorig) != -1) {
            return min(max(round(($x / ($maxorig - $minorig + 1)) * ($maxdest - $mindest + 1)), $mindest), $maxdest);
        }

        return $defaultvalue;
    }

    private function getMonarcVersion(): ?string
    {
        return $this->monarcVersion;
    }

    private function isMonarcVersionLoverThen(string $version): bool
    {
        return version_compare($this->monarcVersion, $version) < 0;
    }

    private function createSetOfRecommendations(array $data, Anr $anr): void
    {
        if (!empty($data['recSets'])) {
            foreach ($data['recSets'] as $recSetUuid => $recommendationSetData) {
                if (!isset($this->cachedData['recSets'][$recSetUuid])) {
                    try {
                        $recommendationsSet = $this->recommendationSetTable->findByAnrAndUuid($anr, $recSetUuid);
                    } catch (EntityNotFoundException $e) {
                        $recommendationsSet = (new RecommandationSet())
                            ->setUuid($recSetUuid)
                            ->setAnr($anr)
                            ->setLabel1($recommendationSetData['label1'])
                            ->setLabel2($recommendationSetData['label2'])
                            ->setLabel3($recommendationSetData['label3'])
                            ->setLabel4($recommendationSetData['label4']);

                        $this->recommendationSetTable->saveEntity($recommendationsSet, false);
                    }

                    $this->cachedData['recSets'][$recSetUuid] = $recommendationsSet;
                }
            }
            $this->recommendationSetTable->getDb()->flush();
        } elseif ($this->isMonarcVersionLoverThen('2.8.4')) {
            $recommendationsSets = $this->recommendationSetTable->getEntityByFields([
                'anr' => $anr->getId(),
                'label1' => 'Recommandations importées'
            ]);
            if (!empty($recommendationsSets)) {
                $recommendationSet = current($recommendationsSets);
            } else {
                $recommendationSet = (new RecommandationSet())
                    ->setAnr($anr)
                    ->setLabel1('Recommandations importées')
                    ->setLabel2('Imported recommendations')
                    ->setLabel3('Importierte empfehlungen')
                    ->setLabel4('Geïmporteerde aanbevelingen');

                $this->recommendationSetTable->saveEntity($recommendationSet);
            }

            $this->cachedData['recSets'][$recommendationSet->getUuid()] = $recommendationSet;
        }

        // Create recommendations not linked with recommendation risks.
        if (!empty($data['recs'])) {
            foreach ($data['recs'] as $recUuid => $recommendationData) {
                if (!isset($this->cachedData['recs'][$recUuid])) {
                    try {
                        $recommendation = $this->recommendationTable->findByAnrAndUuid($anr, $recUuid);
                    } catch (EntityNotFoundException $e) {
                        $recommendation = (new Recommandation())
                            ->setUuid($recommendationData['uuid'])
                            ->setAnr($anr)
                            ->setRecommandationSet(
                                $this->cachedData['recSets'][$recommendationData['recommandationSet']]
                            )
                            ->setComment($recommendationData['comment'])
                            ->setResponsable($recommendationData['responsable'])
                            ->setStatus($recommendationData['status'])
                            ->setImportance($recommendationData['importance'])
                            ->setCode($recommendationData['code'])
                            ->setDescription($recommendationData['description'])
                            ->setCounterTreated($recommendationData['counterTreated']);

                        if (!empty($recommendationData['duedate']['date'])) {
                            $recommendation->setDueDate(new DateTime($recommendationData['duedate']['date']));
                        }

                        $this->recommendationTable->saveEntity($recommendation, false);
                    }

                    $this->cachedData['recs'][$recUuid] = $recommendation;
                }
            }
            $this->recommendationTable->getDb()->flush();
        }
    }

    /**
     * @throws EntityNotFoundException
     * @throws NonUniqueResultException
     */
    private function processRecommendationDataLinkedToRisk(
        Anr $anr,
        array $recommendationData,
        bool $isRiskTreated
    ): Recommandation {
        if (isset($this->cachedData['recs'][$recommendationData['uuid']])) {
            /** @var Recommandation $recommendation */
            $recommendation = $this->cachedData['recs'][$recommendationData['uuid']];
            if ($isRiskTreated && $recommendation->isPositionEmpty()) {
                $recommendation->setPosition(++$this->currentAnalyseMaxRecommendationPosition);
                $this->recommendationTable->saveEntity($recommendation, false);
            }

            return $recommendation;
        }

        if (isset($this->cachedData['recSets'][$recommendationData['recommandationSet']])) {
            $recommendationSet = $this->cachedData['recSets'][$recommendationData['recommandationSet']];
        } else {

            $recommendationSet = $this->recommendationSetTable
                ->findByAnrAndUuid($anr, $recommendationData['recommandationSet']);

            $this->cachedData['recSets'][$recommendationSet->getUuid()] = $recommendationSet;
        }

        $recommendation = $this->recommendationTable->findByAnrCodeAndRecommendationSet(
            $anr,
            $recommendationData['code'],
            $recommendationSet
        );
        if ($recommendation === null) {
            $recommendation = (new Recommandation())->setUuid($recommendationData['uuid']);
        }

        $recommendation->setAnr($anr)
            ->setRecommandationSet($recommendationSet)
            ->setComment($recommendationData['comment'])
            ->setResponsable($recommendationData['responsable'])
            ->setStatus($recommendationData['status'])
            ->setImportance($recommendationData['importance'])
            ->setCode($recommendationData['code'])
            ->setDescription($recommendationData['description'])
            ->setCounterTreated($recommendationData['counterTreated']);
        if (!empty($recommendationData['duedate']['date'])) {
            $recommendation->setDueDate(new DateTime($recommendationData['duedate']['date']));
        }

        if ($isRiskTreated && $recommendation->isPositionEmpty()) {
            $recommendation->setPosition(++$this->currentAnalyseMaxRecommendationPosition);
        }

        $this->recommendationTable->saveEntity($recommendation, false);

        $this->cachedData['recs'][$recommendation->getUuid()] = $recommendation;

        return $recommendation;
    }

    /**
     * Check if the data can be imported in the anr.
     */
    private function isImportPossible(Anr $anr, ?InstanceSuperClass $parent): bool
    {
        return $parent === null
            || (
                $parent->getLevel() !== InstanceSuperClass::LEVEL_INTER
                && $parent->getAnr() === $anr
            );
    }

    private function prepareInstanceConsequences(
        array $data,
        Anr $anr,
        InstanceSuperClass $instance,
        MonarcObject $monarcObject,
        bool $includeEval
    ): void {
        $labelKey = 'label' . $anr->getLanguage();

        if (!$includeEval) {
            // TODO: improve the method.
            $this->anrInstanceService->createInstanceConsequences($instance->getId(), $anr->getId(), $monarcObject);

            return;
        }

        foreach (Instance::getAvailableScalesCriteria() as $scaleCriteria) {
            if ($instance->{'getInherited' . $scaleCriteria}()) {
                $instance->{'setInherited' . $scaleCriteria}(1);
                $instance->{'set' . $scaleCriteria}(-1);
            } else {
                $instance->{'setInherited' . $scaleCriteria}(0);
                $instance->{'set' . $scaleCriteria}(
                    $this->approximate(
                        $instance->get($scaleCriteria),
                        $this->cachedData['scales']['orig'][Scale::TYPE_IMPACT]['min'],
                        $this->cachedData['scales']['orig'][Scale::TYPE_IMPACT]['max'],
                        $this->cachedData['scales']['dest'][Scale::TYPE_IMPACT]['min'],
                        $this->cachedData['scales']['dest'][Scale::TYPE_IMPACT]['max']
                    )
                );
            }
        }

        if (!empty($data['consequences'])) {
            $localScaleImpact = $this->prepareScalesImpact($data, $anr, $includeEval);
            if ($localScaleImpact === null) {
                $localScaleImpact = $this->scaleTable->findByAnrAndType($anr, Scale::TYPE_IMPACT);
            }
            $scalesImpactTypes = $this->scalesImpactTypeTable->findByAnr($anr);
            $localScalesImpactTypes = [];
            foreach ($scalesImpactTypes as $scalesImpactType) {
                $localScalesImpactTypes[$scalesImpactType->getLabel($anr->getLanguage())] = $scalesImpactType;
            }
            $scaleImpactTypeMaxPosition = $this->scalesImpactTypeTable
                ->findMaxPositionByAnrAndScale($anr, $localScaleImpact);

            foreach ($data['consequences'] as $consequenceData) {
                if (!isset($localScalesImpactTypes[$consequenceData['scaleImpactType'][$labelKey]])) {
                    $scaleImpactTypeData = $consequenceData['scaleImpactType'];

                    $scaleImpactType = (new ScaleImpactType())
                        ->setType($scaleImpactTypeData['type'])
                        ->setLabels($scaleImpactTypeData)
                        ->setIsSys((bool)$scaleImpactTypeData['isSys'])
                        ->setIsHidden((bool)$scaleImpactTypeData['isHidden'])
                        ->setAnr($anr)
                        ->setScale($localScaleImpact)
                        ->setPosition(++$scaleImpactTypeMaxPosition);

                    $this->scalesImpactTypeTable->saveEntity($scaleImpactType, false);

                    $localScalesImpactTypes[$consequenceData['scaleImpactType'][$labelKey]] = $scaleImpactType;
                }

                $instanceConsequence = (new InstanceConsequence())
                    ->setAnr($anr)
                    ->setObject($monarcObject)
                    ->setInstance($instance)
                    ->setScaleImpactType($localScalesImpactTypes[$consequenceData['scaleImpactType'][$labelKey]])
                    ->setIsHidden((bool)$consequenceData['isHidden'])
                    ->setLocallyTouched($consequenceData['locallyTouched']);

                foreach (InstanceConsequence::getAvailableScalesCriteria() as $scaleCriteria) {
                    $instanceConsequence->{'set' . $scaleCriteria}(
                        $instanceConsequence->isHidden()
                            ? -1
                            : $this->approximate(
                                $consequenceData[$scaleCriteria],
                                $this->cachedData['scales']['orig'][Scale::TYPE_IMPACT]['min'],
                                $this->cachedData['scales']['orig'][Scale::TYPE_IMPACT]['max'],
                                $this->cachedData['scales']['dest'][Scale::TYPE_IMPACT]['min'],
                                $this->cachedData['scales']['dest'][Scale::TYPE_IMPACT]['max']
                            )
                    );
                }

                $this->instanceConsequenceTable->saveEntity($instanceConsequence, false);
            }

            $this->instanceConsequenceTable->getDb()->flush();
        }
    }

    private function prepareScalesImpact(array $data, Anr $anr, bool $includeEval): ?Scale
    {
        $localScaleImpact = null;
        if ($includeEval && !empty($data['scales'])) {
            $scales = $this->scaleTable->findByAnr($anr);
            $this->cachedData['scales']['dest'] = [];
            $this->cachedData['scales']['orig'] = $data['scales'];

            foreach ($scales as $scale) {
                if ($scale->getType() === Scale::TYPE_IMPACT) {
                    $localScaleImpact = $scale;
                }
                $this->cachedData['scales']['dest'][$scale->getType()] = [
                    'min' => $scale->getMin(),
                    'max' => $scale->getMax(),
                ];
            }
        }

        return $localScaleImpact;
    }

    private function processInstanceRisks(
        array $data,
        Anr $anr,
        InstanceSuperClass $instance,
        MonarcObject $monarcObject,
        bool $includeEval,
        string $modeImport
    ): void {
        if (empty($data['risks'])) {
            return;
        }

        $indexFiled = !$this->isMonarcVersionLoverThen('2.8.2') ? 'uuid' : 'code';
        /**
         * First we get the data from the cached data AssetImportService and compute the difference.
         */
        if (empty($this->cachedData['threats'])) {
            $this->cachedData['threats'] = $this->assetImportService->getCachedDataByKey('threats');
            $threatsUuids = array_diff_key(
                array_column($data['threats'], $indexFiled),
                $this->cachedData['threats']
            );
            if (!empty($threatsUuids)) {
                $this->cachedData['threats'] = $this->threatTable->findByAnrAndUuidsIndexedByField(
                    $anr,
                    $threatsUuids,
                    $indexFiled
                );
            }
        }
        if (empty($this->cachedData['vulnerabilities'])) {
            $this->cachedData['vulnerabilities'] = $this->assetImportService->getCachedDataByKey('vulnerabilities');
            $vulnerabilitiesUuids = array_diff_key(
                array_column($data['vuls'], $indexFiled),
                $this->cachedData['vulnerabilities']
            );
            if (!empty($vulnerabilitiesUuids)) {
                $this->cachedData['vulnerabilities'] = $this->vulnerabilityTable->findByAnrAndUuidsIndexedByField(
                    $anr,
                    $vulnerabilitiesUuids,
                    $indexFiled
                );
            }
        }

        foreach ($data['risks'] as $instanceRiskData) {
            if ((int)$instanceRiskData['specific'] === InstanceRisk::TYPE_SPECIFIC) {
                $threatData = $data['threats'][$instanceRiskData['threat']];
                $vulnerabilityData = $data['vuls'][$instanceRiskData['vulnerability']];
                $indexFiled = $this->isMonarcVersionLoverThen('2.8.2') ? 'code' : 'uuid';

                if (!isset($this->cachedData['threats'][$threatData[$indexFiled]])) {
                    $threat = (new Threat())
                        ->setAnr($anr)
                        ->setCode($threatData['code'])
                        ->setLabels($threatData)
                        ->setDescriptions($threatData)
                        ->setConfidentiality($threatData['c'])
                        ->setIntegrity($threatData['i'])
                        ->setAvailability($threatData['a'])
                        ->setMode($threatData['mode'])
                        ->setStatus($threatData['status'])
                        ->setTrend($threatData['trend'])
                        ->setQualification($threatData['qualification'])
                        ->setComment($threatData['comment']);
                    if (!$this->isMonarcVersionLoverThen('2.8.2')) {
                        $threat->setUuid($threatData['uuid']);
                    }

                    /*
                     * Unfortunately we don't add "themes" on the same level as "risks" and "threats", but only under "asset".
                     * TODO: we should add theme linked to the threat inside of the threat object data when export later on.
                     * after we can set it $threat->setTheme($theme);
                     */

                    $this->threatTable->saveEntity($threat, false);

                    $this->cachedData['threats'][$threatData[$indexFiled]] = $threat;
                }

                if (!isset($this->cachedData['ivuls'][$vulnerabilityData[$indexFiled]])) {
                    $vulnerability = (new Vulnerability())
                        ->setAnr($anr)
                        ->setLabels($vulnerabilityData)
                        ->setDescriptions($vulnerabilityData)
                        ->setCode($vulnerabilityData['code'])
                        ->setMode($vulnerabilityData['mode'])
                        ->setStatus($vulnerabilityData['status']);
                    if (!$this->isMonarcVersionLoverThen('2.8.2')) {
                        $vulnerability->setUuid($vulnerabilityData['uuid']);
                    }

                    $this->vulnerabilityTable->saveEntity($vulnerability, false);

                    $this->cachedData['vulnerabilities'][$vulnerabilityData[$indexFiled]] = $vulnerability;
                }

                $instanceRisk = $this->createInstanceRiskFromData(
                    $instanceRiskData,
                    $anr,
                    $instance,
                    $monarcObject->getAsset(),
                    $this->cachedData['threats'][$threatData[$indexFiled]],
                    $this->cachedData['vulnerabilities'][$vulnerabilityData[$indexFiled]]
                );

                $this->instanceRiskTable->saveEntity($instanceRisk, false);

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
                        $this->cachedData['threats'][$threatData[$indexFiled]],
                        $this->cachedData['vulnerabilities'][$vulnerabilityData[$indexFiled]]
                    );

                    $this->instanceRiskTable->saveEntity($instanceRiskBrother, false);
                }
            }

            $tuuid = Uuid::isValid($instanceRiskData['threat'])
                ? $instanceRiskData['threat']
                : $this->cachedData['ithreats'][$data['threats'][$instanceRiskData['threat']]['code']];
            $vuuid = Uuid::isValid($instanceRiskData['vulnerability'])
                ? $instanceRiskData['vulnerability']
                : $this->cachedData['ivuls'][$data['vuls'][$instanceRiskData['vulnerability']]['code']];

            /** @var InstanceRisk $instanceRisk */
            $instanceRisk = current($this->instanceRiskTable->getEntityByFields([
                'anr' => $anr->getId(),
                'instance' => $instance->getId(),
                'asset' => $monarcObject ? [
                    'anr' => $anr->getId(),
                    'uuid' => $monarcObject->getAsset()->getUuid(),
                ] : null,
                'threat' => ['anr' => $anr->getId(), 'uuid' => $tuuid],
                'vulnerability' => ['anr' => $anr->getId(), 'uuid' => $vuuid],
            ]));

            if ($instanceRisk !== null && $includeEval) {
                $instanceRisk->set('threatRate', $this->approximate(
                    $instanceRiskData['threatRate'],
                    $this->cachedData['scales']['orig'][Scale::TYPE_THREAT]['min'],
                    $this->cachedData['scales']['orig'][Scale::TYPE_THREAT]['max'],
                    $this->cachedData['scales']['dest'][Scale::TYPE_THREAT]['min'],
                    $this->cachedData['scales']['dest'][Scale::TYPE_THREAT]['max']
                ));
                $instanceRisk->set('vulnerabilityRate', $this->approximate(
                    $instanceRiskData['vulnerabilityRate'],
                    $this->cachedData['scales']['orig'][Scale::TYPE_VULNERABILITY]['min'],
                    $this->cachedData['scales']['orig'][Scale::TYPE_VULNERABILITY]['max'],
                    $this->cachedData['scales']['dest'][Scale::TYPE_VULNERABILITY]['min'],
                    $this->cachedData['scales']['dest'][Scale::TYPE_VULNERABILITY]['max']
                ));
                $instanceRisk->set('mh', $instanceRiskData['mh']);
                $instanceRisk->set('kindOfMeasure', $instanceRiskData['kindOfMeasure']);
                $instanceRisk->set('comment', $instanceRiskData['comment']);
                $instanceRisk->set('commentAfter', $instanceRiskData['commentAfter']);

                // La valeur -1 pour le reduction_amount n'a pas de sens, c'est 0 le minimum. Le -1 fausse
                // les calculs.
                // Cas particulier, faudrait pas mettre n'importe quoi dans cette colonne si on part d'une scale
                // 1 - 7 vers 1 - 3 on peut pas avoir une réduction de 4, 5, 6 ou 7
                $instanceRisk->set(
                    'reductionAmount',
                    $instanceRiskData['reductionAmount'] != -1
                        ? $this->approximate(
                        $instanceRiskData['reductionAmount'],
                        0,
                        $instanceRiskData['vulnerabilityRate'],
                        0,
                        $instanceRisk->getVulnerabilityRate(),
                        0)
                        : 0
                );
                $this->instanceRiskTable->saveEntity($instanceRisk, false);

                // Merge all fields for global assets.
                if ($modeImport === 'merge'
                    && !$instanceRisk->isSpecific()
                    && $instance->getObject()->isScopeGlobal()
                ) {
                    $objectIdsBrothers = $this->instanceTable->findByAnrAndObject($anr, $instance->getObject());

                    // TODO: check why do we take only a single one, use query instead of the generic method.
                    /** @var InstanceRisk $instanceRiskBrothers */
                    $instanceRiskBrothers = current($this->instanceRiskTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'instance' => ['op' => 'IN', 'value' => $objectIdsBrothers],
                        'amv' => [
                            'anr' => $anr->getId(),
                            'uuid' => $instanceRisk->getAmv()->getUuid(),
                        ]
                    ]));

                    if (!empty($instanceRiskBrothers)) {
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
                            $dataUpdate['comment'] = $instanceRiskBrothers->getComment() . "\n\n" . $instanceRisk->getComment(); // Merge comments
                        } else {
                            $dataUpdate['comment'] = $instanceRiskBrothers->getComment();
                        }

                        $this->anrInstanceRiskService->update($instanceRisk->getId(), $dataUpdate);
                    }
                }

                // Process recommendations.
                if (!empty($data['recos'][$instanceRiskData['id']])) {
                    foreach ($data['recos'][$instanceRiskData['id']] as $reco) {
                        $recommendation = $this->processRecommendationDataLinkedToRisk(
                            $anr,
                            $reco,
                            $instanceRiskData['kindOfMeasure'] !== InstanceRiskSuperClass::KIND_NOT_TREATED
                        );
                        $recommendationRisk = (new RecommandationRisk())
                            ->setAnr($anr)
                            ->setInstance($instance)
                            ->setInstanceRisk($instanceRisk)
                            ->setGlobalObject($monarcObject->isScopeGlobal() ? $monarcObject : null)
                            ->setAsset($instanceRisk->getAsset())
                            ->setThreat($instanceRisk->getThreat())
                            ->setVulnerability($instanceRisk->getVulnerability())
                            ->setCommentAfter((string)$reco['commentAfter'])
                            ->setRecommandation($recommendation);

                        $this->recommendationRiskTable->saveEntity($recommendationRisk, false);

                        // Replicate recommendation to brothers.
                        if ($modeImport === 'merge' && $recommendationRisk->hasGlobalObjectRelation()) {
                            $brotherInstances = $this->getInstanceBrothers($instance);
                            if (!empty($brotherInstances)) {
                                foreach ($brotherInstances as $brotherInstance) {
                                    // Get the risks of brothers
                                    /** @var InstanceRisk[] $brothers */
                                    if ($instanceRisk->isSpecific()) {
                                        $brothers = $this->recommendationRiskTable->getEntityByFields([
                                            'anr' => $anr->getId(),
                                            'specific' => InstanceRisk::TYPE_SPECIFIC,
                                            'instance' => $brotherInstance->getId(),
                                            'threat' => [
                                                'anr' => $anr->getId(),
                                                'uuid' => $instanceRisk->getThreat()->getUuid()
                                            ],
                                            'vulnerability' => [
                                                'anr' => $anr->getId(),
                                                'uuid' => $instanceRisk->getVulnerability()->getUuid()
                                            ]
                                        ]);
                                    } else {
                                        $brothers = $this->recommendationRiskTable->getEntityByFields([
                                            'anr' => $anr->getId(),
                                            'instance' => $brotherInstance->getId(),
                                            'amv' => [
                                                'anr' => $anr->getId(),
                                                'uuid' => $instanceRisk->getAmv()->getUuid()
                                            ]
                                        ]);
                                    }

                                    foreach ($brothers as $brother) {
                                        $recommendationRiskBrother = $this->recommendationRiskTable
                                            ->findByInstanceRiskAndRecommendation($brother, $recommendation);
                                        if ($recommendationRiskBrother === null) {
                                            $recommendationRiskBrother = (new RecommandationRisk())
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
                                                ->setRecommandation($recommendation);

                                            $this->recommendationRiskTable->saveEntity($recommendationRiskBrother, false);
                                        }
                                    }
                                }
                                $this->recommendationRiskTable->getDb()->flush();
                            }
                        }
                    }
                    $this->recommendationRiskTable->getDb()->flush();
                }
            }

            // Check recommendations from a brother
            /** @var Instance $instanceBrother */
            $instanceBrother = current($this->instanceTable->getEntityByFields([
                'id' => ['op' => '!=', 'value' => $instance->getId()],
                'anr' => $anr->getId(),
                'asset' => ['anr' => $anr->getId(), 'uuid' => $monarcObject->getAsset()->getUuid()],
                'object' => ['anr' => $anr->getId(), 'uuid' => $monarcObject->getUuid()]
            ]));

            if ($instanceBrother !== null && $instanceRisk !== null && !$instanceRisk->isSpecific()) {
                $instanceRiskBrothers = $this->instanceRiskTable->findByInstanceAndAmv(
                    $instanceBrother,
                    $instanceRisk->getAmv()
                );

                foreach ($instanceRiskBrothers as $instanceRiskBrother) {
                    /** @var RecommandationRisk[] $brotherRecoRisks */
                    // Get recommendation of brother
                    $brotherRecoRisks = $this->recommendationRiskTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        'instanceRisk' => $instanceRiskBrother->getId(),
                        'instance' => ['op' => '!=', 'value' => $instance->getId()],
                        'globalObject' => [
                            'anr' => $anr->getId(),
                            'uuid' => $monarcObject->getUuid(),
                        ]
                    ]);

                    if (!empty($brotherRecoRisks)) {
                        foreach ($brotherRecoRisks as $brotherRecoRisk) {
                            $recommendationRisk = $this->recommendationRiskTable->findByInstanceRiskAndRecommendation(
                                $instanceRisk,
                                $brotherRecoRisk->getRecommandation()
                            );

                            if ($recommendationRisk === null) {
                                $recommendationRisk = (new RecommandationRisk())
                                    ->setAnr($anr)
                                    ->setInstance($instance)
                                    ->setInstanceRisk($brotherRecoRisk->getInstanceRisk())
                                    ->setGlobalObject($brotherRecoRisk->getGlobalObject())
                                    ->setAsset($brotherRecoRisk->getAsset())
                                    ->setThreat($brotherRecoRisk->getThreat())
                                    ->setVulnerability($brotherRecoRisk->getVulnerability())
                                    ->setCommentAfter($brotherRecoRisk->getCommentAfter())
                                    ->setRecommandation($brotherRecoRisk->getRecommandation());

                                $this->recommendationRiskTable->saveEntity($recommendationRisk, false);
                            }
                        }

                        $this->recommendationRiskTable->getDb()->flush();
                    }
                }
            }
        }

        // Check recommandations from specific risk of brothers
        $recoToCreate = [];
        // Get all specific risks of instance
        // TODO: replace all the queries with QueryBuilder. Review the logic.
        /** @var InstanceRisk[] $specificRisks */
        $specificRisks = $this->instanceRiskTable->getEntityByFields([
            'anr' => $anr->getId(),
            'instance' => $instance->getId(),
            'specific' => 1,
        ]);
        foreach ($specificRisks as $specificRisk) {
            // Get recommandations of brothers
            /** @var RecommandationRisk[] $exitingRecoRisks */
            $exitingRecoRisks = $this->recommendationRiskTable->getEntityByFields([
                'anr' => $anr->getId(),
                'asset' => ['anr' => $anr->getId(), 'uuid' => $specificRisk->getAsset()->getUuid()],
                'threat' => ['anr' => $anr->getId(), 'uuid' => $specificRisk->getThreat()->getUuid()],
                'vulnerability' => ['anr' => $anr->getId(), 'uuid' => $specificRisk->getVulnerability()->getUuid()],
            ]);
            foreach ($exitingRecoRisks as $exitingRecoRisk) {
                if ($instance->getId() !== $exitingRecoRisk->getInstance()->getId()) {
                    $recoToCreate[] = $exitingRecoRisk;
                }
            }
        }

        /** @var RecommandationRisk $recommendationRiskToCreate */
        foreach ($recoToCreate as $recommendationRiskToCreate) {
            // Check if reco-risk link exist
            $recoCreated = $this->recommendationRiskTable->getEntityByFields([
                'recommandation' => [
                    'anr' => $anr->getId(),
                    'uuid' => $recommendationRiskToCreate->getRecommandation()->getUuid(),
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
                ]
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

                $recommendationRisk = (new RecommandationRisk())
                    ->setAnr($anr)
                    ->setInstance($instance)
                    ->setInstanceRisk($instanceRiskSpecific)
                    ->setGlobalObject($recommendationRiskToCreate->getGlobalObject())
                    ->setAsset($recommendationRiskToCreate->getAsset())
                    ->setThreat($recommendationRiskToCreate->getThreat())
                    ->setVulnerability($recommendationRiskToCreate->getVulnerability())
                    ->setCommentAfter($recommendationRiskToCreate->getCommentAfter())
                    ->setRecommandation($recommendationRiskToCreate->getRecommandation());

                $this->recommendationRiskTable->saveEntity($recommendationRisk, false);
            }
        }
        $this->recommendationRiskTable->getDb()->flush();

        // on met finalement à jour les risques en cascade
        $this->anrInstanceService->updateRisks($instance);
    }

    /**
     * Update the instance impacts from brothers for global assets.
     *
     * @param InstanceSuperClass $instance
     * @param string $modeImport
     */
    private function updateInstanceImpactsFromBrothers(InstanceSuperClass $instance, string $modeImport): void
    {
        if ($modeImport === 'merge' && $instance->getObject()->isScopeGlobal()) {
            $instanceBrothers = $this->getInstanceBrothers($instance);
            if (!empty($instanceBrothers)) {
                // Update impacts of the instance. We use only one brother global instance as the impacts are the same.
                $instanceBrother = current($instanceBrothers);
                foreach (InstanceConsequence::getAvailableScalesCriteria() as $scaleCriteria) {
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
                            $instanceConsequenceBrother->setIsHidden($instanceConsequence->getIsHidden())
                                ->setLocallyTouched($instanceConsequence->getLocallyTouched())
                                ->setConfidentiality($instanceConsequence->getConfidentiality())
                                ->setIntegrity($instanceConsequence->getIntegrity())
                                ->setAvailability($instanceConsequence->getAvailability());

                            $this->instanceConsequenceTable->saveEntity($instanceConsequenceBrother, false);
                        }
                    }
                }

                $this->instanceTable->saveEntity($instance);
            }
        }
    }

    /**
     * @return Instance[]
     */
    private function getInstanceBrothers(InstanceSuperClass $instance): array
    {
        if (!isset($this->cachedData['instanceBrothers'])) {
            $this->cachedData['instanceBrothers'] = $this->instanceTable->findByAnrAssetAndObjectExcludeInstance(
                $instance->getAnr(),
                $instance->getAsset(),
                $instance->getObject(),
                $instance
            );
        }

        return $this->cachedData['instanceBrothers'];
    }

    private function createInstanceRiskFromData(
        array $instanceRiskData,
        Anr $anr,
        Instance $instance,
        AssetSuperClass $asset,
        Threat $threat,
        Vulnerability $vulnerability
    ): InstanceRiskSuperClass {
        return (new InstanceRisk())
            ->setAnr($anr)
            ->setInstance($instance)
            ->setAsset($asset)
            ->setThreat($threat)
            ->setVulnerability($vulnerability)
            ->setSpecific($instanceRiskData['specific'])
            ->setMh($instanceRiskData['mh'])
            ->setThreatRate($instanceRiskData['threatRate'])
            ->setVulnerabilityRate($instanceRiskData['vulnerabilityRate'])
            ->setKindOfMeasure($instanceRiskData['kindOfMeasure'])
            ->setReductionAmount($instanceRiskData['reductionAmount'])
            ->setComment($instanceRiskData['comment'])
            ->setCommentafter($instanceRiskData['commentAfter'])
            ->setCacheMaxRisk($instanceRiskData['cacheMaxRisk'])
            ->setCacheTargetedRisk($instanceRiskData['cacheTargetedRisk'])
            ->setRiskConfidentiality($instanceRiskData['riskC'])
            ->setRiskIntegrity($instanceRiskData['riskI'])
            ->setRiskAvailability($instanceRiskData['riskD']);
    }
}
