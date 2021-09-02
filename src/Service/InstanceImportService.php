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
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;
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
use Monarc\FrontOffice\Model\Entity\RolfRisk;
use Monarc\FrontOffice\Model\Entity\Scale;
use Monarc\FrontOffice\Model\Entity\ScaleComment;
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
use Monarc\FrontOffice\Model\Table\InstanceRiskOwnerTable;
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
use Ramsey\Uuid\Uuid;

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

    private OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable;

    private InstanceRiskOwnerTable $instanceRiskOwnerTable;

    private string $importType;

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
        InstanceRiskOwnerTable $instanceRiskOwnerTable,
        ConnectedUserService $connectedUserService,
        TranslationTable $translationTable,
        ConfigService $configService
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
        $this->instanceRiskOwnerTable = $instanceRiskOwnerTable;
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
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '-1');

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
        $this->validateIfImportIsPossible($anr, $parentInstance, $data);

        $this->setAndValidateMonarcVersion($data);

        $this->initialAnalyseMaxRecommendationPosition = $this->recommendationTable->getMaxPositionByAnr($anr);
        $this->currentAnalyseMaxRecommendationPosition = $this->initialAnalyseMaxRecommendationPosition;
        $this->currentMaxInstancePosition = $this->instanceTable->getMaxPositionByAnrAndParent($anr, $parentInstance);

        $result = false;

        if (isset($data['type']) && $data['type'] === 'instance') {
            $this->importType = 'instance';

            $result = $this->importInstanceFromArray($data, $anr, $parentInstance, $modeImport);
        }

        if (isset($data['type']) && $data['type'] === 'anr') {
            $this->importType = 'anr';

            $result = $this->importAnrFromArray($data, $anr, $parentInstance, $modeImport);
        }

        $this->fixImportMismatches();

        return $result;
    }

    private function isImportTypeAnr(): bool
    {
        return $this->importType === 'anr';
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

        $this->anrInstanceRiskService->createInstanceRisks($instance, $anr, $monarcObject, $data);

        $this->prepareInstanceConsequences($data, $anr, $instance, $monarcObject, $includeEval);

        $this->updateInstanceImpactsFromBrothers($instance, $modeImport);

        $this->anrInstanceService->refreshImpactsInherited($instance);

        $this->createSetOfRecommendations($data, $anr);

        $this->processInstanceRisks($data, $anr, $instance, $monarcObject, $includeEval, $modeImport);

        $this->processOperationalInstanceRisks($data, $anr, $instance, $monarcObject, $includeEval);

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
            /* Approximate values based on the scales from the destination analysis */

            $scalesData = $this->getCurrentAndExternalScalesData($anr, $data);

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
                            $scalesData['current'][Scale::TYPE_IMPACT]['min'],
                            $scalesData['current'][Scale::TYPE_IMPACT]['max'],
                            $scalesData['external'][Scale::TYPE_IMPACT]['min'],
                            $scalesData['external'][Scale::TYPE_IMPACT]['max']
                        ));
                    }

                    $this->anrInstanceService->refreshImpactsInherited($instance);
                }
                // Impacts & Consequences.
                foreach ($consequences as $conseq) {
                    $conseq->set($t, $conseq->isHidden() ? -1 : $this->approximate(
                        $conseq->get($t),
                        $scalesData['current'][Scale::TYPE_IMPACT]['min'],
                        $scalesData['current'][Scale::TYPE_IMPACT]['max'],
                        $scalesData['external'][Scale::TYPE_IMPACT]['min'],
                        $scalesData['external'][Scale::TYPE_IMPACT]['max']
                    ));
                    $this->instanceConsequenceTable->saveEntity($conseq, false);
                }
            }


            // Threat Qualification
            $threats = $this->threatTable->findByAnr($anr);
            foreach ($threats as $threat) {
                $threat->setQualification($this->approximate(
                    $threat->getQualification(),
                    $scalesData['current'][Scale::TYPE_THREAT]['min'],
                    $scalesData['current'][Scale::TYPE_THREAT]['max'],
                    $scalesData['external'][Scale::TYPE_THREAT]['min'],
                    $scalesData['external'][Scale::TYPE_THREAT]['max'],
                ));
                $this->threatTable->saveEntity($threat, false);
            }

            // Informational Risks
            $instanceRisks = $this->instanceRiskTable->findByAnr($anr);
            foreach ($instanceRisks as $instanceRisk) {
                $instanceRisk->setThreatRate($this->approximate(
                    $instanceRisk->getThreatRate(),
                    $scalesData['current'][Scale::TYPE_THREAT]['min'],
                    $scalesData['current'][Scale::TYPE_THREAT]['max'],
                    $scalesData['external'][Scale::TYPE_THREAT]['min'],
                    $scalesData['external'][Scale::TYPE_THREAT]['max'],
                ));
                $oldVulRate = $instanceRisk->getVulnerabilityRate();
                $instanceRisk->setVulnerabilityRate($this->approximate(
                    $instanceRisk->getVulnerabilityRate(),
                    $scalesData['current'][Scale::TYPE_VULNERABILITY]['min'],
                    $scalesData['current'][Scale::TYPE_VULNERABILITY]['max'],
                    $scalesData['external'][Scale::TYPE_VULNERABILITY]['min'],
                    $scalesData['external'][Scale::TYPE_VULNERABILITY]['max'],
                ));
                $newVulRate = $instanceRisk->getVulnerabilityRate();
                $instanceRisk->setReductionAmount(
                    $instanceRisk->getReductionAmount() !== 0
                    ? $this->approximate($instanceRisk->getReductionAmount(), 0, $oldVulRate, 0, $newVulRate, 0)
                    : 0
                );

                $this->anrInstanceRiskService->updateRisks($instanceRisk);
            }

            /* Adjust the values of operational risks scales. */
            $this->adjustOperationalRisksScaleValuesBasedOnNewScales($anr, $data);

            $this->updateScalesAndComments($anr, $data);

            $this->updateOperationalRisksScalesAndRelatedInstances($anr, $data);
        }

        $first = true;
        $instanceIds = [];
        usort($data['instances'], function ($a, $b) {
            return $a['instance']['position'] <=> $b['instance']['position'];
        });
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

    /**
     * Method to approximate the value within new bounds, typically when the exported object had a min/max bound
     * bigger than the target's ANR bounds.
     *
     * @param int $value The value to approximate
     * @param int $minorig The source min bound
     * @param int $maxorig The source max bound
     * @param int $mindest The target min bound
     * @param int $maxdest The target max bound
     * @param int $defaultvalue
     *
     * @return int|mixed The approximated value
     */
    private function approximate(
        int $value,
        int $minorig,
        int $maxorig,
        int $mindest,
        int $maxdest,
        int $defaultvalue = -1
    ): int {
        if ($value === $maxorig) {
            return $maxdest;
        }

        if ($value !== -1 && ($maxorig - $minorig) !== -1) {
            return (int)min(max(
                round(($value / ($maxorig - $minorig + 1)) * ($maxdest - $mindest + 1)),
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
            ->setComment($recommendationData['comment'] ?? '')
            ->setResponsable($recommendationData['responsable'] ?? '')
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
     * Validates if the data can be imported into the anr.
     */
    private function validateIfImportIsPossible(Anr $anr, ?InstanceSuperClass $parent, array $data): void
    {
        if ($parent !== null
            && (
                $parent->getLevel() === InstanceSuperClass::LEVEL_INTER
                || $parent->getAnr() !== $anr
            )
        ) {
            throw new Exception('Parent instance should be in the node tree and the analysis IDs are matched', 412);
        }

        if (!empty($data['with_eval']) && empty($data['scales'])) {
            throw new Exception('The importing file should include evaluation scales.', 412);
        }
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

        $scalesData = $this->getCurrentAndExternalScalesData($anr, $data);

        foreach (Instance::getAvailableScalesCriteria() as $scaleCriteria) {
            if ($instance->{'getInherited' . $scaleCriteria}()) {
                $instance->{'setInherited' . $scaleCriteria}(1);
                $instance->{'set' . $scaleCriteria}(-1);
            } else {
                $instance->{'setInherited' . $scaleCriteria}(0);
                if (!$this->isImportTypeAnr()) {
                    $instance->{'set' . $scaleCriteria}(
                        $this->approximate(
                            $instance->{'get' . $scaleCriteria}(),
                            $scalesData['external'][Scale::TYPE_IMPACT]['min'],
                            $scalesData['external'][Scale::TYPE_IMPACT]['max'],
                            $scalesData['current'][Scale::TYPE_IMPACT]['min'],
                            $scalesData['current'][Scale::TYPE_IMPACT]['max']
                        )
                    );
                }
            }
        }

        if (!empty($data['consequences'])) {
            $localScaleImpact = $this->scaleTable->findByAnrAndType($anr, Scale::TYPE_IMPACT);
            $scalesImpactTypes = $this->scalesImpactTypeTable->findByAnr($anr);
            $localScalesImpactTypes = [];
            foreach ($scalesImpactTypes as $scalesImpactType) {
                $localScalesImpactTypes[$scalesImpactType->getLabel($anr->getLanguage())] = $scalesImpactType;
            }
            $scaleImpactTypeMaxPosition = $this->scalesImpactTypeTable->findMaxPositionByAnrAndScale(
                $anr,
                $localScaleImpact
            );

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
                    if ($instanceConsequence->isHidden()) {
                        $value = -1;
                    } else {
                        $value = $this->isImportTypeAnr()
                            ? $consequenceData[$scaleCriteriaKey]
                            : $this->approximate(
                                $consequenceData[$scaleCriteriaKey],
                                $scalesData['external'][Scale::TYPE_IMPACT]['min'],
                                $scalesData['external'][Scale::TYPE_IMPACT]['max'],
                                $scalesData['current'][Scale::TYPE_IMPACT]['min'],
                                $scalesData['current'][Scale::TYPE_IMPACT]['max']
                            );
                    }
                    $instanceConsequence->{'set' . $scaleCriteria}($value);
                }

                $this->instanceConsequenceTable->saveEntity($instanceConsequence, false);
            }

            $this->instanceConsequenceTable->getDb()->flush();
        }
    }

    /**
     * The prepared cachedData['scales'] are used to convert(approximate) the risks' values of importing instance(s),
     * from external to current scales (in case of instance(s) import).
     * For ANR import, the current analysis risks' values are converted from current to external scales.
     */
    private function getCurrentAndExternalScalesData(Anr $anr, array $data): array
    {
        if (empty($this->cachedData['scales'])) {
            $scales = $this->scaleTable->findByAnr($anr);
            $this->cachedData['scales']['current'] = [];
            $this->cachedData['scales']['external'] = $data['scales'];

            foreach ($scales as $scale) {
                $this->cachedData['scales']['current'][$scale->getType()] = [
                    'min' => $scale->getMin(),
                    'max' => $scale->getMax(),
                ];
            }
        }

        return $this->cachedData['scales'];
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

        $scalesData = [];
        if ($includeEval && !$this->isImportTypeAnr()) {
            $scalesData = $this->getCurrentAndExternalScalesData($anr, $data);
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
                    $this->isImportTypeAnr()
                        ? $instanceRiskData['threatRate']
                        : $this->approximate(
                            $instanceRiskData['threatRate'],
                            $scalesData['external'][Scale::TYPE_THREAT]['min'],
                            $scalesData['external'][Scale::TYPE_THREAT]['max'],
                            $scalesData['current'][Scale::TYPE_THREAT]['min'],
                            $scalesData['current'][Scale::TYPE_THREAT]['max']
                        )
                );
                $instanceRisk->setVulnerabilityRate(
                    $this->isImportTypeAnr()
                        ? $instanceRiskData['vulnerabilityRate']
                        : $this->approximate(
                            $instanceRiskData['vulnerabilityRate'],
                            $scalesData['external'][Scale::TYPE_VULNERABILITY]['min'],
                            $scalesData['external'][Scale::TYPE_VULNERABILITY]['max'],
                            $scalesData['current'][Scale::TYPE_VULNERABILITY]['min'],
                            $scalesData['current'][Scale::TYPE_VULNERABILITY]['max']
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
                            0
                        )
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

    private function processOperationalInstanceRisks(
        array $data,
        Anr $anr,
        Instance $instance,
        MonarcObject $monarcObject,
        bool $includeEval
    ): void {
        if (empty($data['risksop'])) {
            return;
        }

        $operationalRiskScalesData = $this->getCurrentOperationalRiskScalesData($anr);
        $externalOperationalRiskScalesData = [];
        $areScalesLevelsOfLikelihoodDifferent = false;
        $areImpactScaleTypesValuesDifferent = false;
        $matchedScaleTypesMap = [];
        if ($includeEval && !$this->isImportTypeAnr()) {
            $externalOperationalRiskScalesData = $this->getExternalOperationalRiskScalesData($anr, $data);
            $areScalesLevelsOfLikelihoodDifferent = $this->areScalesLevelsOfTypeDifferent(
                OperationalRiskScale::TYPE_LIKELIHOOD,
                $operationalRiskScalesData,
                $externalOperationalRiskScalesData
            );
            $areImpactScaleTypesValuesDifferent = $this->areScaleTypeValuesDifferent(
                OperationalRiskScale::TYPE_IMPACT,
                $operationalRiskScalesData,
                $externalOperationalRiskScalesData
            );
            $matchedScaleTypesMap = $this->matchAndGetOperationalRiskScaleTypesMap(
                $anr,
                $operationalRiskScalesData,
                $externalOperationalRiskScalesData
            );
        }
        $oldInstanceRiskFieldsMapToScaleTypesFields = [
            ['brutR' => 'BrutValue', 'netR' => 'NetValue', 'targetedR' => 'TargetedValue'],
            ['brutO' => 'BrutValue', 'netO' => 'NetValue', 'targetedO' => 'TargetedValue'],
            ['brutL' => 'BrutValue', 'netL' => 'NetValue', 'targetedL' => 'TargetedValue'],
            ['brutF' => 'BrutValue', 'netF' => 'NetValue', 'targetedF' => 'TargetedValue'],
            ['brutP' => 'BrutValue', 'netP' => 'NetValue', 'targetedP' => 'TargetedValue'],
        ];

        $cachedRolfRisks = $this->objectImportService->getCachedDataByKey('rolfRisks');
        foreach ($data['risksop'] as $operationalRiskData) {
            $operationalInstanceRisk = (new InstanceRiskOp())
                ->setAnr($anr)
                ->setInstance($instance)
                ->setObject($monarcObject)
                ->setRiskCacheLabels([
                    'riskCacheLabel1' => $operationalRiskData['riskCacheLabel1'],
                    'riskCacheLabel2' => $operationalRiskData['riskCacheLabel2'],
                    'riskCacheLabel3' => $operationalRiskData['riskCacheLabel3'],
                    'riskCacheLabel4' => $operationalRiskData['riskCacheLabel4'],
                ])
                ->setRiskCacheDescriptions([
                    'riskCacheDescription1' => $operationalRiskData['riskCacheDescription1'],
                    'riskCacheDescription2' => $operationalRiskData['riskCacheDescription2'],
                    'riskCacheDescription3' => $operationalRiskData['riskCacheDescription3'],
                    'riskCacheDescription4' => $operationalRiskData['riskCacheDescription4'],
                ])
                ->setBrutProb($operationalRiskData['brutProb'])
                ->setNetProb($operationalRiskData['netProb'])
                ->setTargetedProb($operationalRiskData['targetedProb'])
                ->setCacheBrutRisk($operationalRiskData['cacheBrutRisk'])
                ->setCacheNetRisk($operationalRiskData['cacheNetRisk'])
                ->setCacheTargetedRisk($operationalRiskData['cacheTargetedRisk'])
                ->setKindOfMeasure($operationalRiskData['kindOfMeasure'])
                ->setComment($operationalRiskData['comment'] ?? '')
                ->setMitigation($operationalRiskData['mitigation'] ?? '')
                ->setSpecific($operationalRiskData['specific'])
                ->setContext($operationalRiskData['context'] ?? '')
                ->setCreator($this->connectedUser->getEmail());

            if (!empty($operationalRiskData['riskOwner'])) {
                $instanceRiskOwner = $this->anrInstanceRiskService->getOrCreateInstanceRiskOwner(
                    $anr,
                    $operationalRiskData['riskOwner']
                );
                $operationalInstanceRisk->setInstanceRiskOwner($instanceRiskOwner);
            }

            if ($areScalesLevelsOfLikelihoodDifferent) {
                $this->adjustOperationalRisksProbabilityScales(
                    $operationalInstanceRisk,
                    $externalOperationalRiskScalesData[OperationalRiskScale::TYPE_LIKELIHOOD],
                    $operationalRiskScalesData[OperationalRiskScale::TYPE_LIKELIHOOD]
                );
            }

            if (!empty($operationalRiskData['rolfRisk']) && $monarcObject->getRolfTag() !== null) {
                $rolfRisk = $cachedRolfRisks[$operationalRiskData['rolfRisk']] ?? null;
                if ($rolfRisk !== null) {
                    $operationalInstanceRisk->setRolfRisk($rolfRisk)->setRiskCacheCode($rolfRisk->getCode());
                } else {
                    $this->cachedData['operationalRisksToFixRolfRisks'][] = [
                        'operationalRisk' => $operationalInstanceRisk,
                        'rolfRiskId' => $operationalRiskData['rolfRisk'],
                    ];
                }
            }

            $impactScale = $operationalRiskScalesData[OperationalRiskScale::TYPE_IMPACT];
            foreach ($impactScale['operationalRiskScaleTypes'] as $index => $scaleType) {
                /** @var OperationalRiskScaleType $operationalRiskScaleType */
                $operationalRiskScaleType = $scaleType['object'];
                $operationalInstanceRiskScale = (new OperationalInstanceRiskScale())
                    ->setAnr($anr)
                    ->setOperationalRiskScaleType($operationalRiskScaleType)
                    ->setOperationalInstanceRisk($operationalInstanceRisk)
                    ->setCreator($this->connectedUser->getEmail());

                if ($includeEval) {
                    /* The format is since v2.11.0 */
                    if (isset($operationalRiskData['scalesValues'])) {
                        $externalScaleTypeId = null;
                        if ($this->isImportTypeAnr()) {
                            /* For anr import, match current scale type translation key with external ids. */
                            $externalScaleTypeId = $this->getExternalScaleTypeIdByCurrentScaleLabelTranslationKey(
                                $operationalRiskScaleType->getLabelTranslationKey()
                            );
                        } elseif (isset($matchedScaleTypesMap['currentScaleTypeKeysToExternalIds']
                            [$operationalRiskScaleType->getLabelTranslationKey()])
                        ) {
                            /* For instance import, match current scale type translation key with external ids. */
                            $externalScaleTypeId = $matchedScaleTypesMap['currentScaleTypeKeysToExternalIds']
                            [$operationalRiskScaleType->getLabelTranslationKey()];
                        }
                        if ($externalScaleTypeId !== null
                            && isset($operationalRiskData['scalesValues'][$externalScaleTypeId])
                        ) {
                            $scalesValueData = $operationalRiskData['scalesValues'][$externalScaleTypeId];
                            $operationalInstanceRiskScale->setBrutValue($scalesValueData['brutValue']);
                            $operationalInstanceRiskScale->setNetValue($scalesValueData['netValue']);
                            $operationalInstanceRiskScale->setTargetedValue($scalesValueData['targetedValue']);
                            if ($areImpactScaleTypesValuesDifferent) {
                                /* We convert from the importing new scales to the current anr scales. */
                                $this->adjustOperationalInstanceRisksScales(
                                    $operationalInstanceRiskScale,
                                    $externalOperationalRiskScalesData[OperationalRiskScale::TYPE_IMPACT],
                                    $impactScale
                                );
                            }
                        }
                    /* The format before v2.11.0. Update only first 5 scales (ROLFP if not changed by user). */
                    } elseif ($index < 5) {
                        foreach ($oldInstanceRiskFieldsMapToScaleTypesFields[$index] as $oldFiled => $typeField) {
                            $operationalInstanceRiskScale->{'set' . $typeField}($operationalRiskData[$oldFiled]);
                        }
                        if ($areImpactScaleTypesValuesDifferent) {
                            /* We convert from the importing new scales to the current anr scales. */
                            $this->adjustOperationalInstanceRisksScales(
                                $operationalInstanceRiskScale,
                                $externalOperationalRiskScalesData[OperationalRiskScale::TYPE_IMPACT],
                                $impactScale
                            );
                        }
                    }
                }

                $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
            }

            if (!empty($matchedScaleTypesMap['notMatchedScaleTypes']) && !$this->isImportTypeAnr()) {
                /* In case of instance import, there is a need to create external scale types in case if
                    the linked values are set for at least one operational instance risk.
                    The new created type has to be linked with all the existed risks. */
                $anrLanguageCode = $this->getAnrLanguageCode($anr);
                foreach ($matchedScaleTypesMap['notMatchedScaleTypes'] as $extScaleTypeId => $extScaleTypeData) {
                    if (isset($operationalRiskData['scalesValues'][$extScaleTypeId])) {
                        $scalesValueData = $operationalRiskData['scalesValues'][$extScaleTypeId];
                        if ($scalesValueData['netValue'] !== -1
                            || $scalesValueData['brutValue'] !== -1
                            || $scalesValueData['targetedValue'] !== -1
                        ) {
                            $labelTranslationKey = (string)Uuid::uuid4();
                            $operationalRiskScaleType = (new OperationalRiskScaleType())
                                ->setAnr($anr)
                                ->setOperationalRiskScale($impactScale['object'])
                                ->setLabelTranslationKey($labelTranslationKey)
                                ->setCreator($this->connectedUser->getEmail());
                            $this->operationalRiskScaleTypeTable->save($operationalRiskScaleType, false);

                            $translation = (new Translation())
                                ->setAnr($anr)
                                ->setType(OperationalRiskScaleType::TRANSLATION_TYPE_NAME)
                                ->setKey($labelTranslationKey)
                                ->setValue($extScaleTypeData['translation']['value'])
                                ->setLang($anrLanguageCode)
                                ->setCreator($this->connectedUser->getEmail());
                            $this->translationTable->save($translation, false);

                            foreach ($extScaleTypeData['operationalRiskScaleComments'] as $scaleCommentData) {
                                $this->createOrUpdateOperationalRiskScaleComment(
                                    $anr,
                                    false,
                                    $impactScale['object'],
                                    $scaleCommentData,
                                    [],
                                    [],
                                    $operationalRiskScaleType
                                );
                            }

                            $operationalInstanceRiskScale = (new OperationalInstanceRiskScale())
                                ->setAnr($anr)
                                ->setOperationalInstanceRisk($operationalInstanceRisk)
                                ->setOperationalRiskScaleType($operationalRiskScaleType)
                                ->setBrutValue($scalesValueData['brutValue'])
                                ->setNetValue($scalesValueData['netValue'])
                                ->setTargetedValue($scalesValueData['targetedValue'])
                                ->setCreator($this->connectedUser->getEmail());
                            $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale);

                            $this->adjustOperationalInstanceRisksScales(
                                $operationalInstanceRiskScale,
                                $externalOperationalRiskScalesData[OperationalRiskScale::TYPE_IMPACT],
                                $impactScale
                            );

                            /* To swap the scale risk between the to keys in the map as it is already matched. */
                            unset($matchedScaleTypesMap['notMatchedScaleTypes'][$extScaleTypeId]);
                            $matchedScaleTypesMap['currentScaleTypeKeysToExternalIds']
                            [$operationalRiskScaleType->getLabelTranslationKey()] = $extScaleTypeId;

                            /* Due to the new scale type and related comments the cache has to be reset. */
                            $this->cachedData['currentOperationalRiskScalesData'] = [];
                            $operationalRiskScalesData = $this->getCurrentOperationalRiskScalesData($anr);
                            $areImpactScaleTypesValuesDifferent = true;

                            /* Link the newly created scale type to all the existed operational risks. */
                            $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrAndInstance(
                                $anr,
                                $instance
                            );
                            foreach ($operationalInstanceRisks as $operationalInstanceRiskToUpdate) {
                                if ($operationalInstanceRiskToUpdate->getId() !== $operationalInstanceRisk->getId()) {
                                    $operationalInstanceRiskScale = (new OperationalInstanceRiskScale())
                                        ->setAnr($anr)
                                        ->setOperationalInstanceRisk($operationalInstanceRiskToUpdate)
                                        ->setOperationalRiskScaleType($operationalRiskScaleType)
                                        ->setCreator($this->connectedUser->getEmail());
                                    $this->operationalInstanceRiskScaleTable->save(
                                        $operationalInstanceRiskScale,
                                        false
                                    );
                                }
                            }
                        }
                    }
                }
            }

            if ($includeEval) {
                /* recalculate the cached risk values */
                $this->anrInstanceRiskOpService->updateRiskCacheValues($operationalInstanceRisk, false);
            }

            $this->instanceRiskOpTable->saveEntity($operationalInstanceRisk, false);

            /* Process recommendations related to the operational risk. */
            if ($includeEval && !empty($data['recosop'][$operationalRiskData['id']])) {
                foreach ($data['recosop'][$operationalRiskData['id']] as $recommendationData) {
                    $recommendation = $this->processRecommendationDataLinkedToRisk(
                        $anr,
                        $recommendationData,
                        $operationalRiskData['kindOfMeasure'] !== InstanceRiskOpSuperClass::KIND_NOT_TREATED
                    );

                    $recommendationRisk = (new RecommandationRisk())
                        ->setInstance($instance)
                        ->setInstanceRiskOp($operationalInstanceRisk)
                        ->setGlobalObject($monarcObject->isScopeGlobal() ? $monarcObject : null)
                        ->setCommentAfter($recommendationData['commentAfter'] ?? '')
                        ->setRecommandation($recommendation);

                    // TODO: remove the trick when #240 is done.
                    $this->recommendationRiskTable->saveEntity($recommendationRisk);
                    $this->recommendationRiskTable->saveEntity($recommendationRisk->setAnr($anr), false);
                }
            }

            $this->recommendationRiskTable->getDb()->flush();
        }
    }

    private function matchAndGetOperationalRiskScaleTypesMap(
        AnrSuperClass $anr,
        array $operationalRiskScalesData,
        array $externalOperationalRiskScalesData
    ): array {
        $matchedScaleTypesMap = [
            'currentScaleTypeKeysToExternalIds' => [],
            'notMatchedScaleTypes' => [],
        ];
        $anrLanguageCode = $this->getAnrLanguageCode($anr);
        $scaleTypesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScaleType::TRANSLATION_TYPE_NAME],
            $anrLanguageCode
        );
        $scaleTypesData = $operationalRiskScalesData[OperationalRiskScale::TYPE_IMPACT]
        ['operationalRiskScaleTypes'];
        $externalScaleTypesData =
            $externalOperationalRiskScalesData[OperationalRiskScale::TYPE_IMPACT]['operationalRiskScaleTypes'];
        foreach ($externalScaleTypesData as $externalScaleTypeData) {
            $isMatched = false;
            foreach ($scaleTypesData as $scaleTypeData) {
                /** @var OperationalRiskScaleType $scaleType */
                $scaleType = $scaleTypeData['object'];
                $scaleTypeTranslation = $scaleTypesTranslations[$scaleType->getLabelTranslationKey()];
                if ($externalScaleTypeData['translation']['value'] === $scaleTypeTranslation->getValue()) {
                    $matchedScaleTypesMap['currentScaleTypeKeysToExternalIds'][$scaleType->getLabelTranslationKey()]
                        = $externalScaleTypeData['id'];
                    $isMatched = true;
                    break;
                }
            }
            if (!$isMatched) {
                $matchedScaleTypesMap['notMatchedScaleTypes'][$externalScaleTypeData['id']] = $externalScaleTypeData;
            }
        }

        return $matchedScaleTypesMap;
    }

    private function getExternalScaleTypeIdByCurrentScaleLabelTranslationKey(string $labelTranslationKey): ?int
    {
        return $this->cachedData['operationalRiskScaleTypes']['currentScaleTypeLabelTranslationKeyToExternalIds']
            [$labelTranslationKey] ?? null;
    }

    private function areScalesLevelsOfTypeDifferent(
        int $type,
        array $operationalRiskScales,
        array $externalOperationalRiskScalesData
    ): bool {
        foreach ($operationalRiskScales as $scaleType => $operationalRiskScale) {
            if ($scaleType === $type) {
                $externalScaleDataOfType = $externalOperationalRiskScalesData[$type];
                if ($operationalRiskScale['min'] !== $externalScaleDataOfType['min']
                    || $operationalRiskScale['max'] !== $externalScaleDataOfType['max']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks if any of the scale comments values related to the scale types have different scaleValue
     * then in the new operational scale data.
     */
    public function areScaleTypeValuesDifferent(
        int $type,
        array $operationalRiskScales,
        array $extOperationalRiskScalesData
    ): bool {
        foreach ($operationalRiskScales[$type]['commentsIndexToValueMap'] as $scaleIndex => $scaleValue) {
            if (!isset($extOperationalRiskScalesData[$type]['commentsIndexToValueMap'][$scaleIndex])) {
                return true;
            }
            $extScaleValue = $extOperationalRiskScalesData[$type]['commentsIndexToValueMap'][$scaleIndex];
            if ($scaleValue !== $extScaleValue) {
                return true;
            }
        }

        return false;
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
    ): InstanceRisk {
        /** @var InstanceRisk $instanceRisk */
        $instanceRisk = (new InstanceRisk())
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
            ->setContext($instanceRiskData['context'] ?? '')
            ->setCreator($this->connectedUser->getEmail());

        if (!empty($instanceRiskData['riskOwner'])) {
            $instanceRiskOwner = $this->anrInstanceRiskService->getOrCreateInstanceRiskOwner(
                $anr,
                $instanceRiskData['riskOwner']
            );
            $instanceRisk->setInstanceRiskOwner($instanceRiskOwner);
        }

        return $instanceRisk;
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
    ): Instance {
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
            $currentOperationalRiskScalesData = $this->getCurrentOperationalRiskScalesData($anr);
            $externalOperationalRiskScalesData = $this->getExternalOperationalRiskScalesData($anr, $data);

            foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
                $this->adjustOperationalRisksProbabilityScales(
                    $operationalInstanceRisk,
                    $currentOperationalRiskScalesData[OperationalRiskScale::TYPE_LIKELIHOOD],
                    $externalOperationalRiskScalesData[OperationalRiskScale::TYPE_LIKELIHOOD]
                );

                foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $instanceRiskScale) {
                    $this->adjustOperationalInstanceRisksScales(
                        $instanceRiskScale,
                        $currentOperationalRiskScalesData[OperationalRiskScale::TYPE_IMPACT],
                        $externalOperationalRiskScalesData[OperationalRiskScale::TYPE_IMPACT]
                    );
                }

                $this->instanceRiskOpTable->saveEntity($operationalInstanceRisk, false);

                $this->anrInstanceRiskOpService->updateRiskCacheValues($operationalInstanceRisk);
            }

            $this->instanceRiskOpTable->getDb()->flush();
        }
    }

    private function adjustOperationalRisksProbabilityScales(
        InstanceRiskOp $operationalInstanceRisk,
        array $fromOperationalRiskScalesData,
        array $toOperationalRiskScalesData
    ): void {
        foreach (['NetProb', 'BrutProb', 'TargetedProb'] as $likelihoodScaleName) {
            $operationalInstanceRisk->{'set' . $likelihoodScaleName}($this->approximate(
                $operationalInstanceRisk->{'get' . $likelihoodScaleName}(),
                $fromOperationalRiskScalesData['min'],
                $fromOperationalRiskScalesData['max'],
                $toOperationalRiskScalesData['min'],
                $toOperationalRiskScalesData['max']
            ));
        }
    }

    private function adjustOperationalInstanceRisksScales(
        OperationalInstanceRiskScale $instanceRiskScale,
        array $fromOperationalRiskScalesData,
        array $toOperationalRiskScalesData
    ): void {
        foreach (['NetValue', 'BrutValue', 'TargetedValue'] as $impactScaleName) {
            $scaleImpactValue = $instanceRiskScale->{'get' . $impactScaleName}();
            if ($scaleImpactValue === -1) {
                continue;
            }
            $scaleImpactIndex = array_search(
                $scaleImpactValue,
                $fromOperationalRiskScalesData['commentsIndexToValueMap'],
                true
            );
            if ($scaleImpactIndex === false) {
                continue;
            }

            $approximatedIndex = $this->approximate(
                $scaleImpactIndex,
                $fromOperationalRiskScalesData['min'],
                $fromOperationalRiskScalesData['max'],
                $toOperationalRiskScalesData['min'],
                $toOperationalRiskScalesData['max']
            );

            $approximatedValueToNewScales = $toOperationalRiskScalesData['commentsIndexToValueMap'][$approximatedIndex]
                ?? $scaleImpactValue;
            $instanceRiskScale->{'set' . $impactScaleName}($approximatedValueToNewScales);

            $this->operationalInstanceRiskScaleTable->save($instanceRiskScale, false);
        }
    }

    private function getCurrentOperationalRiskScalesData(AnrSuperClass $anr): array
    {
        if (empty($this->cachedData['currentOperationalRiskScalesData'])) {
            $operationalRisksScales = $this->operationalRiskScaleTable->findByAnr($anr);
            foreach ($operationalRisksScales as $operationalRisksScale) {
                $scaleTypesData = [];
                $commentsIndexToValueMap = [];
                /* Build the map of the comments index <=> values relation. */
                foreach ($operationalRisksScale->getOperationalRiskScaleTypes() as $typeIndex => $scaleType) {
                    /* The operational risk scale types object is used to recreate operational instance risk scales. */
                    $scaleTypesData[$typeIndex]['object'] = $scaleType;
                    /* All the scale comment have the same index -> value corresponding values, so populating once. */
                    if (empty($commentsIndexToValueMap)) {
                        foreach ($scaleType->getOperationalRiskScaleComments() as $scaleTypeComment) {
                            if (!$scaleTypeComment->isHidden()) {
                                $commentsIndexToValueMap[$scaleTypeComment->getScaleIndex()] =
                                    $scaleTypeComment->getScaleValue();
                            }
                        }
                    }
                }

                $this->cachedData['currentOperationalRiskScalesData'][$operationalRisksScale->getType()] = [
                    'min' => $operationalRisksScale->getMin(),
                    'max' => $operationalRisksScale->getMax(),
                    'object' => $operationalRisksScale,
                    'commentsIndexToValueMap' => $commentsIndexToValueMap,
                    'operationalRiskScaleTypes' => $scaleTypesData,
                    'operationalRiskScaleComments' => $operationalRisksScale->getOperationalRiskScaleComments(),
                ];
            }
        }

        return $this->cachedData['currentOperationalRiskScalesData'];
    }

    /**
     * Prepare and cache the new scales for the future use.
     * The format can be different, depends on the version (before v2.11.0 and after).
     */
    private function getExternalOperationalRiskScalesData(AnrSuperClass $anr, array $data): array
    {
        if (empty($this->cachedData['externalOperationalRiskScalesData'])) {
            /* Populate with informational risks scales in case if there is an import of file before v2.11.0. */
            $scalesDataResult = [
                OperationalRiskScale::TYPE_IMPACT => [
                    'min' => 0,
                    'max' => $data['scales'][Scale::TYPE_IMPACT]['max'] - $data['scales'][Scale::TYPE_IMPACT]['min'],
                    'commentsIndexToValueMap' => [],
                    'operationalRiskScaleTypes' => [],
                    'operationalRiskScaleComments' => [],
                ],
                OperationalRiskScale::TYPE_LIKELIHOOD => [
                    'min' => $data['scales'][Scale::TYPE_THREAT]['min'],
                    'max' => $data['scales'][Scale::TYPE_THREAT]['max'],
                    'commentsIndexToValueMap' => [],
                    'operationalRiskScaleTypes' => [],
                    'operationalRiskScaleComments' => [],
                ],
            ];
            if (!empty($data['operationalRiskScales'])) {
                /* Overwrite the values for the version >= 2.10.5. */
                foreach ($data['operationalRiskScales'] as $scaleType => $operationalRiskScaleData) {
                    $scalesDataResult[$scaleType]['min'] = $operationalRiskScaleData['min'];
                    $scalesDataResult[$scaleType]['max'] = $operationalRiskScaleData['max'];

                    /* Build the map of the comments index <=> values relation. */
                    foreach ($operationalRiskScaleData['operationalRiskScaleTypes'] as $typeIndex => $scaleTypeData) {
                        $scalesDataResult[$scaleType]['operationalRiskScaleTypes'][$typeIndex] = $scaleTypeData;
                        /* All the scale comment have the same index->value corresponding values, so populating once. */
                        if (empty($scalesDataResult[$scaleType]['commentsIndexToValueMap'])) {
                            foreach ($scaleTypeData['operationalRiskScaleComments'] as $scaleTypeComment) {
                                if (!$scaleTypeComment['isHidden']) {
                                    $scalesDataResult[$scaleType]['commentsIndexToValueMap']
                                    [$scaleTypeComment['scaleIndex']] = $scaleTypeComment['scaleValue'];
                                }
                            }
                        }
                    }

                    $scalesDataResult[$scaleType]['operationalRiskScaleComments'] =
                        $operationalRiskScaleData['operationalRiskScaleComments'];
                }
            } else {
                /* Convert comments and types from informational risks to operational (new format). */
                $anrLanguageCode = $this->getAnrLanguageCode($anr);
                $scaleMin = $data['scales'][Scale::TYPE_IMPACT]['min'];
                foreach ($this->scaleImpactTypeTable->findByAnrOrderedByPosition($anr) as $index => $scaleImpactType) {
                    if ($scaleImpactType->isSys()
                        && \in_array($scaleImpactType->getType(), ScaleImpactType::getScaleImpactTypesRolfp(), true)
                    ) {
                        $labelTranslationKey = (string)Uuid::uuid4();
                        $scalesDataResult[Scale::TYPE_IMPACT]['operationalRiskScaleTypes'][$index] = [
                            'id' => $scaleImpactType->getId(),
                            'isHidden' => $scaleImpactType->isHidden(),
                            'labelTranslationKey' => $labelTranslationKey,
                            'translation' => [
                                'key' => $labelTranslationKey,
                                'lang' => $anrLanguageCode,
                                'value' => $scaleImpactType->getLabel($anr->getLanguage()),
                            ],
                        ];
                    }
                }
                foreach ($data['scalesComments'] as $scaleComment) {
                    $scaleType = $scaleComment['scale']['type'];
                    if (!\in_array($scaleType, [Scale::TYPE_IMPACT, Scale::TYPE_THREAT], true)) {
                        continue;
                    }

                    if ($scaleType === Scale::TYPE_THREAT) {
                        $commentTranslationKey = (string)Uuid::uuid4();
                        $scalesDataResult[$scaleType]['operationalRiskScaleComments'][] = [
                            'id' => $scaleComment['id'],
                            'scaleIndex' => $scaleComment['val'],
                            'scaleValue' => $scaleComment['val'],
                            'isHidden' => false,
                            'commentTranslationKey' => $commentTranslationKey,
                            'translation' => [
                                'key' => $commentTranslationKey,
                                'lang' => $anrLanguageCode,
                                'value' => $scaleComment['comment' . $anr->getLanguage()] ?? '',
                            ],
                        ];
                    } elseif ($scaleType === Scale::TYPE_IMPACT && $scaleComment['val'] >= $scaleMin) {
                        $commentTranslationKey = (string)Uuid::uuid4();
                        $scaleIndex = $scaleComment['val'] - $scaleMin;
                        $scaleTypePosition = $scaleComment['scaleImpactType']['position'];
                        if (isset($scalesDataResult[$scaleType]['operationalRiskScaleTypes'][$scaleTypePosition])) {
                            $scalesDataResult[$scaleType]['operationalRiskScaleTypes'][$scaleTypePosition]
                            ['operationalRiskScaleComments'][] = [
                                'id' => $scaleComment['id'],
                                'scaleIndex' => $scaleIndex,
                                'scaleValue' => $scaleComment['val'],
                                'isHidden' => false,
                                'commentTranslationKey' => $commentTranslationKey,
                                'translation' => [
                                    'key' => $commentTranslationKey,
                                    'lang' => $anrLanguageCode,
                                    'value' => $scaleComment['comment' . $anr->getLanguage()] ?? '',
                                ],
                            ];

                            $scalesDataResult[$scaleType]['commentsIndexToValueMap'][$scaleIndex]
                                = $scaleComment['val'];
                        }
                    }
                }
            }

            $this->cachedData['externalOperationalRiskScalesData'] = $scalesDataResult;
        }

        return $this->cachedData['externalOperationalRiskScalesData'];
    }

    private function updateScalesAndComments(AnrSuperClass $anr, array $data): void
    {
        $scalesByType = [];
        $scales = $this->scaleTable->findByAnr($anr);
        foreach ([Scale::TYPE_IMPACT, Scale::TYPE_THREAT, Scale::TYPE_VULNERABILITY] as $type) {
            foreach ($scales as $scale) {
                if ($scale->getType() === $type) {
                    $scale->setMin((int)$data['scales'][$type]['min']);
                    $scale->setMax((int)$data['scales'][$type]['max']);

                    $scalesByType[$type] = $scale;

                    $this->scaleTable->saveEntity($scale, false);
                }
            }
        }

        if (!empty($data['scalesComments'])) {
            $scaleComments = $this->scaleCommentTable->findByAnr($anr);
            foreach ($scaleComments as $scaleComment) {
                if ($scaleComment->getScaleImpactType() === null
                    || $scaleComment->getScaleImpactType()->isSys()
                ) {
                    $this->scaleCommentTable->deleteEntity($scaleComment, false);
                }
            }
            $this->scaleCommentTable->getDb()->flush();

            $scaleImpactTypes = $this->scaleImpactTypeTable->findByAnrOrderedAndIndexedByPosition($anr);
            $scaleImpactTypeMaxPosition = $this->scalesImpactTypeTable->findMaxPositionByAnrAndScale(
                $anr,
                $scalesByType[Scale::TYPE_IMPACT]
            );
            foreach ($data['scalesComments'] as $scalesCommentData) {
                /*
                 * Comments, which are not matched with a scale impact type, should not be created.
                 * This is possible only for exported files before v2.11.0.
                 */
                if (isset($scalesCommentData['scaleImpactType'])
                    && !isset($scaleImpactTypes[$scalesCommentData['scaleImpactType']['position']])
                    && !isset($scalesCommentData['scaleImpactType']['labels'])
                ) {
                    continue;
                }

                $scale = $scalesByType[$scalesCommentData['scale']['type']];
                $scaleComment = (new ScaleComment())
                    ->setAnr($anr)
                    ->setScale($scale)
                    ->setScaleIndex($scalesCommentData['scaleIndex'] ?? $scalesCommentData['val'])
                    ->setScaleValue($scalesCommentData['scaleValue'] ?? $scalesCommentData['val'])
                    ->setComments([
                        'comment1' => $scalesCommentData['comment1'],
                        'comment2' => $scalesCommentData['comment2'],
                        'comment3' => $scalesCommentData['comment3'],
                        'comment4' => $scalesCommentData['comment4'],
                    ])
                    ->setCreator($this->connectedUser->getEmail());

                if (isset($scalesCommentData['scaleImpactType']['position'])) {
                    $scaleImpactTypePosition = $scalesCommentData['scaleImpactType']['position'];
                    $scaleImpactType = $scaleImpactTypes[$scaleImpactTypePosition] ?? null;
                    $isSystem = $scaleImpactType !== null && $scaleImpactType->isSys();
                    /* Scale impact types are presented in the export separately since v2.11.0 */
                    if (isset($scalesCommentData['scaleImpactType']['labels'])
                        && !$isSystem
                        && ($scaleImpactType === null
                            || $scaleImpactType->getLabel($anr->getLanguage())
                            !== $scalesCommentData['scaleImpactType']['labels']['label' . $anr->getLanguage()]
                        )
                    ) {
                        $scaleImpactType = (new ScaleImpactType())
                            ->setType($scalesCommentData['scaleImpactType']['type'])
                            ->setLabels($scalesCommentData['scaleImpactType']['labels'])
                            ->setIsSys($scalesCommentData['scaleImpactType']['isSys'])
                            ->setIsHidden($scalesCommentData['scaleImpactType']['isHidden'])
                            ->setAnr($anr)
                            ->setScale($scale)
                            ->setPosition(++$scaleImpactTypeMaxPosition)
                            ->setCreator($this->connectedUser->getEmail());

                        $this->scalesImpactTypeTable->saveEntity($scaleImpactType, false);

                        $scaleImpactTypes[$scaleImpactTypePosition] = $scaleImpactType;
                    }
                    if ($scaleImpactType === null) {
                        continue;
                    }

                    /* We may overwrite the comments if position is matched but scale type labels are different */
                    $scaleComment->setScaleImpactType($scaleImpactType);
                }

                $this->scaleCommentTable->saveEntity($scaleComment, false);
            }
            $this->scaleCommentTable->getDb()->flush();
        }

        /* Reset the cache */
        $this->cachedData['scales'] = [];
    }

    private function updateOperationalRisksScalesAndRelatedInstances(AnrSuperClass $anr, array $data): void
    {
        $operationalRiskScales = $this->operationalRiskScaleTable->findByAnr($anr);
        $anrLanguageCode = $this->getAnrLanguageCode($anr);
        $scalesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScaleType::TRANSLATION_TYPE_NAME, OperationalRiskScaleComment::TRANSLATION_TYPE_NAME],
            $anrLanguageCode
        );
        $externalOperationalScalesData = $this->getExternalOperationalRiskScalesData($anr, $data);

        foreach ($operationalRiskScales as $operationalRiskScale) {
            $scaleData = $externalOperationalScalesData[$operationalRiskScale->getType()];
            $currentScaleLevelDifferenceFromExternal = $operationalRiskScale->getMax() - $scaleData['max'];
            $operationalRiskScale
                ->setAnr($anr)
                ->setMin($scaleData['min'])
                ->setMax($scaleData['max'])
                ->setUpdater($this->connectedUser->getEmail());

            /* This is currently only applicable for impact scales type. */
            $createdScaleTypes = [];
            $matchedScaleTypes = [];
            foreach ($scaleData['operationalRiskScaleTypes'] as $scaleTypeData) {
                $isScaleTypeMatched = true;
                $operationalRiskScaleType = $this->matchScaleTypeDataWithScaleTypesList(
                    $scaleTypeData,
                    $operationalRiskScale->getOperationalRiskScaleTypes(),
                    $scalesTranslations
                );
                if ($operationalRiskScaleType === null) {
                    $isScaleTypeMatched = false;
                    $labelTranslationKey = (string)Uuid::uuid4();
                    $operationalRiskScaleType = (new OperationalRiskScaleType())
                        ->setAnr($anr)
                        ->setOperationalRiskScale($operationalRiskScale)
                        ->setLabelTranslationKey($labelTranslationKey)
                        ->setCreator($this->connectedUser->getEmail());

                    $translation = (new Translation())
                        ->setAnr($anr)
                        ->setType(OperationalRiskScaleType::TRANSLATION_TYPE_NAME)
                        ->setLang($anrLanguageCode)
                        ->setKey($labelTranslationKey)
                        ->setValue($scaleTypeData['translation']['value'])
                        ->setCreator($this->connectedUser->getEmail());
                    $this->translationTable->save($translation, false);

                    $createdScaleTypes[$labelTranslationKey] = $operationalRiskScaleType;
                } elseif ($currentScaleLevelDifferenceFromExternal !== 0) {
                    $matchedScaleTypes[$operationalRiskScaleType->getId()] = $operationalRiskScaleType;
                }

                /* The map is used to match for the importing operational risks, scale values with scale types. */
                $this->cachedData['operationalRiskScaleTypes']['currentScaleTypeLabelTranslationKeyToExternalIds']
                [$operationalRiskScaleType->getLabelTranslationKey()] = $scaleTypeData['id'];

                $operationalRiskScaleType->setIsHidden($scaleTypeData['isHidden']);
                $this->operationalRiskScaleTypeTable->save($operationalRiskScaleType, false);

                foreach ($scaleTypeData['operationalRiskScaleComments'] as $scaleTypeCommentData) {
                    $this->createOrUpdateOperationalRiskScaleComment(
                        $anr,
                        $isScaleTypeMatched,
                        $operationalRiskScale,
                        $scaleTypeCommentData,
                        $operationalRiskScaleType->getOperationalRiskScaleComments(),
                        $scalesTranslations,
                        $operationalRiskScaleType
                    );
                }
            }

            /* Create relations of all the created scales with existed risks. */
            if (!empty($createdScaleTypes)) {
                $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnr($anr);
                foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
                    foreach ($createdScaleTypes as $createdScaleType) {
                        $operationalInstanceRiskScale = (new OperationalInstanceRiskScale())
                            ->setAnr($anr)
                            ->setOperationalRiskScaleType($createdScaleType)
                            ->setOperationalInstanceRisk($operationalInstanceRisk)
                            ->setCreator($this->connectedUser->getEmail());
                        $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
                    }
                }
            }

            $maxIndexForLikelihood = 0;
            /* This is currently applicable only for likelihood scales type */
            foreach ($scaleData['operationalRiskScaleComments'] as $scaleCommentData) {
                $this->createOrUpdateOperationalRiskScaleComment(
                    $anr,
                    true,
                    $operationalRiskScale,
                    $scaleCommentData,
                    $operationalRiskScale->getOperationalRiskScaleComments(),
                    $scalesTranslations
                );
                $maxIndexForLikelihood = (int)$scaleCommentData['scaleIndex']>$maxIndexForLikelihood?(int)$scaleCommentData['scaleIndex']:$maxIndexForLikelihood;
            }
            // manage case when the scale (probability) is not matched and level higher than external
            if($operationalRiskScale->getType() === OperationalRiskScale::TYPE_LIKELIHOOD && $maxIndexForLikelihood!==0)
            {
                foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $comment) {
                    if((int)$comment->getScaleIndex()>=$maxIndexForLikelihood)
                    {
                        $comment->setIsHidden(true);
                        $this->operationalRiskScaleCommentTable->save($comment, false);
                    }
                }
            }

            /* Validate if any existed comments are now out of the new scales bound and if their values are valid.
                Also, if their comments are complete per scale's level. */
            if ($currentScaleLevelDifferenceFromExternal !== 0) {
                foreach ($operationalRiskScale->getOperationalRiskScaleTypes() as $operationalRiskScaleType) {
                    /* Ignore the currently created scale types. */
                    if (\array_key_exists($operationalRiskScaleType->getLabelTranslationKey(), $createdScaleTypes)) {
                        continue;
                    }

                    if ($currentScaleLevelDifferenceFromExternal < 0
                        && !\array_key_exists($operationalRiskScaleType->getId(), $matchedScaleTypes)
                    ) {
                        /* The scales type was not matched and the current scales level is lower then external,
                            so we need to create missing empty scales comments. */
                        $commentIndex = $operationalRiskScale->getMax() + $currentScaleLevelDifferenceFromExternal + 1;
                        $commentIndexToValueMap = $externalOperationalScalesData[OperationalRiskScale::TYPE_IMPACT]
                        ['commentsIndexToValueMap'];
                        while ($commentIndex <= $operationalRiskScale->getMax()) {
                            $this->createOrUpdateOperationalRiskScaleComment(
                                $anr,
                                false,
                                $operationalRiskScale,
                                [
                                    'scaleIndex' => $commentIndex,
                                    'scaleValue' => $commentIndexToValueMap[$commentIndex],
                                    'isHidden' => false,
                                    'translation' => [
                                        'value' => '',
                                    ],
                                ],
                                [],
                                [],
                                $operationalRiskScaleType
                            );
                            $commentIndex++;
                        }

                        continue;
                    }

                    if ($currentScaleLevelDifferenceFromExternal > 0
                    ) {
                        /* The scales type was matched and the current scales level is higher then external,
                            so we need to hide their comments and validate values. */
                        $commentIndexToValueMap = $externalOperationalScalesData[OperationalRiskScale::TYPE_IMPACT]
                        ['commentsIndexToValueMap'];
                        $maxValue = $commentIndexToValueMap[$operationalRiskScale->getMax()];
                        if(\array_key_exists($operationalRiskScaleType->getId(), $matchedScaleTypes)){
                            foreach ($matchedScaleTypes as $matchedScaleType) {
                                foreach ($matchedScaleType->getOperationalRiskScaleComments() as $comment) {
                                    $isHidden = $operationalRiskScale->getMin() > $comment->getScaleIndex()
                                        || $operationalRiskScale->getMax() < $comment->getScaleIndex();
                                    $comment->setIsHidden($isHidden);
                                    if ($isHidden && $maxValue >= $comment->getScaleValue()) {
                                        $comment->setScaleValue(++$maxValue);
                                    }

                                    $this->operationalRiskScaleCommentTable->save($comment, false);
                                }
                            }
                        }
                        // manage case when the scale is not matched and level higher than external
                        if(!\array_key_exists($operationalRiskScaleType->getId(), $matchedScaleTypes)){
                            foreach ($operationalRiskScaleType->getOperationalRiskScaleComments() as $comment) {
                                $isHidden = $operationalRiskScale->getMin() > $comment->getScaleIndex()
                                    || $operationalRiskScale->getMax() < $comment->getScaleIndex();
                                $comment->setIsHidden($isHidden);
                                if ($isHidden && $maxValue >= $comment->getScaleValue()) {
                                    $comment->setScaleValue(++$maxValue);
                                }

                                $this->operationalRiskScaleCommentTable->save($comment, false);
                            }
                        }
                    }
                }
            }

            $this->operationalRiskScaleTable->save($operationalRiskScale);
        }

        /* Reset the cache */
        $this->cachedData['currentOperationalRiskScalesData'] = [];
    }

    private function createOrUpdateOperationalRiskScaleComment(
        AnrSuperClass $anr,
        bool $isMatchRequired,
        OperationalRiskScale $operationalRiskScale,
        array $scaleCommentData,
        iterable $scaleCommentsToMatchWith,
        array $scalesTranslations,
        ?OperationalRiskScaleType $operationalRiskScaleType = null
    ): void {
        $operationalRiskScaleComment = null;
        if ($isMatchRequired) {
            $operationalRiskScaleComment = $this->matchScaleCommentDataWithScaleCommentsList(
                $operationalRiskScale,
                $scaleCommentData,
                $scaleCommentsToMatchWith,
                $scalesTranslations
            );
        }
        if ($operationalRiskScaleComment === null) {
            $anrLanguageCode = $this->getAnrLanguageCode($anr);
            $commentTranslationKey = (string)Uuid::uuid4();
            $operationalRiskScaleComment = (new OperationalRiskScaleComment())
                ->setAnr($anr)
                ->setOperationalRiskScale($operationalRiskScale)
                ->setCommentTranslationKey($commentTranslationKey)
                ->setCreator($this->connectedUser->getEmail());

            $translation = (new Translation())
                ->setAnr($anr)
                ->setType(OperationalRiskScaleComment::TRANSLATION_TYPE_NAME)
                ->setLang($anrLanguageCode)
                ->setKey($commentTranslationKey)
                ->setValue($scaleCommentData['translation']['value'])
                ->setCreator($this->connectedUser->getEmail());
            $this->translationTable->save($translation, false);
        }

        if ($operationalRiskScaleType !== null) {
            $operationalRiskScaleComment->setOperationalRiskScaleType($operationalRiskScaleType);
        }

        $operationalRiskScaleComment
            ->setScaleIndex($scaleCommentData['scaleIndex'])
            ->setScaleValue($scaleCommentData['scaleValue'])
            ->setIsHidden($scaleCommentData['isHidden']);
        $this->operationalRiskScaleCommentTable->save($operationalRiskScaleComment, false);
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
            if (isset($scalesTranslations[$operationalRiskScaleType->getLabelTranslationKey()])) {
                $translation = $scalesTranslations[$operationalRiskScaleType->getLabelTranslationKey()];
                if ($translation->getValue() === $scaleTypeData['translation']['value']) {
                    return $operationalRiskScaleType;
                }
            }
        }

        return null;
    }

    /**
     * @param OperationalRiskScale $operationalRiskScale
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
        OperationalRiskScale $operationalRiskScale,
        array $scaleTypeCommentData,
        iterable $operationalRiskScaleComments,
        array $scalesTranslations
    ): ?OperationalRiskScaleComment {
        foreach ($operationalRiskScaleComments as $operationalRiskScaleComment) {
            if ($operationalRiskScale->getId() !== $operationalRiskScaleComment->getOperationalRiskScale()->getId()) {
                continue;
            }
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

    private function getAnrLanguageCode(AnrSuperClass $anr): string
    {
        return strtolower($this->configService->getLanguageCodes()[$anr->getLanguage()]);
    }

    private function fixImportMismatches(): void
    {
        if (!empty($this->cachedData['operationalRisksToFixRolfRisks'])) {
            $cachedRolfRisks = $this->objectImportService->getCachedDataByKey('rolfRisks');
            foreach ($this->cachedData['operationalRisksToFixRolfRisks'] as $operationalRiskToFixRolfRisk) {
                if (isset($cachedRolfRisks[$operationalRiskToFixRolfRisk['rolfRiskId']])) {
                    /** @var InstanceRiskOp $operationalInstanceRisk */
                    $operationalInstanceRisk = $operationalRiskToFixRolfRisk['operationalRisk'];
                    /** @var RolfRisk $rolfRisk */
                    $rolfRisk = $cachedRolfRisks[$operationalRiskToFixRolfRisk['rolfRiskId']];

                    $operationalInstanceRisk
                        ->setRolfRisk($rolfRisk)
                        ->setRiskCacheCode($rolfRisk->getCode());

                    $this->instanceRiskOpTable->saveEntity($operationalInstanceRisk, false);
                }
            }

            $this->instanceRiskOpTable->getDb()->flush();
        }
    }
}
