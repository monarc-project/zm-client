<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\Table\QuestionTable;
use Monarc\FrontOffice\Table\QuestionChoiceTable;
use Monarc\FrontOffice\Validator\InputValidator\Question\QuestionSnapshotInputValidator;

class QuestionSnapshotService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private QuestionTable $questionTable,
        private QuestionChoiceTable $questionChoiceTable,
        private QuestionSnapshotInputValidator $questionSnapshotInputValidator,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /** Copies definitions and analysis answers into fresh identities, preserving all translated labels. */
    public function copy(array $questions, Anr $target): array
    {
        $questions = array_values($questions);
        $snapshot = ['questions' => []];
        foreach ($questions as $question) {
            $row = array_merge($question->getLabels(), [
                'id' => $question->getId(), 'type' => $question->getType(),
                'multichoice' => (int)$question->isMultiChoice(), 'position' => $question->getPosition(),
                'mode' => $question instanceof Question ? $question->getMode() : 0,
                'response' => $question instanceof Question ? $question->getResponse() : null,
                'questionChoices' => [],
            ]);
            foreach ($question->getQuestionChoices() as $choice) {
                $row['questionChoices'][] = array_merge($choice->getLabels(), [
                    'id' => $choice->getId(), 'position' => $choice->getPosition(),
                ]);
            }
            $snapshot['questions'][] = $row;
        }
        return $this->buildCopies(
            $this->questionSnapshotInputValidator->validate($snapshot, $target->getLanguage()),
            $target,
            $questions
        );
    }

    public function replace(Anr $anr, array $snapshot): void
    {
        $rows = $this->questionSnapshotInputValidator->validate($snapshot, $anr->getLanguage());
        $this->questionTable->beginTransaction();
        try {
            foreach ($this->questionTable->findOrderedQuestions($anr) as $question) {
                foreach ($this->questionChoiceTable->findChoicesOfQuestion($question) as $choice) {
                    $question->removeQuestionChoice($choice);
                    $this->questionChoiceTable->remove($choice, false);
                }
                $this->questionTable->remove($question, false);
            }
            $copies = $this->buildCopies($rows, $anr);
            $this->questionTable->flush();
            $this->completeCopy($copies);
            $this->questionTable->commit();
        } catch (\Throwable $error) {
            $this->questionTable->rollback();
            throw $error;
        }
    }

    /** Called after the caller's existing flush, when all copied entities have their new identifiers. */
    public function completeCopy(array $copies): void
    {
        $changed = false;
        foreach ($copies as $copy) {
            $question = $copy['question'];
            $response = $question->getResponse();
            if ($question->getType() !== 2 || $response === null || $response === '' || $response === 'null') {
                continue;
            }
            if ($question->isMultiChoice()) {
                $ids = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                $response = json_encode(
                    array_map(fn ($id): int => $copy['choices'][$id]->getId(), $ids),
                    JSON_THROW_ON_ERROR
                );
            } else {
                $response = (string)$copy['choices'][$response]->getId();
            }
            $question->setResponse($response);
            $changed = true;
        }
        if ($changed) {
            $this->questionTable->flush();
        }
    }

    private function buildCopies(array $rows, Anr $anr, array $sources = []): array
    {
        $copies = [];
        foreach ($rows as $index => $row) {
            $source = $sources[$index] ?? null;
            $question = (new Question())->setAnr($anr)->setLabels($row)->setType($row['type'])
                ->setIsMultiChoice((bool)$row['multichoice'])->setPosition($row['position'])
                ->setMode($row['mode'])->setResponse($row['response']);
            $question->setCreator($source === null ? $this->connectedUser->getEmail() : $source->getCreator());
            if ($source !== null) {
                $question->setUpdater($source->getUpdater());
            }
            $this->questionTable->save($question, false);
            $sourceChoices = [];
            if ($source !== null) {
                foreach ($source->getQuestionChoices() as $sourceChoice) {
                    $sourceChoices[$sourceChoice->getId()] = $sourceChoice;
                }
            }
            $choiceMap = [];
            foreach ($row['questionChoices'] as $oldId => $choiceRow) {
                $choice = (new QuestionChoice())->setAnr($anr)->setQuestion($question)
                    ->setLabels($choiceRow)->setPosition($choiceRow['position'] ?? count($choiceMap) + 1);
                $sourceChoice = $sourceChoices[$oldId] ?? null;
                $choice->setCreator(
                    $sourceChoice === null ? $this->connectedUser->getEmail() : $sourceChoice->getCreator()
                );
                if ($sourceChoice !== null) {
                    $choice->setUpdater($sourceChoice->getUpdater());
                }
                $this->questionChoiceTable->save($choice, false);
                $choiceMap[$oldId] = $choice;
            }
            $copies[] = ['question' => $question, 'choices' => $choiceMap];
        }
        return $copies;
    }
}
