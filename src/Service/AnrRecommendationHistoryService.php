<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\Common\Collections\Criteria;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\InstanceRisk;
use Monarc\FrontOffice\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Entity\RecommendationHistory;
use Monarc\FrontOffice\Entity\RecommendationRisk;
use Monarc\FrontOffice\Table\RecommendationHistoryTable;

class AnrRecommendationHistoryService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private RecommendationHistoryTable $recommendationHistoryTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(Anr $anr): array
    {
        $recommendationsHistoryList = [];
        /** @var RecommendationHistory $recommendationHistory */
        foreach ($this->recommendationHistoryTable->findByAnr($anr) as $recommendationHistory) {
            $recommendationsHistoryList[] = [
                'id' => $recommendationHistory->getId(),
                'cacheCommentAfter' => $recommendationHistory->getCacheCommentAfter(),
                'implComment' => $recommendationHistory->getImplComment(),
                'final' => (int)$recommendationHistory->isFinal(),
                'creator' => $recommendationHistory->getCreator(),
                'createdAt' => [
                    'date' => $recommendationHistory->getCreatedAt() !== null
                        ? $recommendationHistory->getCreatedAt()->format('Y-m-d H:i:s')
                        : '',
                ],
                'recoCode' => $recommendationHistory->getRecoCode(),
                'recoDescription' => $recommendationHistory->getRecoDescription(),
                'recoComment' => $recommendationHistory->getRecoComment(),
                'recoImportance' => $recommendationHistory->getRecoImportance(),
                'recoDuedate' => [
                    'date' => $recommendationHistory->getRecoDueDate() !== null
                        ? $recommendationHistory->getRecoDueDate()->format('Y-m-d H:i:s')
                        : '',
                ],
                'recoResponsable' => $recommendationHistory->getRecoResponsable(),
                'riskAsset' => $recommendationHistory->getRiskAsset(),
                'riskInstance' => $recommendationHistory->getRiskInstance(),
                'riskInstanceContext' => $recommendationHistory->getRiskInstanceContext(),
                'riskThreat' => $recommendationHistory->getRiskThreat(),
                'riskVul' => $recommendationHistory->getRiskVul(),
                'riskOpDescription' => $recommendationHistory->getRiskOpDescription(),
                'riskKindOfMeasure' => $recommendationHistory->getRiskKindOfMeasure(),
                'riskColorBefore' => $recommendationHistory->getRiskColorBefore(),
                'riskColorAfter' => $recommendationHistory->getRiskColorAfter(),
                'riskCommentBefore' => $recommendationHistory->getRiskCommentBefore(),
                'riskCommentAfter' => $recommendationHistory->getRiskCommentAfter(),
                'riskMaxRiskBefore' => $recommendationHistory->getRiskMaxRiskBefore(),
                'riskMaxRiskAfter' => $recommendationHistory->getRiskMaxRiskAfter(),
            ];
        }

        return $recommendationsHistoryList;
    }

    public function createFromRecommendationRisk(
        array $data,
        RecommendationRisk $recommendationRisk,
        bool $isFinal,
        bool $saveInDb = true
    ): RecommendationHistory {
        $recommendation = $recommendationRisk->getRecommendation();
        $anr = $recommendation->getAnr();
        $languageIndex = $anr->getLanguage();

        $recommendationHistory = (new RecommendationHistory())
            ->setAnr($anr)
            ->setImplComment($data['comment'])
            ->setIsFinal($isFinal)
            ->setRecoCode($recommendation->getCode())
            ->setRecoDescription($recommendation->getDescription())
            ->setRecoImportance($recommendation->getImportance())
            ->setRecoComment($recommendation->getComment())
            ->setRecoDueDate($recommendation->getDueDate())
            ->setRecoResponsable($recommendation->getResponsible())
            ->setRiskInstance($recommendationRisk->getInstance()->getName($languageIndex))
            ->setRiskInstanceContext($recommendationRisk->getInstance()->getHierarchyString())
            ->setCacheCommentAfter($recommendationRisk->getCommentAfter())
            ->setCreator($this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname());

        $instanceRisk = $recommendationRisk->getInstanceRisk();
        $instanceRiskOp = $recommendationRisk->getInstanceRiskOp();
        if ($instanceRisk !== null) {
            if ($isFinal) {
                $riskColorAfter = $instanceRisk->getCacheTargetedRisk() !== -1
                    ? $anr->getInformationalRiskLevelColor($instanceRisk->getCacheTargetedRisk())
                    : '';
            } else {
                $riskColorAfter = $instanceRisk->getCacheMaxRisk() !== -1
                    ? $anr->getInformationalRiskLevelColor($instanceRisk->getCacheMaxRisk())
                    : '';
            }
            $recommendationHistory->setInstanceRisk($instanceRisk)
                ->setRiskAsset($instanceRisk->getAsset()->getLabel($languageIndex))
                ->setRiskThreat($instanceRisk->getThreat()->getLabel($languageIndex))
                ->setRiskThreatVal($instanceRisk->getThreatRate())
                ->setRiskVul($instanceRisk->getVulnerability()->getLabel($languageIndex))
                ->setRiskVulValBefore($instanceRisk->getVulnerabilityRate())
                ->setRiskVulValAfter(
                    $isFinal
                        ? max(0, $instanceRisk->getVulnerabilityRate() - $instanceRisk->getReductionAmount())
                        : $instanceRisk->getVulnerabilityRate()
                )
                ->setRiskKindOfMeasure($instanceRisk->getKindOfMeasure())
                ->setRiskCommentBefore($instanceRisk->getComment())
                ->setRiskCommentAfter($isFinal ? $recommendationRisk->getCommentAfter() : $instanceRisk->getComment())
                ->setRiskMaxRiskBefore($instanceRisk->getCacheMaxRisk())
                ->setRiskMaxRiskAfter(
                    $isFinal ? $instanceRisk->getCacheTargetedRisk() : $instanceRisk->getCacheMaxRisk()
                )->setRiskColorBefore(
                    $instanceRisk->getCacheMaxRisk() !== -1
                        ? $recommendation->getAnr()->getInformationalRiskLevelColor($instanceRisk->getCacheMaxRisk())
                        : ''
                )->setRiskColorAfter($riskColorAfter);
        } elseif ($instanceRiskOp !== null) {
            if ($isFinal) {
                $riskColorAfter = $instanceRiskOp->getCacheTargetedRisk() !== -1
                    ? $anr->getOperationalRiskLevelColor($instanceRiskOp->getCacheTargetedRisk())
                    : '';
            } else {
                $riskColorAfter = $instanceRiskOp->getCacheNetRisk() !== -1
                    ? $anr->getOperationalRiskLevelColor($instanceRiskOp->getCacheNetRisk())
                    : '';
            }
            $recommendationHistory->setInstanceRiskOp($instanceRiskOp)
                ->setRiskAsset($instanceRiskOp->getObject()->getAsset()->getLabel($languageIndex))
                ->setRiskOpDescription($instanceRiskOp->getRiskCacheLabel($languageIndex))
                ->setNetProbBefore($instanceRiskOp->getNetProb())
                ->setRiskKindOfMeasure($instanceRiskOp->getKindOfMeasure())
                ->setRiskCommentBefore($instanceRiskOp->getComment())
                ->setRiskCommentAfter($isFinal ? $recommendationRisk->getCommentAfter() : $instanceRiskOp->getComment())
                ->setRiskMaxRiskBefore($instanceRiskOp->getCacheNetRisk())
                ->setRiskMaxRiskAfter(
                    $isFinal ? $instanceRiskOp->getCacheTargetedRisk() : $instanceRiskOp->getCacheNetRisk()
                )->setRiskColorBefore(
                    $instanceRiskOp->getCacheNetRisk() !== -1
                        ? $anr->getOperationalRiskLevelColor($instanceRiskOp->getCacheNetRisk())
                        : ''
                )->setRiskColorAfter($riskColorAfter);
        }

        $this->recommendationHistoryTable->save($recommendationHistory, $saveInDb);

        return $recommendationHistory;
    }

    public function getValidatedCachedCommentsList(
        InstanceRisk|InstanceRiskOp $instanceRisk,
        RecommendationHistory $currentRecommendationHistory
    ): array {
        $recommendationsHistory = $this->recommendationHistoryTable->findByInstanceRiskOrderBy(
            $instanceRisk,
            ['id' => Criteria::DESC]
        );

        $cacheCommentsAfter = $currentRecommendationHistory->getCacheCommentAfter() !== null
            ? [$currentRecommendationHistory->getCacheCommentAfter()]
            : [];
        foreach ($recommendationsHistory as $recommendationHistory) {
            /* Get other validated recommendations comments linked to the same risk before the final one. */
            if ($recommendationHistory->isFinal()) {
                break;
            }
            if ($recommendationHistory->getCacheCommentAfter() !== '') {
                $cacheCommentsAfter[] = $recommendationHistory->getCacheCommentAfter();
            }
        }

        return $cacheCommentsAfter;
    }
}
