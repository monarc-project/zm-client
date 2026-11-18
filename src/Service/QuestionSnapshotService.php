<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\Table\QuestionTable;
use Monarc\FrontOffice\Table\QuestionChoiceTable;

class QuestionSnapshotService
{
    public function __construct(
        private QuestionTable $questionTable,
        private QuestionChoiceTable $questionChoiceTable
    ) {
    }

    /** Copy questions and linked choices without flushing; only text answers are copied. */
    public function copy(array $questions, Anr $targetAnr): void
    {
        foreach ($questions as $sourceQuestion) {
            $response = $sourceQuestion instanceof Question && $sourceQuestion->getType() === Question::TYPE_SINGLE
                ? $sourceQuestion->getResponse() : null;
            $question = (new Question())
                ->setAnr($targetAnr)
                ->setMode($sourceQuestion instanceof Question ? $sourceQuestion->getMode() : 0)
                ->setResponse($response)
                ->setLabels($sourceQuestion->getLabels())
                ->setType($sourceQuestion->getType())
                ->setIsMultiChoice($sourceQuestion->isMultiChoice())
                ->setPosition($sourceQuestion->getPosition())
                ->setCreator($sourceQuestion->getCreator())
                ->setUpdater($sourceQuestion->getUpdater());
            $this->questionTable->save($question, false);

            if ($sourceQuestion->getType() === Question::TYPE_CHOICE) {
                foreach ($sourceQuestion->getQuestionChoices() as $sourceChoice) {
                    $questionChoice = (new QuestionChoice())
                        ->setAnr($targetAnr)
                        ->setQuestion($question)
                        ->setLabels($sourceChoice->getLabels())
                        ->setPosition($sourceChoice->getPosition())
                        ->setCreator($sourceChoice->getCreator())
                        ->setUpdater($sourceChoice->getUpdater());
                    $this->questionChoiceTable->save($questionChoice, false);
                }
            }
        }
    }
}
