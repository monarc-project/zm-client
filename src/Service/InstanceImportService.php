<?php declare(strict_types=1);
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
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\AssetSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
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
use Monarc\FrontOffice\Model\Entity\OperationalInstanceRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleType;
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
use Monarc\FrontOffice\Model\Entity\Translation;
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
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTypeTable;
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
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Monarc\FrontOffice\Model\Table\VulnerabilityTable;

class InstanceImportService
{
    use EncryptDecryptHelperTrait;

    private string $monarcVersion;

    private array $cachedData = [];

    private int $currentAnalyseMaxRecommendationPosition;

    private int $initialAnalyseMaxRecommendationPosition;

    private int $currentMaxInstancePosition;

    private AnrInstanceRiskService $anrInstanceRiskService;

    private AnrInstanceService $anrInstanceService;

    private AssetImportService $assetImportService;

    private AnrRecordService $anrRecordService;

    private AnrInstanceRiskOpService $anrInstanceRiskOpService;

    private InstanceTable $instanceTable;

    private AnrTable $anrTable;

    private RecommandationTable $recommendationTable;

    private InstanceConsequenceTable $instanceConsequenceTable;

    private ScaleTable $scaleTable;

    private ScaleImpactTypeTable $scalesImpactTypeTable;

    private ThreatTable $threatTable;

    private VulnerabilityTable $vulnerabilityTable;

    private RecommandationSetTable $recommendationSetTable;

    private InstanceRiskTable $instanceRiskTable;

    private InstanceRiskOpTable $instanceRiskOpTable;

    private RecommandationRiskTable $recommendationRiskTable;

    private QuestionTable $questionTable;

    private QuestionChoiceTable $questionChoiceTable;

    private SoaTable $soaTable;

    private MeasureTable $measureTable;

    private MeasureMeasureTable $measureMeasureTable;

    private ThemeTable $themeTable;

    private ReferentialTable $referentialTable;

    private SoaCategoryTable $soaCategoryTable;

    private ObjectImportService $objectImportService;

    private InterviewTable $interviewTable;

    private DeliveryTable $deliveryTable;

    private ScaleImpactTypeTable $scaleImpactTypeTable;

    private ScaleCommentTable $scaleCommentTable;

    private OperationalRiskScaleTable $operationalRiskScaleTable;

    private OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable;

    private UserSuperClass $connectedUser;

    private TranslationTable $translationTable;

    private ConfigService $configService;

    private OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable;

    private AnrScaleCommentService $anrScaleCommentService;

    private OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable;

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
        OperationalRiskScaleTable $operationalRiskScaleTable,
        OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable,
        OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        ConnectedUserService $connectedUserService,
        TranslationTable $translationTable,
        ConfigService $configService,
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
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;
        $this->operationalInstanceRiskScaleTable = $operationalInstanceRiskScaleTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->operationalRiskScaleTypeTable = $operationalRiskScaleTypeTable;
        $this->translationTable = $translationTable;
        $this->configService = $configService;
        $this->operationalRiskScaleCommentTable = $operationalRiskScaleCommentTable;
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

        $this->setAndValidateMonarcVersion($data);

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
        $monarcObject = $this->objectImportService->importFromArray($data['object'], $anr, $modeImport);
        if ($monarcObject === null) {
            return false;
        }

        $instance = $this->createInstance($data, $anr, $parentInstance, $monarcObject);

        $includeEval = !empty($data['with_eval']);

        $localScaleImpact = $this->prepareScalesImpact($data, $anr, $includeEval);

        $this->anrInstanceRiskService->createInstanceRisks($instance, $anr, $monarcObject);

        $this->prepareInstanceConsequences($data, $anr, $instance, $monarcObject, $localScaleImpact, $includeEval);

        $this->updateInstanceImpactsFromBrothers($instance, $modeImport);

        $this->anrInstanceService->refreshImpactsInherited($instance);

        $this->createSetOfRecommendations($data, $anr);

        $this->processInstanceRisks($data, $anr, $instance, $monarcObject, $includeEval, $modeImport);

        $this->processInstanceOperationalRisks($data, $anr, $instance, $monarcObject, $includeEval);

