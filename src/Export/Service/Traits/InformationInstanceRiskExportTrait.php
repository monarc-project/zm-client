<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\Core\Entity\InstanceRiskSuperClass;
use Monarc\FrontOffice\Entity;

trait InformationInstanceRiskExportTrait
{
    use InformationRiskExportTrait;
    use RecommendationExportTrait;

    private function prepareInformationInstanceRiskData(
        Entity\InstanceRisk $instanceRisk,
        int $languageIndex,
        bool $includeCompleteInformationRisksData,
        bool $withEval,
        bool $withControls,
        bool $withRecommendations
    ): array {
        /** @var ?Entity\Amv $amv */
        $amv = $instanceRisk->getAmv();
        $informationRiskData = $amv === null ? null : ['uuid' => $amv->getUuid()];
        if ($includeCompleteInformationRisksData && $amv !== null) {
            $informationRiskData = $this->prepareInformationRiskData($amv, $withEval, $withControls);
        }
        /** @var Entity\Threat $threat */
        $threat = $instanceRisk->getThreat();
        /** @var Entity\Vulnerability $vulnerability */
        $vulnerability = $instanceRisk->getVulnerability();
        $recommendationsData = [];
        if ($withRecommendations) {
            foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $recommendation = $recommendationRisk->getRecommendation();
                $recommendationsData[] = array_merge(
                    $this->prepareRecommendationData($recommendation),
                    ['commentAfter' => $recommendationRisk->getCommentAfter()]
                );
            }
        }

        return [
            'id' => $instanceRisk->getId(),
            'informationRisk' => $informationRiskData,
            'threat' => $this->prepareThreatData($threat, $languageIndex, $withEval),
            'vulnerability' => $this->prepareVulnerabilityData($vulnerability, $languageIndex),
            'specific' => (int)$instanceRisk->isSpecific(),
            'isThreatRateNotSetOrModifiedExternally' => $withEval
                ? (int)$instanceRisk->isThreatRateNotSetOrModifiedExternally()
                : 1,
            'threatRate' => $withEval ? $instanceRisk->getThreatRate() : -1,
            'vulnerabilityRate' => $withEval ? $instanceRisk->getVulnerabilityRate() : -1,
            'kindOfMeasure' => $withEval ? $instanceRisk->getKindOfMeasure() : InstanceRiskSuperClass::KIND_NOT_SET,
            'reductionAmount' => $withEval ? $instanceRisk->getReductionAmount() : 0,
            'comment' => $withControls ? $instanceRisk->getComment() : '',
            'commentAfter' => $withControls ? $instanceRisk->getCommentAfter() : '',
            'cacheMaxRisk' => $withEval ? $instanceRisk->getCacheMaxRisk() : -1,
            'cacheTargetedRisk' => $withEval ? $instanceRisk->getCacheTargetedRisk() : -1,
            'riskConfidentiality' => $withEval ? $instanceRisk->getRiskConfidentiality() : -1,
            'riskIntegrity' => $withEval ? $instanceRisk->getRiskIntegrity() : -1,
            'riskAvailability' => $withEval ? $instanceRisk->getRiskAvailability() : -1,
            'context' => $withEval ? $instanceRisk->getContext() : '',
            'riskOwner' => $withEval ? $instanceRisk->getInstanceRiskOwner()?->getName() : '',
            'recommendations' => $recommendationsData,
        ];
    }
}
