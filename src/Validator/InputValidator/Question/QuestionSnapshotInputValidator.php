<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Question;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Validator\InputValidator\Question\QuestionDataInputValidator;
use Monarc\Core\Validator\InputValidator\Question\QuestionChoiceDataInputValidator;

/** Validates both existing method-step formats before any persisted data is changed. */
class QuestionSnapshotInputValidator
{
    public function __construct(
        private QuestionDataInputValidator $questionDataInputValidator,
        private QuestionChoiceDataInputValidator $questionChoiceDataInputValidator
    ) {
    }

    public function validate(array $snapshot, int $language): array
    {
        if (!isset($snapshot['questions']) || !is_array($snapshot['questions'])
            || count($snapshot['questions']) > QuestionDataInputValidator::MAX_UNSIGNED_SMALLINT
        ) {
            throw new Exception('Invalid questions', 412);
        }
        $rows = [];
        $seenIds = [];
        foreach ($snapshot['questions'] as $position => $row) {
            if (!is_array($row)) {
                throw new Exception('Invalid question', 412);
            }
            if (array_key_exists('label', $row)) {
                $row['label' . $language] = $row['label'];
            }
            $row['multichoice'] = $row['isMultiChoice'] ?? $row['multichoice'] ?? 0;
            $row['position'] = $row['position'] ?? $position;
            $validated = $this->questionDataInputValidator->validate($row);
            $id = $row['id'] ?? $position;
            if ((!is_int($id) && !(is_string($id) && ctype_digit($id))) || isset($seenIds[$id])) {
                throw new Exception('Invalid question identifier', 412);
            }
            $seenIds[$id] = true;
            $mode = $row['mode'] ?? 0;
            if (!in_array($mode, [0, 1, '0', '1'], true)) {
                throw new Exception('Invalid question mode', 412);
            }
            $validated += ['mode' => (int)$mode, 'response' => null];
            $sourceChoices = $snapshot['questionChoice'] ?? $row['questionChoices'] ?? [];
            if (!is_array($sourceChoices)) {
                throw new Exception('Invalid choices', 412);
            }
            $choiceRows = [];
            foreach ($sourceChoices as $choiceId => $choice) {
                if (!is_array($choice)) {
                    throw new Exception('Invalid choice', 412);
                }
                if (isset($choice['question'])) {
                    $this->questionDataInputValidator->validateIds([$choice['question']]);
                }
                if (isset($snapshot['questionChoice'], $choice['question'])
                    && (string)$choice['question'] !== (string)$id
                ) {
                    continue;
                }
                if (isset($choice['question']) && (string)$choice['question'] !== (string)$id) {
                    throw new Exception('Choice must belong to the same question', 412);
                }
                if (array_key_exists('label', $choice)) {
                    $choice['label' . $language] = $choice['label'];
                }
                $oldId = $choice['id'] ?? $choiceId;
                if ((!is_int($oldId) && !(is_string($oldId) && ctype_digit($oldId))) || isset($choiceRows[$oldId])) {
                    throw new Exception('Invalid choice identifier', 412);
                }
                // A temporary positive question identifier validates content; old IDs are only used for remapping.
                $choiceRows[$oldId] = $this->questionChoiceDataInputValidator->validate(
                    array_merge($choice, ['question' => 1])
                );
            }
            if (count($choiceRows) > QuestionChoiceDataInputValidator::MAX_UNSIGNED_SMALLINT
                || ($validated['type'] !== 2 && $choiceRows !== [])
            ) {
                throw new Exception('Invalid choices', 412);
            }
            if ($validated['type'] === 2 && $validated['response'] !== null
                && $validated['response'] !== '' && $validated['response'] !== 'null'
            ) {
                $ids = $validated['multichoice']
                    ? json_decode($validated['response'], true) : [$validated['response']];
                if (!is_array($ids) || !array_is_list($ids)) {
                    throw new Exception('Invalid response', 412);
                }
                foreach ($ids as $responseId) {
                    if ((!is_int($responseId) && !(is_string($responseId) && ctype_digit($responseId)))
                        || !array_key_exists($responseId, $choiceRows)
                    ) {
                        throw new Exception('Response must belong to the same question', 412);
                    }
                }
            }
            $validated['questionChoices'] = $choiceRows;
            $rows[] = $validated;
        }
        return $rows;
    }
}
