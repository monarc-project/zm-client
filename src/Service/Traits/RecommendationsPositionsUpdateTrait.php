<?php

namespace Monarc\FrontOffice\Service\Traits;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Table\RecommandationTable;

trait RecommendationsPositionsUpdateTrait
{
    /**
     * Updates the recommendations' positions related to the risk.
     *
     * @param InstanceRisk|InstanceRiskOp $instanceRisk
     */
    public function updateInstanceRiskRecommendationsPositions($instanceRisk): void
    {
        $riskRecommendations = [];
        if ($instanceRisk->isTreated()) {
            foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $recommendation = $recommendationRisk->getRecommandation();
                if ($recommendation->isImportanceEmpty() || !$recommendation->isPositionEmpty()) {
                    continue;
                }

                $riskRecommendations[$recommendation->getImportance()][$recommendation->getUuid()] = $recommendation;
            }

            if (!empty($riskRecommendations)) {
                krsort($riskRecommendations);
                $riskRecommendations = array_reduce($riskRecommendations, 'array_merge', []);
                $this->updateRecommendationsPositions($instanceRisk->getAnr(), $riskRecommendations);
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

                $riskRecommendations[$recommendation->getPosition()][$recommendation->getUuid()] = $recommendation;
            }

            if (!empty($riskRecommendations)) {
                ksort($riskRecommendations);
                $riskRecommendations = array_reduce($riskRecommendations, 'array_merge', []);
                $this->resetRecommendationsPositions($instanceRisk->getAnr(), $riskRecommendations);
            }
        }
    }

    protected function resetRecommendationsPositions(AnrSuperClass $anr, array $riskRecommendations): void
    {
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');
        $linkedRecommendations = $recommendationTable
            ->findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
                $anr,
                array_keys($riskRecommendations),
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

    private function updateRecommendationsPositions(AnrSuperClass $anr, array $riskRecommendations): void
    {
        /** @var RecommandationTable $recommendationTable */
        $recommendationTable = $this->get('recommandationTable');
        $linkedRecommendations = $recommendationTable
            ->findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
                $anr,
                array_keys($riskRecommendations),
                ['position' => 'ASC']
            );

        $maxPositionsPerImportance = $this->getMaxPositionsPerImportance($linkedRecommendations);

        /** @var Recommandation[] $riskRecommendations */
        if ($this->isImportanceOrderRespected($linkedRecommendations)) {
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
                $maxPositionsPerImportance = $this->getReviewedPositions($maxPositionsPerImportance);
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
     * @param Recommandation[] $linkedRecommendations
     */
    private function getMaxPositionsPerImportance(array $linkedRecommendations): array
    {
        $maxPositionsPerImportance = [
            Recommandation::HIGH_IMPORTANCE => 0,
            Recommandation::MEDIUM_IMPORTANCE => 0,
            Recommandation::LOW_IMPORTANCE => 0,
        ];
        foreach ($linkedRecommendations as $linkedRecommendation) {
            $maxPositionsPerImportance[$linkedRecommendation->getImportance()] = $linkedRecommendation->getPosition();
        }

        return $this->getReviewedPositions($maxPositionsPerImportance);
    }

    /**
     * Keeps lower importance positions aligned with higher.
     * Validates the positions to prevent the situation when for low importance we have no recommendations
     * with position > 0, but have some for higher importance.
     */
    private function getReviewedPositions(array $maxPositionsPerImportance): array
    {
        foreach ($maxPositionsPerImportance as $importance => $maxPosition) {
            if (isset($maxPositionsPerImportance[$importance + 1])
                && $maxPositionsPerImportance[$importance + 1] > $maxPosition
            ) {
                $maxPositionsPerImportance[$importance] = $maxPositionsPerImportance[$importance + 1];
            }
        }

        return $maxPositionsPerImportance;
    }

    /**
     * @param Recommandation[] $linkedRecommendations
     */
    private function isImportanceOrderRespected(array $linkedRecommendations): bool
    {
        $previousRecommendationImportance = Recommandation::HIGH_IMPORTANCE;
        foreach ($linkedRecommendations as $linkedRecommendation) {
            if ($linkedRecommendation->isImportanceHigherThan($previousRecommendationImportance)) {
                return false;
            }
            $previousRecommendationImportance = $linkedRecommendation->getImportance();
        }

        return true;
    }
}
