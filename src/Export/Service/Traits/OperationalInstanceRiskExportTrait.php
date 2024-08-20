<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\Core\Entity\InstanceRiskOpSuperClass;
use Monarc\FrontOffice\Entity;

trait OperationalInstanceRiskExportTrait
{
    use OperationalRiskExportTrait;

    private function prepareOperationalInstanceRiskData(
        Entity\InstanceRiskOp $operationalInstanceRisk,
        int $languageIndex,
        bool $withEval,
        bool $withControls,
        bool $withRecommendations
    ): array {
        /** @var ?Entity\RolfRisk $operationalRisk */
        $operationalRisk = $operationalInstanceRisk->getRolfRisk();
        $recommendationsData = [];
        if ($withRecommendations) {
            foreach ($operationalInstanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $recommendation = $recommendationRisk->getRecommendation();
                $recommendationsData[] = array_merge(
                    $this->prepareRecommendationData($recommendation),
                    ['commentAfter' => $recommendationRisk->getCommentAfter()]
                );
            }
        }
        $operationalInstanceRiskScales = [];
        if ($withEval) {
            foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $instanceRiskScale) {
                $scaleTypeId = $instanceRiskScale->getOperationalRiskScaleType()->getId();
                $operationalInstanceRiskScales[$scaleTypeId] = [
                    'operationalRiskScaleTypeId' => $scaleTypeId,
                    'netValue' => $instanceRiskScale->getNetValue(),
                    'brutValue' => $instanceRiskScale->getBrutValue(),
                    'targetedValue' => $instanceRiskScale->getTargetedValue(),
                ];
            }
        }

        return [
            'id' => $operationalInstanceRisk->getId(),
            'operationalRisk' => $operationalRisk !== null
                ? $this->prepareOperationalRiskData($operationalRisk, $languageIndex, $withControls)
                : null,
            'riskCacheCode' => $operationalInstanceRisk->getRiskCacheCode(),
            'riskCacheLabel' => $operationalInstanceRisk->getRiskCacheLabel($languageIndex),
            'riskCacheDescription' => $operationalInstanceRisk->getRiskCacheDescription($languageIndex),
            'brutProb' => $withEval ? $operationalInstanceRisk->getBrutProb() : -1,
            'netProb' => $withEval ? $operationalInstanceRisk->getNetProb() : -1,
            'targetedProb' => $withEval ? $operationalInstanceRisk->getTargetedProb() : -1,
            'cacheBrutRisk' => $withEval ? $operationalInstanceRisk->getCacheBrutRisk() : -1,
            'cacheNetRisk' => $withEval ? $operationalInstanceRisk->getCacheNetRisk() : -1,
            'cacheTargetedRisk' => $withEval ? $operationalInstanceRisk->getCacheTargetedRisk() : -1,
            'kindOfMeasure' => $withEval
                ? $operationalInstanceRisk->getKindOfMeasure()
                : InstanceRiskOpSuperClass::KIND_NOT_SET,
            'comment' => $withControls ? $operationalInstanceRisk->getComment() : '',
            'mitigation' => $withEval ? $operationalInstanceRisk->getMitigation() : '',
            'specific' => $operationalInstanceRisk->getSpecific(),
            'context' => $withEval ? $operationalInstanceRisk->getContext() : '',
            'riskOwner' => $withEval && $operationalInstanceRisk->getInstanceRiskOwner() !== null
                ? $operationalInstanceRisk->getInstanceRiskOwner()->getName()
                : '',
            'recommendations' => $recommendationsData,
            'operationalInstanceRiskScales' => $operationalInstanceRiskScales,
        ];
    }
}
