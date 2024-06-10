<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\FrontOffice\Entity;

trait RecommendationExportTrait
{
    private function prepareRecommendationData(
        Entity\Recommendation $recommendation,
        bool $includeRecommendationSetData = true
    ): array {
        $result = [
            'uuid' => $recommendation->getUuid(),
            'code' => $recommendation->getCode(),
            'description' => $recommendation->getDescription(),
            'importance' => $recommendation->getImportance(),
            'comment' => $recommendation->getComment(),
            'status' => $recommendation->getStatus(),
            'responsible' => $recommendation->getResponsible(),
            'duedate' => $recommendation->getDueDate()?->format('Y-m-d'),
            'counterTreated' => $recommendation->getCounterTreated(),
        ];
        if ($includeRecommendationSetData) {
            $result['recommendationSet'] = [
                'uuid' => $recommendation->getRecommendationSet()->getUuid(),
                'label' => $recommendation->getRecommendationSet()->getLabel(),
            ];
        }

        return $result;
    }
}
