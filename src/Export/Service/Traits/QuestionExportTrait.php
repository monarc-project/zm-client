<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\FrontOffice\Entity;

trait QuestionExportTrait
{
    private function prepareQuestionData(Entity\Question $question, int $languageIndex): array
    {
        $questionChoicesData = [];
        foreach ($question->getQuestionChoices() as $questionChoice) {
            $questionChoicesData[] = [
                'id' => $questionChoice->getId(),
                'label' => $questionChoice->getLabel($languageIndex),
                'position' => $questionChoice->getPosition(),
            ];
        }

        return [
            'id' => $question->getId(),
            'mode' => $question->getMode(),
            'isMultiChoice' => $question->isMultiChoice(),
            'label' => $question->getLabel($languageIndex),
            'response' => $question->getResponse(),
            'type' => $question->getType(),
            'position' => $question->getPosition(),
            'questionChoices' => $questionChoicesData,
        ];
    }
}
