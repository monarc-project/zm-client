<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service\Traits;

use LogicException;
use Monarc\Core\Entity\AnrSuperClass;
use Monarc\FrontOffice\Entity\InstanceRisk;
use Monarc\FrontOffice\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Entity\Recommendation;
use Monarc\FrontOffice\Table\RecommendationTable;

trait RecommendationsPositionsUpdateTrait
{
    /**
     * Updates the recommendations' positions related to the risk.
     *
     * @param InstanceRisk|InstanceRiskOp $instanceRisk
     */
    public function updateInstanceRiskRecommendationsPositions(InstanceRisk|InstanceRiskOp $instanceRisk): void
    {
        $recommendationRisks = $instanceRisk->getRecommendationRisks();
        if ($recommendationRisks === null) {
            return;
        }

        $riskRecommendations = [];
        if ($instanceRisk->isTreated()) {
            foreach ($recommendationRisks as $recommendationRisk) {
                $recommendation = $recommendationRisk->getRecommendation();
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
            foreach ($recommendationRisks as $recommendationRisk) {
                $recommendation = $recommendationRisk->getRecommendation();
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

    /**
     * Resets positions of the recommendations related to the informational/operational risk if not linked anymore.
     *
     * @param InstanceRisk|InstanceRiskOp $instanceRisk
     */
    protected function processRemovedInstanceRiskRecommendationsPositions($instanceRisk): void
    {
        if ($instanceRisk->isTreated()
            && $instanceRisk->getRecommendationRisks() !== null
            && !$instanceRisk->getRecommendationRisks()->isEmpty()
        ) {
            $recommendationsToResetPositions = [];
            foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $linkedRecommendation = $recommendationRisk->getRecommendation();
                if (!$linkedRecommendation->hasLinkedRecommendationRisks()) {
                    $recommendationsToResetPositions[$linkedRecommendation->getUuid()] = $linkedRecommendation;
                }
            }
            if (!empty($recommendationsToResetPositions)) {
                $this->resetRecommendationsPositions($instanceRisk->getAnr(), $recommendationsToResetPositions);
            }
        }
    }

    /**
     * @param AnrSuperClass $anr
     * @param array $riskRecommendations List of recommendations to reset the positions (set ot 0),
     *                                   Keys of the array are UUIDs, values are Recommendation objects.
     */
    protected function resetRecommendationsPositions(AnrSuperClass $anr, array $riskRecommendations): void
    {
        $recommendationTable = $this->getRecommendationTable();
        $linkedRecommendations = $recommendationTable
            ->findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
                $anr,
                array_keys($riskRecommendations),
                ['r.position' => 'ASC']
            );

        $positionShiftAdjustment = 0;
        /** @var Recommendation[] $riskRecommendations */
        foreach ($riskRecommendations as $riskRecommendation) {
            foreach ($linkedRecommendations as $linkedRecommendation) {
                if ($linkedRecommendation->isPositionLowerThan(
                    $riskRecommendation->getPosition() + $positionShiftAdjustment
                )) {
                    $linkedRecommendation->shiftPositionUp();
                    $recommendationTable->save($riskRecommendation, false);
                }
            }

            $positionShiftAdjustment--;
            $riskRecommendation->setEmptyPosition();
            $recommendationTable->save($riskRecommendation, false);
        }

        $recommendationTable->flush();
    }

    private function updateRecommendationsPositions(AnrSuperClass $anr, array $riskRecommendations): void
    {
        $recommendationTable = $this->getRecommendationTable();
        $linkedRecommendations = $recommendationTable
            ->findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
                $anr,
                array_keys($riskRecommendations),
                ['r.position' => 'ASC']
            );

        $maxPositionsPerImportance = $this->getMaxPositionsPerImportance($linkedRecommendations);

        /** @var Recommendation[] $riskRecommendations */
        if ($this->isImportanceOrderRespected($linkedRecommendations)) {
            foreach ($riskRecommendations as $riskRecommendation) {
                $riskRecommendation->setPosition(++$maxPositionsPerImportance[$riskRecommendation->getImportance()]);
                $recommendationTable->save($riskRecommendation, false);

                foreach ($linkedRecommendations as $linkedRecommendation) {
                    if ($linkedRecommendation->isImportanceLowerThan($riskRecommendation->getImportance())) {
                        $linkedRecommendation->shiftPositionDown();
                        $recommendationTable->save($linkedRecommendation, false);
                    }
                }

                $maxPositionsPerImportance = $this->increaseMaxPositionsForLowerImportance(
                    $maxPositionsPerImportance,
                    $riskRecommendation->getImportance()
                );
            }
        } else {
            $maxPosition = max($maxPositionsPerImportance);
            foreach ($riskRecommendations as $riskRecommendation) {
                $riskRecommendation->setPosition(++$maxPosition);
                $recommendationTable->save($riskRecommendation, false);
            }
        }

        $recommendationTable->flush();
    }

    /**
     * @param Recommendation[] $linkedRecommendations
     */
    private function getMaxPositionsPerImportance(array $linkedRecommendations): array
    {
        $maxPositionsPerImportance = [
            Recommendation::HIGH_IMPORTANCE => 0,
            Recommendation::MEDIUM_IMPORTANCE => 0,
            Recommendation::LOW_IMPORTANCE => 0,
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

    private function increaseMaxPositionsForLowerImportance(array $maxPositionsPerImportance, int $importance): array
    {
        foreach ($maxPositionsPerImportance as $currentImportance => $maxPosition) {
            if ($currentImportance < $importance) {
                $maxPositionsPerImportance[$currentImportance]++;
            }
        }

        return $maxPositionsPerImportance;
    }

    /**
     * @param Recommendation[] $linkedRecommendations
     */
    private function isImportanceOrderRespected(array $linkedRecommendations): bool
    {
        $previousRecommendationImportance = Recommendation::HIGH_IMPORTANCE;
        foreach ($linkedRecommendations as $linkedRecommendation) {
            if ($linkedRecommendation->isImportanceHigherThan($previousRecommendationImportance)) {
                return false;
            }
            $previousRecommendationImportance = $linkedRecommendation->getImportance();
        }

        return true;
    }

    private function getRecommendationTable(): RecommendationTable
    {
        if (property_exists(\get_class($this), 'recommendationTable')) {
            return $this->recommendationTable;
        }

        throw new LogicException(sprintf(
            'The property "recommendationTable" should be defined in the class "%s" to be able to use the trait "%s"',
            __CLASS__,
            __TRAIT__
        ));
    }
}
