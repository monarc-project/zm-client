<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use LogicException;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Traits\PositionUpdateTrait;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\Table\QuestionChoiceTable;
use Monarc\FrontOffice\Table\QuestionTable;

class AnrQuestionService
{
    use PositionUpdateTrait;

    private UserSuperClass $connectedUser;

    public function __construct(
        private QuestionTable $questionTable,
        private QuestionChoiceTable $questionChoiceTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        if (!$params->hasFilterFor('anr')) {
            throw new LogicException('Analysis filter is required');
        }

        return array_map(
            fn ($question): array => $this->serialize($question, true),
            $this->questionTable->findByParams($params)
        );
    }

    public function getCount(FormattedInputParams $params): int
    {
        if (!$params->hasFilterFor('anr')) {
            throw new LogicException('Analysis filter is required');
        }

        return $this->questionTable->countByParams($params);
    }

    public function getQuestionData(Anr $anr, int $id): array
    {
        return $this->serialize($this->questionTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(Question $question, bool $withChoices = false): array
    {
        $questionData = array_merge($this->content($question), [
            'type' => $question->getType(),
            'multichoice' => (int)$question->isMultiChoice(),
            // Legacy JSON represented the collection as an object; choices is the populated list field.
            'questionChoices' => new \stdClass(),
        ]);
        $questionData += ['mode' => $question->getMode(), 'response' => $question->getResponse()];

        if ($withChoices && $question->getType() === Question::TYPE_CHOICE) {
            $questionData['choices'] = array_map(function ($choice): array {
                return array_merge($this->content($choice), ['question' => new \stdClass()]);
            }, $this->questionChoiceTable->findChoicesOfQuestion($question));
        }

        return $questionData;
    }

    public function create(Anr $anr, array $data): Question
    {
        $question = (new Question())
            ->setAnr($anr)
            ->setMode(Question::MODE_LOCALLY_CREATED)
            ->setResponse($data['response'] ?? null)
            ->setType(Question::TYPE_SINGLE)
            ->setIsMultiChoice(false)
            ->setLabels($data)
            ->setCreator($this->connectedUser->getEmail());
        $this->updatePositions($question, $this->questionTable);
        $this->questionTable->save($question);

        return $question;
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        $question = $this->questionTable->findByIdAndAnr($id, $anr);
        if ($question->getMode() === Question::MODE_LOCALLY_CREATED) {
            $question->setLabels($data);
        }
        if (array_key_exists('response', $data)) {
            $question->setResponse($data['response']);
        }
        $question->setUpdater($this->connectedUser->getEmail());

        $this->questionTable->save($question);
    }

    public function delete(Anr $anr, int $id): void
    {
        $question = $this->questionTable->findByIdAndAnr($id, $anr);
        if ($question->getMode() !== Question::MODE_LOCALLY_CREATED) {
            throw new Exception('Delete question is not possible', 412);
        }
        foreach ($this->questionChoiceTable->findChoicesOfQuestion($question) as $questionChoice) {
            $question->removeQuestionChoice($questionChoice);
            $this->questionChoiceTable->remove($questionChoice, false);
        }
        $this->shiftPositionsForRemovingEntity($question, $this->questionTable);
        $this->questionTable->remove($question);
    }

    private function content(Question|QuestionChoice $questionOrChoice): array
    {
        $questionOrChoiceData = array_merge(['id' => $questionOrChoice->getId()], $questionOrChoice->getLabels(), [
            'position' => $questionOrChoice->getPosition(),
            'creator' => $questionOrChoice->getCreator(),
            'createdAt' => $questionOrChoice->getCreatedAt(),
            'updater' => $questionOrChoice->getUpdater(),
            'updatedAt' => $questionOrChoice->getUpdatedAt(),
        ]);
        $questionOrChoiceData['anr'] = new \stdClass();

        return $questionOrChoiceData;
    }
}
