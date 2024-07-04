<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Table;

class AnrMethodStepImportProcessor
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\AnrTable $anrTable,
        private Table\DeliveryTable $deliveryTable,
        private DeprecatedTable\InterviewTable $interviewTable,
        private DeprecatedTable\QuestionTable $questionTable,
        private DeprecatedTable\QuestionChoiceTable $questionChoiceTable,
        private ThreatImportProcessor $threatImportProcessor,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function processAnrMethodStepsData(Entity\Anr $anr, array $methodStepsData): void
    {
        if (!empty($methodStepsData['questions'])) {
            /* The only place where the data is flushed, so has to be called first. */
            $this->processQuestionsData($anr, $methodStepsData['questions']);
        }

        /* Set method steps checkboxes. */
        if (!empty($methodStepsData['steps'])) {
            $anr->setInitAnrContext((int)$methodStepsData['steps']['initAnrContext'])
                ->setInitEvalContext((int)$methodStepsData['steps']['initEvalContext'])
                ->setInitRiskContext((int)$methodStepsData['steps']['initRiskContext'])
                ->setInitDefContext((int)$methodStepsData['steps']['initDefContext'])
                ->setModelImpacts((int)$methodStepsData['steps']['modelImpacts'])
                ->setModelSummary((int)$methodStepsData['steps']['modelSummary'])
                ->setEvalRisks((int)$methodStepsData['steps']['evalRisks'])
                ->setEvalPlanRisks((int)$methodStepsData['steps']['evalPlanRisks'])
                ->setManageRisks((int)$methodStepsData['steps']['manageRisks'])
                ->setUpdater($this->connectedUser->getEmail());
            $this->anrTable->save($anr, false);
        }

        /* Set data of text-boxes. */
        if (!empty($methodStepsData['data'])) {
            $anr->setContextAnaRisk($methodStepsData['data']['contextAnaRisk'])
                ->setContextGestRisk($methodStepsData['data']['contextGestRisk'])
                ->setSynthThreat($methodStepsData['data']['synthThreat'])
                ->setSynthAct($methodStepsData['data']['synthAct'])
                ->setUpdater($this->connectedUser->getEmail());
            $this->anrTable->save($anr, false);
        }

        /* Recreate the generated deliveries reports. */
        if (!empty($methodStepsData['deliveries'])) {
            foreach ($methodStepsData['deliveries'] as $deliveryData) {
                $delivery = (new Entity\Delivery())
                    ->setAnr($anr)
                    ->setName($deliveryData['name'])
                    ->setDocType($deliveryData['typedoc'])
                    ->setVersion($deliveryData['version'])
                    ->setStatus($deliveryData['status'])
                    ->setClassification($deliveryData['classification'])
                    ->setRespCustomer($deliveryData['respCustomer'])
                    ->setResponsibleManager($deliveryData['responsibleManager'] ?? $deliveryData['respSmile'])
                    ->setSummaryEvalRisk($deliveryData['summaryEvalRisk'])
                    ->setCreator($this->connectedUser->getEmail());
                $this->deliveryTable->save($delivery, false);
            }
        }

        if (!empty($methodStepsData['interviews'])) {
            $this->processInterviewsData($anr, $methodStepsData['interviews']);
        }

        if (!empty($methodStepsData['thresholds'])) {
            $this->processThresholdsData($anr, $methodStepsData['thresholds']);
        }

        /* Process the evaluation of threats. */
        if (!empty($methodStepsData['threats'])) {
            $this->threatImportProcessor->processThreatsData($anr, $methodStepsData['threats']);
        }
    }

    public function processThresholdsData(Entity\Anr $anr, array $thresholdsData): void
    {
        $anr->setSeuil1((int)$thresholdsData['seuil1'])
            ->setSeuil2((int)$thresholdsData['seuil2'])
            ->setSeuilRolf1((int)$thresholdsData['seuilRolf1'])
            ->setSeuilRolf2((int)$thresholdsData['seuilRolf2'])
            ->setUpdater($this->connectedUser->getEmail());
        $this->anrTable->save($anr, false);
    }

    public function processInterviewsData(Entity\Anr $anr, array $interviewsData): void
    {
        foreach ($interviewsData as $interviewData) {
            $newInterview = (new Entity\Interview())
                ->setAnr($anr)
                ->setDate($interviewData['date'])
                ->setContent($interviewData['content'])
                ->setService($interviewData['service'])
                ->setCreator($this->connectedUser->getEmail());
            $this->interviewTable->saveEntity($newInterview, false);
        }
    }

    private function processQuestionsData(Entity\Anr $anr, $questionsData): void
    {
        foreach ($this->questionTable->findByAnr($anr) as $question) {
            $this->questionTable->deleteEntity($question, false);
        }

        foreach ($questionsData as $position => $questionData) {
            /* In the new data structure there is only "label" field set. */
            if (isset($questionData['label'])) {
                $questionData['label' . $anr->getLanguage()] = $questionData['label'];
            }
            $question = (new Entity\Question())
                ->setAnr($anr)
                ->setLabels($questionData)
                ->setMode($questionData['mode'])
                ->setIsMultiChoice($questionData['isMultiChoice'] ?? (bool)$questionData['multichoice'])
                ->setType($questionData['type'])
                ->setResponse((string)$questionData['response'])
                ->setPosition($position)
                ->setCreator($this->connectedUser->getEmail());
            $this->questionTable->saveEntity($question, false);

            if ($question->isMultiChoice()) {
                $choicesOldIdsToNewObjects = [];
                /* Support the old structure format, prior v2.13.1 */
                $questionChoicesData = $methodStepsData['questionChoice'] ?? $questionData['questionChoices'];
                foreach ($questionChoicesData as $questionChoiceData) {
                    if (!isset($questionChoiceData['question'])
                        || $questionChoiceData['question'] === $questionData['id']
                    ) {
                        if (isset($questionChoiceData['label'])) {
                            $questionChoiceData['label' . $anr->getLanguage()] = $questionChoiceData['label'];
                        }
                        $questionChoice = (new Entity\QuestionChoice())->setAnr($anr)->setQuestion($question)
                            ->setLabels($questionChoiceData)->setPosition($questionChoiceData['position'])
                            ->setCreator($this->connectedUser->getEmail());
                        $this->questionChoiceTable->saveEntity($questionChoice, false);
                        $choicesOldIdsToNewObjects[$questionChoiceData['id']] = $questionChoice;
                    }
                }
                $response = trim($question->getResponse(), '[]');
                if ($response !== '') {
                    /* The flush is necessary as responses are stored as array of IDs from the exported DB.
                    TODO: refactor the responses saving in a separate table and avoid the flush operation here. */
                    $this->questionChoiceTable->getDb()->flush();
                    $originQuestionChoicesIds = explode(',', $response);
                    $questionChoicesIds = [];
                    foreach ($originQuestionChoicesIds as $originQuestionChoicesId) {
                        if (isset($choicesOldIdsToNewObjects[$originQuestionChoicesId])) {
                            $questionChoicesIds[] = $choicesOldIdsToNewObjects[$originQuestionChoicesId]->getId();
                        }
                    }
                    $question->setResponse('[' . implode(',', $questionChoicesIds) . ']');
                    $this->questionTable->save($question, false);
                }
            }
        }
    }
}
