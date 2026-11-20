<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Table\QuestionTable;
use Monarc\FrontOffice\Table\QuestionChoiceTable;

class QuestionImportProcessor
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private QuestionTable $questionTable,
        private QuestionChoiceTable $questionChoiceTable,
        private ImportCacheHelper $importCacheHelper,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function processQuestionsData(Anr $anr, array $snapshot): void
    {
        if (!isset($snapshot['questions']) || !is_array($snapshot['questions'])) {
            return;
        }
        $rows = $this->prepareSnapshot($snapshot, $anr->getLanguage());
        if ($rows === [] && $snapshot['questions'] !== []) {
            return;
        }

        // Preload the existing graph once; the remaining work uses objects and cached references.
        $questions = $this->questionTable->findWithChoicesByAnr($anr);
        foreach ($questions as $question) {
            foreach ($question->getQuestionChoices()->toArray() as $questionChoice) {
                $question->removeQuestionChoice($questionChoice);
                $this->questionChoiceTable->remove($questionChoice, false);
            }
            $this->questionTable->remove($question, false);
        }
        $this->importCacheHelper->setArrayCacheValue('imported_question_copies', $this->buildCopies($rows, $anr));
    }

    public function completeImport(): bool
    {
        $copies = $this->importCacheHelper->getValueFromArrayCache('imported_question_copies') ?? [];
        $this->importCacheHelper->setArrayCacheValue('imported_question_copies', []);
        $changed = false;
        foreach ($copies as $copy) {
            $question = $copy['question'];
            $response = $question->getResponse();
            if ($question->getType() !== Question::TYPE_CHOICE
                || $response === null || $response === '' || $response === 'null'
            ) {
                continue;
            }
            if ($question->isMultiChoice()) {
                $ids = json_decode($response, true);
                $mappedIds = [];
                foreach (is_array($ids) && array_is_list($ids) ? $ids : [] as $id) {
                    if ((is_int($id) || is_string($id)) && isset($copy['choices'][$id])) {
                        $mappedIds[] = $copy['choices'][$id]->getId();
                    }
                }
                $response = json_encode(array_values(array_unique($mappedIds)), JSON_THROW_ON_ERROR);
            } else {
                $choice = $copy['choices'][$response] ?? null;
                $response = $choice === null ? null : (string)$choice->getId();
            }
            $question->setResponse($response);
            $changed = true;
        }
        return $changed;
    }

    private function prepareSnapshot(array $snapshot, int $language): array
    {
        $rows = [];
        $seenQuestionReferences = [];
        foreach ($snapshot['questions'] as $position => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (array_key_exists('label', $row)) {
                $row['label' . $language] = $row['label'];
            }
            $questionReference = $row['id'] ?? $position;
            $row['position'] = $row['position'] ?? (array_is_list($snapshot['questions'])
                ? count($rows) + 1 : $position);
            $row['mode'] = $row['mode'] ?? 0;
            $row['multichoice'] = $row['isMultiChoice'] ?? $row['multichoice'] ?? 0;
            if (!in_array(
                (int)($row['type'] ?? Question::TYPE_SINGLE),
                [Question::TYPE_SINGLE, Question::TYPE_CHOICE],
                true
            )) {
                continue;
            }
            if ((!is_int($questionReference) && !is_string($questionReference))
                || isset($seenQuestionReferences[$questionReference])
            ) {
                continue;
            }
            $seenQuestionReferences[$questionReference] = true;
            $row['position'] = max(1, (int)$row['position']);
            $row += ['type' => Question::TYPE_SINGLE, 'response' => null];
            $sourceChoices = $snapshot['questionChoice'] ?? $row['questionChoices'] ?? [];
            if ((int)$row['type'] !== Question::TYPE_CHOICE || !is_array($sourceChoices)) {
                $sourceChoices = [];
            }
            $choiceRows = [];
            foreach ($sourceChoices as $choiceId => $choice) {
                if (!is_array($choice)) {
                    continue;
                }
                if (isset($snapshot['questionChoice']) && !isset($choice['question'])) {
                    continue;
                }
                if (isset($choice['question']) && $choice['question'] != $questionReference) {
                    continue;
                }
                if (array_key_exists('label', $choice)) {
                    $choice['label' . $language] = $choice['label'];
                }
                $choiceReference = $choice['id'] ?? $choiceId;
                if ((!is_int($choiceReference) && !is_string($choiceReference))
                    || isset($choiceRows[$choiceReference])
                ) {
                    continue;
                }
                $choice['position'] = max(1, (int)($choice['position'] ?? count($choiceRows) + 1));
                $choiceRows[$choiceReference] = $choice;
            }
            $row['questionChoices'] = $choiceRows;
            $rows[] = $row;
        }

        return $rows;
    }

    private function buildCopies(array $rows, Anr $anr): array
    {
        usort($rows, fn (array $left, array $right): int => $left['position'] <=> $right['position']);
        $copies = [];
        foreach ($rows as $position => $questionData) {
            $question = (new Question())
                ->setAnr($anr)
                ->setMode((int)$questionData['mode'])
                ->setResponse(isset($questionData['response']) ? (string)$questionData['response'] : null)
                ->setLabels($questionData)
                ->setType((int)$questionData['type'])
                ->setIsMultiChoice((bool)$questionData['multichoice'])
                ->setPosition($position + 1)
                ->setCreator($this->connectedUser->getEmail());
            $this->questionTable->save($question, false);

            $copiedChoices = [];
            foreach ($questionData['questionChoices'] as $oldId => $questionChoiceData) {
                $questionChoice = (new QuestionChoice())
                    ->setAnr($anr)
                    ->setQuestion($question)
                    ->setLabels($questionChoiceData)
                    ->setPosition($questionChoiceData['position'])
                    ->setCreator($this->connectedUser->getEmail());
                $this->questionChoiceTable->save($questionChoice, false);
                $copiedChoices[$oldId] = $questionChoice;
            }
            $copies[] = ['question' => $question, 'choices' => $copiedChoices];
        }

        return $copies;
    }
}
