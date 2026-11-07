<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Entity\AnrSuperClass;
use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\Table\QuestionChoiceTable;
use Monarc\FrontOffice\Table\QuestionTable;

class AnrQuestionService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private QuestionTable $questionTable,
        private QuestionChoiceTable $questionChoiceTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params, ?AnrSuperClass $anr = null): array
    {
        return array_map(
            fn ($question): array => $this->serialize($question, true),
            $this->questionTable->findByParams($this->scopedParams($params, $anr))
        );
    }

    public function getCount(FormattedInputParams $params, ?AnrSuperClass $anr = null): int
    {
        return $this->questionTable->countByParams($this->scopedParams($params, $anr));
    }

    public function getEntity(int $id, ?AnrSuperClass $anr = null): array
    {
        return $this->serialize($this->questionTable->findByIdAndAnr($id, $anr));
    }

    public function serialize(Question $question, bool $withChoices = false): array
    {
        $data = array_merge($this->content($question), [
            'type' => $question->getType(), 'multichoice' => (int)$question->isMultiChoice(),
            // Legacy JSON represented the collection as an object; choices is the populated list field.
            'questionChoices' => new \stdClass(),
        ]);
        $data += ['mode' => $question->getMode(), 'response' => $question->getResponse()];

        if ($withChoices && $question->getType() === 2) {
            $data['choices'] = array_map(function ($choice): array {
                return array_merge($this->content($choice), ['question' => new \stdClass()]);
            }, $this->questionChoiceTable->findChoicesOfQuestion($question));
            if (!$question->isMultiChoice()) {
                $data['choices'] = array_merge([[
                    'id' => null, 'label1' => null, 'label2' => null, 'label3' => null, 'label4' => null,
                    'position' => null, 'question' => null, 'anr' => null,
                    'creator' => null, 'createdAt' => null, 'updater' => null, 'updatedAt' => null,
                ]], $data['choices']);
            }
        }

        return $data;
    }

    public function create(array $data, ?AnrSuperClass $anr = null): int
    {
        $position = count($this->questionTable->findOrderedQuestions($anr)) + 1;
        if ($position > 65535) {
            throw new Exception('Too many records', 412);
        }
        $question = (new Question())->setAnr($anr)->setType(1)->setIsMultiChoice(false)->setMode(1)
            ->setPosition($position);

        $this->applyData($question, $data);
        $question->setCreator($this->connectedUser->getEmail());
        $this->questionTable->save($question);

        return $question->getId();
    }

    public function update(int $id, array $data, ?AnrSuperClass $anr = null): void
    {
        $question = $this->questionTable->findByIdAndAnr($id, $anr);
        if ($question->getMode() === 0) {
            $data = array_intersect_key($data, ['response' => true]);
        } else {
            $data = array_intersect_key($data, array_flip(['label1', 'label2', 'label3', 'label4', 'response']));
        }

        $this->validateResponse($question, $data);
        $this->applyData($question, $data);
        $question->setUpdater($this->connectedUser->getEmail());

        $this->questionTable->save($question);
    }

    public function delete(int $id, ?AnrSuperClass $anr = null): void
    {
        $this->deleteList([$id], $anr);
    }

    public function deleteList(array $ids, ?AnrSuperClass $anr = null): void
    {
        $questions = array_map(fn ($id) => $this->questionTable->findByIdAndAnr($id, $anr), $ids);
        foreach ($questions as $question) {
            if ($question->getMode() === 0) {
                throw new Exception('Delete question is not possible', 412);
            }
        }
        foreach ($questions as $question) {
            foreach ($this->questionChoiceTable->findChoicesOfQuestion($question) as $choice) {
                $question->removeQuestionChoice($choice);
                $this->questionChoiceTable->remove($choice, false);
            }
            $this->questionTable->remove($question, false);
        }
        $this->numberQuestions(array_values(array_filter(
            $this->questionTable->findOrderedQuestions($anr),
            fn ($question): bool => !in_array($question->getId(), $ids, true)
        )));
        $this->questionTable->flush();
    }

    private function applyData(Question $question, array $data): void
    {
        $question->setLabels($data);
        if (array_key_exists('response', $data)) {
            $question->setResponse($data['response']);
        }
    }

    private function validateResponse(Question $question, array $data): void
    {
        if ($question->getType() !== 2 || !isset($data['response']) || $data['response'] === '') {
            return;
        }
        $ids = $question->isMultiChoice() ? json_decode($data['response'], true) : [$data['response']];
        if (!is_array($ids) || !array_is_list($ids)) {
            throw new Exception('Invalid response', 412);
        }
        // A null selection is represented as the string "null" by the existing client.
        if (!$question->isMultiChoice() && $data['response'] === 'null') {
            return;
        }
        $ids = array_map('intval', $ids);
        $choices = $this->questionChoiceTable->findChoicesOfQuestion($question);
        $allowed = array_map(fn ($choice): int => $choice->getId(), $choices);
        if (array_diff($ids, $allowed) !== []) {
            throw new Exception('Response must belong to the same question', 412);
        }
    }

    private function scopedParams(FormattedInputParams $params, ?AnrSuperClass $anr): FormattedInputParams
    {
        $params = clone $params;
        $params->addFilter('anr', ['value' => $anr->getId(), 'operator' => '=']);

        return $params;
    }

    private function content(Question|QuestionChoice $entity): array
    {
        $data = array_merge(['id' => $entity->getId()], $entity->getLabels(), [
            'position' => $entity->getPosition(), 'creator' => $entity->getCreator(),
            'createdAt' => $entity->getCreatedAt(), 'updater' => $entity->getUpdater(),
            'updatedAt' => $entity->getUpdatedAt(),
        ]);
        $data['anr'] = $entity->getAnr() === null ? null : new \stdClass();

        return $data;
    }

    private function numberQuestions(array $records): void
    {
        foreach ($records as $index => $record) {
            $record->setPosition($index + 1);
        }
    }
}
