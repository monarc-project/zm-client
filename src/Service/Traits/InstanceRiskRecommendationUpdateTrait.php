<?php

namespace Monarc\FrontOffice\Service\Traits;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Table\RecommandationTable;

trait InstanceRiskRecommendationUpdateTrait
{
    /**
     * Updates the recommendations' positions related to the risks.
     *
     * @param InstanceRisk|InstanceRiskOp $instanceRisk
     */
    public function updateRecoRisks($instanceRisk): void
    {
        $riskRecommendations = [];
        if ($instanceRisk->isTreated()) {
            foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $recommendation = $recommendationRisk->getRecommandation();
                if ($recommendation->isImportanceEmpty()) {
                    continue;
                }

                $riskRecommendations[$recommendation->getImportance()][] = $recommendation;
            }

            if (!empty($riskRecommendations)) {
                krsort($riskRecommendations);
                $riskRecommendations = array_reduce($riskRecommendations, 'array_merge', []);
                $this->updateRecommendationsPositions($instanceRisk, $riskRecommendations);
            }
        } else {
            /** @var InstanceRiskOp $instanceRisk */
            foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $recommendation = $recommendationRisk->getRecommandation();
                if ($recommendation->isPositionEmpty()
                    || $recommendation->isImportanceEmpty()
                    || \count($recommendation->getRecommendationRisks()) > 1
                ) {
                    continue;
                }

                $riskRecommendations[$recommendation->getPosition()][] = $recommendation;
            }

            if (!empty($riskRecommendations)) {
                ksort($riskRecommendations);
                $riskRecommendations = array_reduce($riskRecommendations, 'array_merge', []);
                $this->resetRecommendationsPositions($instanceRisk, $riskRecommendations);
            }
        }
    }

    /**
     * @param InstanceRisk|InstanceRiskOp $instanceRisk
     * @param array $riskRecommendations
     */
    private function updateRecommendationsPositions($instanceRisk, array $riskRecommendations): void
    {
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');
        $linkedRecommendations = $recommendationTable->findLinkedWithRisksByAnrExcludeInstanceRisk(
            $instanceRisk->getAnr(),
            $instanceRisk,
            ['position' => 'ASC']
        );

        // Check if order by importance is respected in the linked set.
        $isImportanceOrderRespected = true;
        $previousRecommendationImportance = Recommandation::HIGH_IMPORTANCE;
        $maxPositionsPerImportance = [
            Recommandation::HIGH_IMPORTANCE => 0,
            Recommandation::MEDIUM_IMPORTANCE => 0,
            Recommandation::LOW_IMPORTANCE => 0,
        ];
        foreach ($linkedRecommendations as $linkedRecommendation) {
            if ($linkedRecommendation->isImportanceHigherThan($previousRecommendationImportance)) {
                $isImportanceOrderRespected = false;
            }
            $previousRecommendationImportance = $linkedRecommendation->getImportance();
            $maxPositionsPerImportance[$linkedRecommendation->getImportance()] = $linkedRecommendation->getPosition();
        }

        /** @var Recommandation[] $riskRecommendations */
        if ($isImportanceOrderRespected) {
            foreach ($riskRecommendations as $riskRecommendation) {
                $riskRecommendation->setPosition(++$maxPositionsPerImportance[$riskRecommendation->getImportance()]);
                $recommendationTable->saveEntity($riskRecommendation, false);

                $isPositionIncreased = false;
                foreach ($linkedRecommendations as $linkedRecommendation) {
                    if ($linkedRecommendation->isImportanceLowerThan($riskRecommendation->getImportance())) {
                        $linkedRecommendation->shiftPositionDown();
                        $recommendationTable->saveEntity($linkedRecommendation, false);

                        if (!$isPositionIncreased) {
                            $maxPositionsPerImportance[$linkedRecommendation->getImportance()]++;
                            $isPositionIncreased = true;
                        }
                    }
                }
            }
        } else {
            $maxPosition = max($maxPositionsPerImportance);
            foreach ($riskRecommendations as $riskRecommendation) {
                $riskRecommendation->setPosition(++$maxPosition);
                $recommendationTable->saveEntity($riskRecommendation, false);
            }
        }

        $recommendationTable->getDb()->flush();
    }

    /**
     * @param InstanceRisk|InstanceRiskOp $instanceRisk
     * @param array $riskRecommendations
     */
    private function resetRecommendationsPositions($instanceRisk, array $riskRecommendations): void
    {
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');
        $linkedRecommendations = $recommendationTable->findLinkedWithRisksByAnrExcludeInstanceRisk(
            $instanceRisk->getAnr(),
            $instanceRisk,
            ['position' => 'ASC']
        );

        /** @var Recommandation[] $riskRecommendations */
        foreach ($riskRecommendations as $riskRecommendation) {
            $riskRecommendation->getPosition();
            foreach ($linkedRecommendations as $linkedRecommendation) {
                if ($linkedRecommendation->isPositionLowerThan($riskRecommendation->getPosition())) {
                    $linkedRecommendation->shiftPositionUp();
                    $recommendationTable->saveEntity($riskRecommendation, false);
                }
            }

            $riskRecommendation->setEmptyPosition();
            $recommendationTable->saveEntity($riskRecommendation, false);
        }

        $recommendationTable->getDb()->flush();
    }
}
