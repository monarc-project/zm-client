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
    private function prepareRecommendationData(Entity\Recommendation $recommendation): array
    {
        return [
            'uuid' => $recommendation->getUuid(),
            'recommendationSet' => [
                'uuid' => $recommendation->getRecommendationSet()->getUuid(),
                'lable' => $recommendation->getRecommendationSet()->getLabel(),
            ],
            'code' => $recommendation->getCode(),
            'description' => $recommendation->getDescription(),
            'importance' => $recommendation->getImportance(),
            'comment' => $recommendation->getComment(),
            'status' => $recommendation->getStatus(),
            'responsible' => $recommendation->getResponsible(),
            'duedate' => $recommendation->getDueDate()?->format('Y-m-d'),
            'counterTreated' => $recommendation->getCode()
        ];
    }
}