        if (!empty($data['children'])) {
            usort($data['children'], function ($a, $b) {
                return $a['instance']['position'] <=> $b['instance']['position'];
            });
            foreach ($data['children'] as $child) {
                $this->importInstanceFromArray($child, $anr, $instance, $modeImport);
            }
            $this->anrInstanceService->updateChildrenImpacts($instance);
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
                foreach ($data['method']['threats'] as $threatUuid => $threatData) {
                    $threat = $this->cachedData['threats'][$threatUuid] ?? null;
                    if ($threat === null) {
                        try {
                            $threat = $this->threatTable->findByAnrAndUuid($anr, $threatUuid);
                        } catch (EntityNotFoundException $e) {
                            $threatData = $data['method']['threats'][$threatUuid];
                            $threat = (new Threat())
                                ->setUuid($threatData['uuid'])
                                ->setAnr($anr)
                                ->setCode($threatData['code'])
                                ->setLabels($threatData)
                                ->setDescriptions($threatData);
                            if (isset($threatData['c'])) {
                                $threat->setConfidentiality((int)$threatData['c']);
                            }
                            if (isset($threatData['i'])) {
                                $threat->setIntegrity((int)$threatData['i']);
                            }
                            if (isset($threatData['a'])) {
                                $threat->setAvailability((int)$threatData['a']);
                            }

                            if (!empty($data['method']['threats'][$threatUuid]['theme'])) {
                                $labelValue = $data['method']['threats'][$threatUuid]['theme'][$labelKey];
                                if (!isset($this->cachedData['themes'][$labelValue])) {
                                    $theme = $this->themeTable->findByAnrIdAndLabel(
                                        $anr->getId(),
                                        $labelKey,
                                        $labelValue
                                    );
                                    if ($theme === null) {
                                        $themeData = $data['method']['threats'][$threatUuid]['theme'];
                                        $theme = (new Theme())
                                            ->setAnr($anr)
                                            ->setLabels($themeData);
                                        $this->themeTable->saveEntity($theme, false);
                                    }
                                    $this->cachedData['themes'][$labelValue] = $theme;
                                }

                                $threat->setTheme($this->cachedData['themes'][$labelValue]);
                            }

                            $this->cachedData['threats'][$threatUuid] = $threat;
                        }
                    }

                    $threat->setTrend((int)$data['method']['threats'][$threatUuid]['trend']);
                    $threat->setComment((string)$data['method']['threats'][$threatUuid]['comment']);
                    $threat->setQualification((int)$data['method']['threats'][$threatUuid]['qualification']);

                    $this->threatTable->saveEntity($threat);
                }
            }
        }

        /*
         * Import the referentials.
         */
        if (isset($data['referentials'])) {
            foreach ($data['referentials'] as $referentialUuid => $referentialData) {
                $referential = $this->referentialTable->findByAnrAndUuid($anr, $referentialUuid);
                if ($referential === null) {
                    $referential = (new Referential($referentialData))->setAnr($anr);
                    $this->referentialTable->saveEntity($referential, false);
                }
                $this->cachedData['referential'][$referentialUuid] = $referential;
            }

            $this->referentialTable->getDb()->flush();
        }

        /*
         * Import the soa categories.
         */
        if (isset($data['soacategories'])) {
            foreach ($data['soacategories'] as $soaCategory) {
                if (isset($this->cachedData['referential'][$soaCategory['referential']])) {
                    $referential = $this->cachedData['referential'][$soaCategory['referential']];
                    $categories = $this->soaCategoryTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        $labelKey => $soaCategory[$labelKey],
                        'referential' => [
                            'anr' => $anr->getId(),
                            'uuid' => $referential->getUuid(),
                        ]
                    ]);
                    if (empty($categories)) {
                        // TODO: set labels and status, remove the constructor set.
                        $newSoaCategory = (new SoaCategory($soaCategory))
                            ->setAnr($anr)
                            ->setReferential($referential);
                        $this->soaCategoryTable->saveEntity($newSoaCategory, false);
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
            $this->cachedData['measures'] = array_merge(
                $this->cachedData['measures'] ?? [],
                $this->assetImportService->getCachedDataByKey('measures'),
                $this->objectImportService->getCachedDataByKey('measures')
            );
            foreach ($data['measures'] as $measureUuid => $measureData) {
                $measure = $this->cachedData['measures'][$measureUuid]
                    ?? $this->measureTable->findByAnrAndUuid($anr, $measureUuid);
                if ($measure === null) {
                    // TODO: findBy...
                    $soaCategories = $this->soaCategoryTable->getEntityByFields([
                        'anr' => $anr->getId(),
                        $labelKey => $measureData['category']
                    ]);
                    if (isset($this->cachedData['referential'][$measureData['referential']])
                        && !empty($soaCategories)
                    ) {
                        $referential = $this->cachedData['referential'][$measureData['referential']];
                        // a measure must be linked to a referential and a category
                        $newMeasure = new Measure($measureData);
                        $newMeasure->setAnr($anr);
                        $newMeasure->setReferential($referential);
                        $newMeasure->setCategory($soaCategories[0]);
                        $newMeasure->setAmvs(new ArrayCollection()); // need to initialize the amvs link
                        $newMeasure->setRolfRisks(new ArrayCollection());
                        $this->measureTable->saveEntity($newMeasure);
                        $measuresNewIds[$measureUuid] = $newMeasure;

                        if (!isset($data['soas'])) {
                            // if no SOAs in the analysis to import, create new ones
                            $newSoa = (new Soa())
                                ->setAnr($anr)
                                ->setMeasure($newMeasure);
                            $this->soaTable->saveEntity($newSoa, false);
                        }
                    }
                }

                $this->cachedData['measures'][$measureUuid] = $measure;
            }

            $this->measureTable->getDb()->flush();
        }
        // import the measuresmeasures
        if (isset($data['measuresMeasures'])) {
            foreach ($data['measuresMeasures'] as $measureMeasureData) {
                $measuresMeasures = $this->measureMeasureTable->findByAnrFatherUuidAndChildUuid(
                    $anr,
                    $measureMeasureData['father'],
                    $measureMeasureData['child']
                );
                if ($measuresMeasures === null) {
                    $measureMeasure = (new MeasureMeasure())
                        ->setAnr($anr)
                        ->setFather($measureMeasureData['father'])
                        ->setChild($measureMeasureData['child']);

                    $this->measureMeasureTable->saveEntity($measureMeasure, false);
                }
            }
            $this->measureMeasureTable->getDb()->flush();
        }

        // import the SOAs
        if (isset($data['soas'])) {
            $existedMeasures = $this->measureTable->findByAnrIndexedByUuid($anr);
            foreach ($data['soas'] as $soa) {
                // check if the corresponding measure has been created during this import.
                if (isset($measuresNewIds[$soa['measure_id']])) {
                    $newSoa = (new Soa($soa))
                        ->setAnr($anr)
                        ->setMeasure($measuresNewIds[$soa['measure_id']]);
                    $this->soaTable->saveEntity($newSoa, false);
                } elseif (isset($existedMeasures[$soa['measure_id']])) { //measure exist so soa exist (normally)
                    // TODO: why not $existedMeasure->getSoa() ...
                    $existedMeasure = $existedMeasures[$soa['measure_id']];
                    $existedSoa = $this->soaTable->findByMeasure($existedMeasure);
                    if ($existedSoa === null) {
                        $newSoa = (new Soa($soa))
                            ->setAnr($anr)
                            ->setMeasure($existedMeasure);
                        $this->soaTable->saveEntity($newSoa, false);
                    } else {
                        $existedSoa->setRemarks($soa['remarks'])
                            ->setEvidences($soa['evidences'])
                            ->setActions($soa['actions'])
                            ->setCompliance($soa['compliance'])
                            ->setEX($soa['EX'])
                            ->setLR($soa['LR'])
                            ->setCO($soa['CO'])
                            ->setBR($soa['BR'])
                            ->setBP($soa['BP'])
                            ->setRRA($soa['RRA']);
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
            // Approximate values from the destination analysis

            $scalesOrigin = $this->prepareOriginScales($anr);
            $minScaleImpactOrigin = $scalesOrigin[Scale::TYPE_IMPACT]['min'];
            $maxScaleImpactOrigin = $scalesOrigin[Scale::TYPE_IMPACT]['max'];
            $minScaleImpactDestination = $data['scales'][Scale::TYPE_IMPACT]['min'];
            $maxScaleImpactDestination = $data['scales'][Scale::TYPE_IMPACT]['max'];

            $ts = ['c', 'i', 'd'];
            $instances = $this->instanceTable->findByAnrId($anr->getId());
            $consequences = $this->instanceConsequenceTable->findByAnr($anr);

            // Instances
            foreach ($ts as $t) {
                foreach ($instances as $instance) {
                    if ($instance->get($t . 'h')) {
                        $instance->set($t . 'h', 1);
                        $instance->set($t, -1);
                    } else {
                        $instance->set($t . 'h', 0);
                        $instance->set($t, $this->approximate(
                            $instance->get($t),
                            $minScaleImpactOrigin,
                            $maxScaleImpactOrigin,
                            $minScaleImpactDestination,
                            $maxScaleImpactDestination
                        ));
                    }

                    $this->anrInstanceService->refreshImpactsInherited($instance);
                }
                //Impacts & Consequences
                foreach ($consequences as $conseq) {
                    $conseq->set($t, $conseq->isHidden ? -1 : $this->approximate(
                        $conseq->get($t),
                        $minScaleImpactOrigin,
                        $maxScaleImpactOrigin,
                        $minScaleImpactDestination,
                        $maxScaleImpactDestination
                    ));
                    $this->instanceConsequenceTable->saveEntity($conseq, false);
                }
            }

            $minScaleThreatOrigin = $scalesOrigin[Scale::TYPE_THREAT]['min'];
            $maxScaleThreatOrigin = $scalesOrigin[Scale::TYPE_THREAT]['max'];
            $minScaleThreatDestination = $data['scales'][Scale::TYPE_THREAT]['min'];
            $maxScaleThreatDestination = $data['scales'][Scale::TYPE_THREAT]['max'];

            // Threat Qualification
            $threats = $this->threatTable->findByAnr($anr);
            foreach ($threats as $threat) {
                $threat->setQualification($this->approximate(
                    $threat->getQualification(),
                    $minScaleThreatOrigin,
                    $maxScaleThreatOrigin,
                    $minScaleThreatDestination,
                    $maxScaleThreatDestination
                ));
                $this->threatTable->saveEntity($threat, false);
            }

            // Informational Risks
            $instanceRisks = $this->instanceRiskTable->findByAnr($anr);
            foreach ($instanceRisks as $instanceRisk) {
                $instanceRisk->setThreatRate($this->approximate(
                    $instanceRisk->getThreatRate(),
                    $minScaleThreatOrigin,
                    $maxScaleThreatOrigin,
                    $minScaleThreatDestination,
                    $maxScaleThreatDestination
                ));
                $oldVulRate = $instanceRisk->getVulnerabilityRate();
                $instanceRisk->setVulnerabilityRate($this->approximate(
                    $instanceRisk->getVulnerabilityRate(),
                    $scalesOrigin[Scale::TYPE_VULNERABILITY]['min'],
                    $scalesOrigin[Scale::TYPE_VULNERABILITY]['max'],
                    $data['scales'][Scale::TYPE_VULNERABILITY]['min'],
                    $data['scales'][Scale::TYPE_VULNERABILITY]['max']
                ));
                $newVulRate = $instanceRisk->getVulnerabilityRate();
                $instanceRisk->setReductionAmount($instanceRisk->getReductionAmount() !== 0
                    ? $this->approximate($instanceRisk->getReductionAmount(), 0, $oldVulRate, 0, $newVulRate, 0)
                    : 0
                );

                $this->anrInstanceRiskService->updateRisks($instanceRisk);
            }

            /* Adjust the values of operational risks scales. */
            $this->adjustOperationalRisksScaleValuesBasedOnNewScales($anr, $data);

            // Finally update scales from import
            $scales = $this->scaleTable->findByAnr($anr);
            $types = [
                Scale::TYPE_IMPACT,
                Scale::TYPE_THREAT,
                Scale::TYPE_VULNERABILITY,
            ];
            foreach ($types as $type) {
                foreach ($scales as $scale) {
                    if ($scale->getType() === $type) {
                        $scale->setMin((int)$data['scales'][$type]['min']);
                        $scale->setMax((int)$data['scales'][$type]['max']);

                        $this->scaleTable->saveEntity($scale, false);
                    }
                }
            }

            $this->createOperationalInstanceRisksScales($anr, $data);
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

        if (!empty($data['scalesComments'])) {
            $pos = 1;
            $siId = null;
            $scIds = null;
            $sId = null;

            foreach ($data['scalesComments'] as $scale) {
                $scIds[$pos] = $scale['id'];
                $pos++;
            }
            // TODO: findBy...
            $scaleComment = $this->scaleCommentTable->getEntityByFields(
                ['anr' => $anr->getId()]
            );
            foreach ($scaleComment as $scale) {
                if ($scale->scaleImpactType === null || $scale->scaleImpactType->isSys === 1) {
                    $this->scaleCommentTable->delete($scale->id);
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
                        ->setScaleImpactType($siType)
                        ->setCreator($this->connectedUser->getEmail());

                    $this->instanceConsequenceTable->saveEntity($consequence, false);
                }
            }
        }

        $this->instanceConsequenceTable->getDb()->flush();

        return $instanceIds;
    }

    private function prepareOriginScales(AnrSuperClass $anr): array
    {
        $scalesOrigin = [];
        $scales = $this->scaleTable->findByAnr($anr);
        foreach ($scales as $scale) {
            $scalesValuesByIndex = [];
            foreach ($scale->getScaleComments() as $scaleComment) {
                $scalesValuesByIndex[$scaleComment->getScaleIndex()] = $scaleComment->getScaleValue();
            }
            $scalesOrigin[$scale->getType()] = [
                'min' => $scale->getMin(),
                'max' => $scale->getMax(),
                'values' => $scalesValuesByIndex,
            ];
        }

        return $scalesOrigin;
    }

    private function prepareCurrentOperationalScales(AnrSuperClass $anr): array
    {
        $operationalScalesOrigin = [];
        $operationalRisksScales = $this->operationalRiskScaleTable->findByAnr($anr);
        foreach ($operationalRisksScales as $operationalRisksScale) {
            $scalesValuesByIndex = [];
            foreach ($operationalRisksScale->getOperationalRiskScaleComments() as $scaleComment) {
                $scalesValuesByIndex[$scaleComment->getScaleIndex()] = $scaleComment->getScaleValue();
            }
            $operationalScalesOrigin[$operationalRisksScale->getType()] = [
                'min' => $operationalRisksScale->getMin(),
                'max' => $operationalRisksScale->getMax(),
                'values' => $scalesValuesByIndex,
            ];
        }

        return $operationalScalesOrigin;
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
     * @param int $defaultvalue
     *
     * @return int|mixed The approximated value
     */
    private function approximate(
        int $x,
        int $minorig,
        int $maxorig,
        int $mindest,
        int $maxdest,
        int $defaultvalue = -1
    ): int {
        if ($x === $maxorig) {
            return $maxdest;
        }

        if ($x !== -1 && ($maxorig - $minorig) !== -1) {
            return (int)min(max(
                round(($x / ($maxorig - $minorig + 1)) * ($maxdest - $mindest + 1)),
                $mindest
            ), $maxdest);
        }

        return $defaultvalue;
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
                            ->setLabel4($recommendationSetData['label4'])
                            ->setCreator($this->connectedUser->getEmail());

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
                    ->setLabel4('Geïmporteerde aanbevelingen')
                    ->setCreator($this->connectedUser->getEmail());

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
                            ->setComment($recommendationData['comment'] ?? '')
                            ->setResponsable($recommendationData['responsable'] ?? '')
                            ->setStatus($recommendationData['status'])
                            ->setImportance($recommendationData['importance'])
                            ->setCode($recommendationData['code'])
                            ->setDescription($recommendationData['description'] ?? '')
                            ->setCounterTreated($recommendationData['counterTreated'])
                            ->setCreator($this->connectedUser->getEmail());

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
            $recommendation = (new Recommandation())->setUuid($recommendationData['uuid'])
                ->setCreator($this->connectedUser->getEmail());
        } else {
            $recommendation->setUpdater($this->connectedUser->getEmail());
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
        ?Scale $localScaleImpact,
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
                        $instance->{'get' . $scaleCriteria}(),
                        $this->cachedData['scales']['orig'][Scale::TYPE_IMPACT]['min'],
                        $this->cachedData['scales']['orig'][Scale::TYPE_IMPACT]['max'],
                        $this->cachedData['scales']['dest'][Scale::TYPE_IMPACT]['min'],
                        $this->cachedData['scales']['dest'][Scale::TYPE_IMPACT]['max']
                    )
                );
            }
        }

        if (!empty($data['consequences'])) {
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
                        ->setPosition(++$scaleImpactTypeMaxPosition)
                        ->setCreator($this->connectedUser->getEmail());

                    $this->scalesImpactTypeTable->saveEntity($scaleImpactType, false);

                    $localScalesImpactTypes[$consequenceData['scaleImpactType'][$labelKey]] = $scaleImpactType;
                }

                $instanceConsequence = (new InstanceConsequence())
                    ->setAnr($anr)
                    ->setObject($monarcObject)
                    ->setInstance($instance)
                    ->setScaleImpactType($localScalesImpactTypes[$consequenceData['scaleImpactType'][$labelKey]])
                    ->setIsHidden((bool)$consequenceData['isHidden'])
                    ->setLocallyTouched($consequenceData['locallyTouched'])
                    ->setCreator($this->connectedUser->getEmail());

                foreach (InstanceConsequence::getAvailableScalesCriteria() as $scaleCriteriaKey => $scaleCriteria) {
                    $instanceConsequence->{'set' . $scaleCriteria}(
                        $instanceConsequence->isHidden()
                            ? -1
                            : $this->approximate(
                            $consequenceData[$scaleCriteriaKey],
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

        /**
         * First we get the data from the cached data AssetImportService and compute the difference.
         */
        if (empty($this->cachedData['threats'])) {
            $this->cachedData['threats'] = $this->assetImportService->getCachedDataByKey('threats');
            $threatsUuids = array_diff_key(
                array_column($data['threats'], 'uuid'),
                $this->cachedData['threats']
            );
            if (!empty($threatsUuids)) {
                $this->cachedData['threats'] = $this->threatTable->findByAnrAndUuidsIndexedByField(
                    $anr,
                    $threatsUuids,
                    'uuid'
                );
            }
        }
        if (empty($this->cachedData['vulnerabilities'])) {
            $this->cachedData['vulnerabilities'] = $this->assetImportService->getCachedDataByKey('vulnerabilities');
            $vulnerabilitiesUuids = array_diff_key(
                array_column($data['vuls'], 'uuid'),
                $this->cachedData['vulnerabilities']
            );
            if (!empty($vulnerabilitiesUuids)) {
                $this->cachedData['vulnerabilities'] = $this->vulnerabilityTable->findByAnrAndUuidsIndexedByField(
                    $anr,
                    $vulnerabilitiesUuids,
                    'uuid'
                );
            }
        }

        foreach ($data['risks'] as $instanceRiskData) {
            $threatData = $data['threats'][$instanceRiskData['threat']];
            $vulnerabilityData = $data['vuls'][$instanceRiskData['vulnerability']];

            if ((int)$instanceRiskData['specific'] === InstanceRisk::TYPE_SPECIFIC) {
                if (!isset($this->cachedData['threats'][$threatData['uuid']])) {
                    $threat = (new Threat())
                        ->setUuid($threatData['uuid'])
                        ->setAnr($anr)
                        ->setCode($threatData['code'])
                        ->setLabels($threatData)
                        ->setDescriptions($threatData)
                        ->setMode($threatData['mode'])
                        ->setStatus($threatData['status'])
                        ->setTrend($threatData['trend'])
                        ->setQualification($threatData['qualification'])
                        ->setComment($threatData['comment'])
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
                     * Unfortunately we don't add "themes" on the same level as "risks" and "threats", but only under "asset".
                     * TODO: we should add theme linked to the threat inside of the threat object data when export later on.
                     * after we can set it $threat->setTheme($theme);
                     */

                    $this->threatTable->saveEntity($threat, false);

                    $this->cachedData['threats'][$threatData['uuid']] = $threat;
                }

                if (!isset($this->cachedData['vulnerabilities'][$vulnerabilityData['uuid']])) {
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

                    $this->cachedData['vulnerabilities'][$vulnerabilityData['uuid']] = $vulnerability;
                }

                $instanceRisk = $this->createInstanceRiskFromData(
                    $instanceRiskData,
                    $anr,
                    $instance,
                    $monarcObject->getAsset(),
                    $this->cachedData['threats'][$threatData['uuid']],
                    $this->cachedData['vulnerabilities'][$vulnerabilityData['uuid']]
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
                        $this->cachedData['threats'][$threatData['uuid']],
                        $this->cachedData['vulnerabilities'][$vulnerabilityData['uuid']]
                    );

                    $this->instanceRiskTable->saveEntity($instanceRiskBrother, false);
                }
            }

            $threatUuid = isset($this->cachedData['threats'][$threatData['uuid']])
                ? $this->cachedData['threats'][$threatData['uuid']]->getUuid()
                : $instanceRiskData['threat'];
            $vulnerabilityUuid = isset($this->cachedData['vulnerabilities'][$vulnerabilityData['uuid']])
                ? $this->cachedData['vulnerabilities'][$vulnerabilityData['uuid']]->getUuid()
                : $instanceRiskData['vulnerability'];

            $instanceRisk = $this->instanceRiskTable->findByInstanceAssetThreatUuidAndVulnerabilityUuid(
                $instance,
                $monarcObject->getAsset(),
                $threatUuid,
                $vulnerabilityUuid
            );
            if ($instanceRisk !== null && $includeEval) {
                $instanceRisk->setThreatRate(
                    $this->approximate(
                        $instanceRiskData['threatRate'],
                        $this->cachedData['scales']['orig'][Scale::TYPE_THREAT]['min'],
                        $this->cachedData['scales']['orig'][Scale::TYPE_THREAT]['max'],
                        $this->cachedData['scales']['dest'][Scale::TYPE_THREAT]['min'],
                        $this->cachedData['scales']['dest'][Scale::TYPE_THREAT]['max']
                    )
                );
                $instanceRisk->setVulnerabilityRate(
                    $this->approximate(
                        $instanceRiskData['vulnerabilityRate'],
                        $this->cachedData['scales']['orig'][Scale::TYPE_VULNERABILITY]['min'],
                        $this->cachedData['scales']['orig'][Scale::TYPE_VULNERABILITY]['max'],
                        $this->cachedData['scales']['dest'][Scale::TYPE_VULNERABILITY]['min'],
                        $this->cachedData['scales']['dest'][Scale::TYPE_VULNERABILITY]['max']
                    )
                );
                $instanceRisk->setMh($instanceRiskData['mh']);
                $instanceRisk->setKindOfMeasure($instanceRiskData['kindOfMeasure']);
                $instanceRisk->setComment($instanceRiskData['comment'] ?? '');
                $instanceRisk->setCommentAfter($instanceRiskData['commentAfter'] ?? '');

                // La valeur -1 pour le reduction_amount n'a pas de sens, c'est 0 le minimum. Le -1 fausse
                // les calculs.
                // Cas particulier, faudrait pas mettre n'importe quoi dans cette colonne si on part d'une scale
                // 1 - 7 vers 1 - 3 on peut pas avoir une réduction de 4, 5, 6 ou 7
                $instanceRisk->setReductionAmount(
                    $instanceRiskData['reductionAmount'] !== -1
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

                    // TODO: findBy...
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
                            ->setRecommandation($recommendation)
                            ->setCreator($this->connectedUser->getEmail());

                        $this->recommendationRiskTable->saveEntity($recommendationRisk, false);

                        // Replicate recommendation to brothers.
                        if ($modeImport === 'merge' && $recommendationRisk->hasGlobalObjectRelation()) {
                            $brotherInstances = $this->getInstanceBrothers($instance);
                            if (!empty($brotherInstances)) {
                                foreach ($brotherInstances as $brotherInstance) {
                                    // Get the risks of brothers
                                    /** @var InstanceRisk[] $brothers */
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
                                                ->setRecommandation($recommendation)
                                                ->setCreator($this->connectedUser->getEmail());

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
            $instanceBrothers = $this->getInstanceBrothers($instance);
            if (!empty($instanceBrothers) && $instanceRisk !== null && !$instanceRisk->isSpecific()) {
                $instanceRiskBrothers = $this->instanceRiskTable->findByInstanceAndAmv(
                    current($instanceBrothers),
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
                                    ->setRecommandation($brotherRecoRisk->getRecommandation())
                                    ->setCreator($this->connectedUser->getEmail());

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
        $specificRisks = $this->instanceRiskTable->findByInstance($instance, true);
        foreach ($specificRisks as $specificRisk) {
            // TODO: replace all the queries with QueryBuilder. Review the logic.
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
                    ->setRecommandation($recommendationRiskToCreate->getRecommandation())
                    ->setCreator($this->connectedUser->getEmail());

                $this->recommendationRiskTable->saveEntity($recommendationRisk, false);
            }
        }
        $this->recommendationRiskTable->getDb()->flush();

        // on met finalement à jour les risques en cascade
        $this->anrInstanceService->updateRisks($instance);
    }

    private function processInstanceOperationalRisks(
        array $data,
        Anr $anr,
        Instance $instance,
        MonarcObject $monarcObject,
        bool $includeEval
    ): void {
        if (!empty($data['risksop'])) {
            // TODO: from cache or preload to cache.
            $operationalRiskScales = $this->getOperationalRisksScales($anr);

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
                    'targetedR',
                    'targetedO',
                    'targetedL',
                    'targetedF',
                    'targetedP',
                    'brutR',
                    'brutO',
                    'brutL',
                    'brutF',
                    'brutP',
                ],
            ];

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
                            ->setInstance($instance)
                            ->setInstanceRiskOp($instanceRiskOp)
                            ->setGlobalObject($monarcObject->isScopeGlobal() ? $monarcObject : null)
                            ->setCommentAfter($reco['commentAfter'] ?? '')
                            ->setRecommandation($recommendation);

                        // TODO: remove the trick when #240 is done.
                        $this->recommendationRiskTable->saveEntity($recommendationRisk);
                        $this->recommendationRiskTable->saveEntity($recommendationRisk->setAnr($anr), false);
                    }
                }

                $this->recommendationRiskTable->getDb()->flush();
            }
        }
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
                            $instanceConsequenceBrother->setIsHidden($instanceConsequence->isHidden())
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
            ->setMh((int)$instanceRiskData['mh'])
            ->setThreatRate((int)$instanceRiskData['threatRate'])
            ->setVulnerabilityRate((int)$instanceRiskData['vulnerabilityRate'])
            ->setKindOfMeasure($instanceRiskData['kindOfMeasure'])
            ->setReductionAmount((int)$instanceRiskData['reductionAmount'])
            ->setComment($instanceRiskData['comment'])
            ->setCommentafter($instanceRiskData['commentAfter'])
            ->setCacheMaxRisk((int)$instanceRiskData['cacheMaxRisk'])
            ->setCacheTargetedRisk((int)$instanceRiskData['cacheTargetedRisk'])
            ->setRiskConfidentiality((int)$instanceRiskData['riskC'])
            ->setRiskIntegrity((int)$instanceRiskData['riskI'])
            ->setRiskAvailability((int)$instanceRiskData['riskD'])
            ->setCreator($this->connectedUser->getEmail());
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function createInstance(
        array $data,
        Anr $anr,
        ?InstanceSuperClass $parentInstance,
        MonarcObject $monarcObject
    ): Instance  {
        $instanceData = $data['instance'];
        $instance = (new Instance())
            ->setAnr($anr)
            ->setLabels($instanceData)
            ->setNames($instanceData)
            ->setDisponibility(!empty($instanceData['disponibility']) ? (float)$instanceData['disponibility'] : 0)
            ->setLevel($parentInstance === null ? Instance::LEVEL_ROOT : $instanceData['level'])
            ->setRoot($parentInstance === null ? null : ($parentInstance->getRoot() ?? $parentInstance))
            ->setParent($parentInstance)
            ->setAssetType($instanceData['assetType'])
            ->setExportable($instanceData['exportable'])
            ->setPosition(++$this->currentMaxInstancePosition)
            ->setObject($monarcObject)
            ->setAsset($monarcObject->getAsset())
            ->setCreator($this->connectedUser->getEmail());
        if (isset($instanceData['c'])) {
            $instance->setConfidentiality((int)$instanceData['c']);
        }
        if (isset($instanceData['i'])) {
            $instance->setIntegrity((int)$instanceData['i']);
        }
        if (isset($instanceData['d'])) {
            $instance->setAvailability((int)$instanceData['d']);
        }
        if (isset($instanceData['ch'])) {
            $instance->setInheritedConfidentiality((int)$instanceData['ch']);
        }
        if (isset($instanceData['ih'])) {
            $instance->setInheritedIntegrity((int)$instanceData['ih']);
        }
        if (isset($instanceData['dh'])) {
            $instance->setInheritedAvailability((int)$instanceData['dh']);
        }

        $this->instanceTable->saveEntity($instance);

        return $instance;
    }

    /**
     * @throws Exception
     */
    private function setAndValidateMonarcVersion($data): void
    {
        if (isset($data['monarc_version'])) {
            $this->monarcVersion = strpos($data['monarc_version'], 'master') === false ? $data['monarc_version'] : '99';
        }

        if ($this->isMonarcVersionLoverThen('2.8.2')) {
            throw new Exception('Import of files exported from MONARC v2.8.1 or lower are not supported.'
                . ' Please contact us for more details.'
            );
        }
    }

    private function adjustOperationalRisksScaleValuesBasedOnNewScales(AnrSuperClass $anr, array $data): void
    {
        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnr($anr);
        if (!empty($operationalInstanceRisks)) {
            $currentOperationalScales = $this->prepareCurrentOperationalScales($anr);
            $newOperationalScalesData = $this->getPreparedNewOperationalRiskScalesData($data);

            foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
                foreach (['NetProb', 'BrutProb', 'TargetedProb'] as $likelihoodScaleName) {
                    $operationalInstanceRisk->set{$likelihoodScaleName}($this->approximate(
                        $operationalInstanceRisk->get{$likelihoodScaleName}(),
                        $currentOperationalScales[OperationalRiskScale::TYPE_LIKELIHOOD]['min'],
                        $currentOperationalScales[OperationalRiskScale::TYPE_LIKELIHOOD]['max'],
                        $newOperationalScalesData[OperationalRiskScale::TYPE_LIKELIHOOD]['min'],
                        $newOperationalScalesData[OperationalRiskScale::TYPE_LIKELIHOOD]['max']
                    ));
                }

                foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $riskScale) {
                    foreach (['NetValue', 'BrutValue', 'TargetedValue'] as $impactScaleName) {
                        $scaleImpactValue = $riskScale->get{$impactScaleName}();
                        $scaleImpactIndex = 0;
                        foreach ($riskScale->getOperationalRiskScaleType()
                                     ->getOperationalRiskScaleComments() as $scaleComment) {
                            if ($scaleComment->getScaleValue() === $scaleImpactValue) {
                                $scaleImpactIndex = $scaleComment->getScaleIndex();
                            }
                        }
                        $approximatedIndex = $this->approximate(
                            $scaleImpactIndex,
                            $currentOperationalScales[OperationalRiskScale::TYPE_IMPACT]['min'],
                            $currentOperationalScales[OperationalRiskScale::TYPE_IMPACT]['max'],
                            $newOperationalScalesData[OperationalRiskScale::TYPE_IMPACT]['min'],
                            $newOperationalScalesData[OperationalRiskScale::TYPE_IMPACT]['max']
                        );

                        $approximatedValueInNewScales = $newOperationalScalesData[OperationalRiskScale::TYPE_IMPACT][
                            'commentsIndexToValueMap'
                        ][$approximatedIndex] ?? $scaleImpactValue;
                        $riskScale->set{$impactScaleName}($approximatedValueInNewScales);

                        $this->operationalInstanceRiskScaleTable->save($riskScale, false);
                    }
                }

                $this->instanceRiskOpTable->saveEntity($operationalInstanceRisk, false);

                $this->anrInstanceRiskOpService->updateRiskCacheValues($operationalInstanceRisk);
            }

            $this->instanceRiskOpTable->getDb()->flush();
        }
    }

    /**
     * Prepare and cache the new scales for the future use.
     * The format can be different, depends on the version (before v2.10.5 and after).
     */
    private function getPreparedNewOperationalRiskScalesData(array $data): array
    {
        if (empty($this->cachedData['preparedNewOperationalRiskScales'])) {
            /* Populate with informational risks scales in case if there is an import of file before v2.10.5. */
            $scalesDataResult = [
                OperationalRiskScale::TYPE_IMPACT => [
                    'min' => 0,
                    'max' => $data['scales'][Scale::TYPE_IMPACT]['max'] - $data['scales'][Scale::TYPE_IMPACT]['min'],
                    'commentsIndexToValueMap' => [],
                ],
                OperationalRiskScale::TYPE_LIKELIHOOD => [
                    'min' => $data['scales'][Scale::TYPE_THREAT]['min'],
                    'max' => $data['scales'][Scale::TYPE_THREAT]['max'],
                    'commentsIndexToValueMap' => [],
                ],
            ];
            if (!empty($data['operationalRiskScales'])) {
                /* Overwrite the values for the version >= 2.10.5. */
                $operationalImpactScale = $data['operationalRiskScales'][OperationalRiskScale::TYPE_IMPACT];
                $scalesDataResult[OperationalRiskScale::TYPE_IMPACT]['min'] = $operationalImpactScale['min'];
                $scalesDataResult[OperationalRiskScale::TYPE_IMPACT]['max'] = $operationalImpactScale['max'];

                $operationalLikelihoodScale = $data['operationalRiskScales'][OperationalRiskScale::TYPE_LIKELIHOOD];
                $scalesDataResult[OperationalRiskScale::TYPE_LIKELIHOOD]['min'] = $operationalLikelihoodScale['min'];
                $scalesDataResult[OperationalRiskScale::TYPE_LIKELIHOOD]['max'] = $operationalLikelihoodScale['max'];

                /* Build the map of the comments index <=> values relation. */
                foreach ($data['operationalRiskScales']['operationalRiskScaleTypes'] as $scaleTypeData) {
                    foreach ($scaleTypeData['operationalRiskScaleTypeComments'] as $scaleTypeComment) {
                        $scalesDataResult['commentsIndexToValueMap'][$scaleTypeComment['scaleIndex']] =
                            $scaleTypeComment['scaleValue'];
                    }
                    $scalesDataResult['operationalRiskScaleTypes'][] = $scaleTypeData;
                }

                $scalesDataResult['operationalRiskScaleComments'] =
                    $data['operationalRiskScales']['operationalRiskScaleComments'];
            } else {
                /* Convert comments and types from informational risks to operational.  */
                // TODO: ......
                // TODO: don't forget to perform the index adjustment for the impact scale when min > 0.
                $scalesDataResult['operationalRiskScaleTypes'] = [];
                $scalesDataResult['operationalRiskScaleComments'] = [];
                $scalesDataResult['commentsIndexToValueMap'] = [];
            }

            $this->cachedData['preparedNewOperationalRiskScales'] = $scalesDataResult;
        }

        return $this->cachedData['preparedNewOperationalRiskScales'];
    }

    private function createOperationalInstanceRisksScales(AnrSuperClass $anr, array $data): void
    {
        $operationalRiskScales = $this->operationalRiskScaleTable->findByAnr($anr);
        $anrLanguageCode = $this->getAnrLanguageCode($anr);
        $scalesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScaleType::TRANSLATION_TYPE_NAME, OperationalRiskScaleComment::TRANSLATION_TYPE_NAME],
            $anrLanguageCode
        );
        $newOperationalScalesData = $this->getPreparedNewOperationalRiskScalesData($data);

        foreach ($operationalRiskScales as $operationalRiskScale) {
            $scaleData = $newOperationalScalesData[$operationalRiskScale->getType()];
            $operationalRiskScale
                ->setAnr($anr)
                ->setMin($scaleData['min'])
                ->setMax($scaleData['max'])
                ->setUpdater($this->connectedUser->getEmail());

            /* This is currently only applicable for impact scales type. */
            $createdScaleTypes = [];
            foreach ($scaleData['operationalRiskScaleTypes'] as $scaleTypeData) {
                $operationalRiskScaleType = $this->matchScaleTypeDataWithScaleTypesList(
                    $scaleTypeData,
                    $operationalRiskScale->getOperationalRiskScaleTypes(),
                    $scalesTranslations
                );
                if ($operationalRiskScaleType === null) {
                    $operationalRiskScaleType = (new OperationalRiskScaleType())
                        ->setAnr($anr)
                        ->setOperationalRiskScale($operationalRiskScale)
                        ->setLabelTranslationKey($scaleTypeData['labelTranslationKey'])
                        ->setCreator($this->connectedUser->getEmail());

                    $translation = (new Translation())
                        ->setAnr($anr)
                        ->setType(OperationalRiskScaleType::TRANSLATION_TYPE_NAME)
                        ->setLang($anrLanguageCode)
                        ->setKey($operationalRiskScaleType->getLabelTranslationKey())
                        ->setValue($scaleTypeData['translation']['value'])
                        ->setCreator($this->connectedUser->getEmail());
                    $this->translationTable->save($translation, false);

                    $createdScaleTypes[] = $operationalRiskScaleType;
                }
                $operationalRiskScaleType->setIsHidden($scaleTypeData['isHidden']);
                $this->operationalRiskScaleTypeTable->save($operationalRiskScaleType, false);

                foreach ($scaleTypeData['operationalRiskScaleTypeComments'] as $scaleTypeCommentData) {
                    $operationalRiskScaleComment = $this->matchScaleCommentDataWithScaleCommentsList(
                        $scaleTypeCommentData,
                        $operationalRiskScaleType->getOperationalRiskScaleComments(),
                        $scalesTranslations
                    );
                    if ($operationalRiskScaleComment === null) {
                        $operationalRiskScaleComment = (new OperationalRiskScaleComment())
                            ->setAnr($anr)
                            ->setOperationalRiskScale($operationalRiskScale)
                            ->setCommentTranslationKey($scaleTypeCommentData['commentTranslationKey'])
                            ->setCreator($this->connectedUser->getEmail());

                        $translation = (new Translation())
                            ->setAnr($anr)
                            ->setType(OperationalRiskScaleComment::TRANSLATION_TYPE_NAME)
                            ->setLang($anrLanguageCode)
                            ->setKey($operationalRiskScaleComment->getCommentTranslationKey())
                            ->setValue($scaleTypeCommentData['translation']['value'])
                            ->setCreator($this->connectedUser->getEmail());
                        $this->translationTable->save($translation, false);
                    }

                    $operationalRiskScaleComment
                        ->setOperationalRiskScaleType($operationalRiskScaleType)
                        ->setScaleIndex($scaleTypeCommentData['scaleIndex'])
                        ->setScaleValue($scaleTypeCommentData['scaleValue'])
                        ->setIsHidden($scaleTypeCommentData['isHidden']);
                    $this->operationalRiskScaleCommentTable->save($operationalRiskScaleComment, false);
                }
            }

            /* Add the created scales to all the existed risks. */
            if (!empty($createdScaleTypes)) {
                $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnr($anr);
                foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
                    foreach ($createdScaleTypes as $createdScaleType) {
                        $this->instanceRiskOpTable->saveEntity(
                            $operationalInstanceRisk->addOperationalInstanceRiskScale($createdScaleType),
                            false
                        );
                    }
                }
            }

            /* This is currently applicable only for likelihood scales type */
            foreach ($scaleData['operationalRiskScaleComments'] as $scaleCommentData) {
                $operationalRiskScaleComment = $this->matchScaleCommentDataWithScaleCommentsList(
                    $scaleCommentData,
                    $operationalRiskScale->getOperationalRiskScaleComments(),
                    $scalesTranslations
                );
                if ($operationalRiskScaleComment === null) {
                    $operationalRiskScaleComment = (new OperationalRiskScaleComment())
                        ->setAnr($anr)
                        ->setOperationalRiskScale($operationalRiskScale)
                        ->setCommentTranslationKey($scaleCommentData['commentTranslationKey'])
                        ->setCreator($this->connectedUser->getEmail());

                    $translation = (new Translation())
                        ->setAnr($anr)
                        ->setType(OperationalRiskScaleComment::TRANSLATION_TYPE_NAME)
                        ->setLang($anrLanguageCode)
                        ->setKey($operationalRiskScaleComment->getCommentTranslationKey())
                        ->setValue($scaleCommentData['translation']['value'])
                        ->setCreator($this->connectedUser->getEmail());
                    $this->translationTable->save($translation, false);
                }

                $operationalRiskScaleComment
                    ->setScaleIndex($scaleCommentData['scaleIndex'])
                    ->setScaleValue($scaleCommentData['scaleValue'])
                    ->setIsHidden($scaleCommentData['isHidden']);
                $this->operationalRiskScaleCommentTable->save($operationalRiskScaleComment, false);
            }

            /* Validate if any existed comments are now out of the new scales bound and if the values are valid. */
            $maxValue = 0;
            foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $scaleComment) {
                if ($maxValue !== 0 && $maxValue >= $scaleComment->getScaleValue()) {
                    $scaleComment->setScaleValue(++$maxValue);
                }
                $isHidden = $operationalRiskScale->getMin() > $scaleComment->getScaleIndex()
                    || $operationalRiskScale->getMax() < $scaleComment->getScaleIndex();
                $scaleComment->setIsHidden($isHidden);

                $this->operationalRiskScaleCommentTable->save($scaleComment, false);

                $maxValue = $scaleComment->getScaleValue();
            }

            $this->operationalRiskScaleTable->save($operationalRiskScale);
        }
    }

    /**
     * @param array $scaleTypeData
     * @param OperationalRiskScaleType[] $operationalRiskScaleTypes
     * @param Translation[] $scalesTranslations
     *
     * @return OperationalRiskScaleType|null
     */
    private function matchScaleTypeDataWithScaleTypesList(
        array $scaleTypeData,
        iterable $operationalRiskScaleTypes,
        array $scalesTranslations
    ): ?OperationalRiskScaleType {
        foreach ($operationalRiskScaleTypes as $operationalRiskScaleType) {
            $translation = $scalesTranslations[$operationalRiskScaleType->getLabelTranslationKey()];
            if ($translation->getValue() === $scaleTypeData['translation']['value']) {
                return $operationalRiskScaleType;
            }
        }

        return null;
    }

    /**
     * @param array $scaleTypeCommentData
     * @param OperationalRiskScaleComment[] $operationalRiskScaleComments
     * @param Translation[] $scalesTranslations
     *
     * @return OperationalRiskScaleComment|null
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function matchScaleCommentDataWithScaleCommentsList(
        array $scaleTypeCommentData,
        iterable $operationalRiskScaleComments,
        array $scalesTranslations
    ): ?OperationalRiskScaleComment {
        foreach ($operationalRiskScaleComments as $operationalRiskScaleComment) {
            if ($operationalRiskScaleComment->getScaleIndex() === $scaleTypeCommentData['scaleIndex']) {
                $translation = $scalesTranslations[$operationalRiskScaleComment->getCommentTranslationKey()];
                if ($translation->getValue() !== $scaleTypeCommentData['translation']['value']) {
                    /* We need to update the translation value. */
                    $translation->setValue($scaleTypeCommentData['translation']['value']);
                    $this->translationTable->save($translation, false);
                }

                return $operationalRiskScaleComment;
            }
        }

        return null;
    }

    /**
     * @return OperationalInstanceRiskScale[]
     */
    private function getOperationalRisksScales(AnrSuperClass $anr): array
    {
        if (empty($this->cachedData['operationalRiskScales'])) {
            $operationalRiskScales = $this->operationalRiskScaleTable->findByAnr($anr);
            foreach ($operationalRiskScales as $operationalRiskScale) {
                $this->cachedData['operationalRiskScales'][$operationalRiskScale->getType()] = $operationalRiskScale;
            }
        }

        return $this->cachedData['operationalRiskScales'];
    }

    private function getAnrLanguageCode(AnrSuperClass $anr): string
    {
        return strtolower($this->configService->getLanguageCodes()[$anr->getLanguage()]);
    }
}
