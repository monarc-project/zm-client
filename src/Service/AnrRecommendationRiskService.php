<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Entity\InstanceRiskSuperClass;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Traits\RiskCalculationTrait;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrRecommendationRiskService
{
    use RiskCalculationTrait;
    use RecommendationsPositionsUpdateTrait;

    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\RecommendationTable $recommendationTable,
        private Table\RecommendationRiskTable $recommendationRiskTable,
        private Table\RecommendationHistoryTable $recommendationHistoryTable,
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private Table\InstanceTable $instanceTable,
        private AnrRecommendationHistoryService $recommendationHistoryService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $formattedInputParams): array
    {
        $includeRelations = $formattedInputParams->hasFilterFor('recommendation.uuid')
            || $formattedInputParams->hasFilterFor('includeRelations');
        $recommendationRisksData = [];
        $globalObjectsUuids = [];
        /** @var Entity\RecommendationRisk $recommendationRisk */
        foreach ($this->recommendationRiskTable->findByParams($formattedInputParams) as $recommendationRisk) {
            if ($includeRelations
                && $recommendationRisk->getGlobalObject() !== null
                && isset($globalObjectsUuids[$recommendationRisk->getGlobalObject()->getUuid()])
            ) {
                continue;
            }

            $recommendationRisksData[] = $this->getPreparedRecommendationRiskData(
                $recommendationRisk,
                $includeRelations
            );

            if ($includeRelations && $recommendationRisk->getGlobalObject() !== null) {
                $globalObjectsUuids[$recommendationRisk->getGlobalObject()->getUuid()] = true;
            }
        }

        return $recommendationRisksData;
    }

    public function getCount(FormattedInputParams $formattedInputParams): int
    {
        return $this->recommendationRiskTable->countByParams($formattedInputParams);
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\RecommendationRisk
    {
        /** @var Entity\Recommendation $recommendation */
        $recommendation = $this->recommendationTable->findByUuidAndAnr($data['recommendation'], $anr);
        /** @var Entity\InstanceRiskOp|Entity\InstanceRisk $instanceRisk */
        $instanceRisk = !empty($data['instanceRiskOp'])
            ? $this->instanceRiskOpTable->findByIdAndAnr($data['instanceRiskOp'], $anr)
            : $this->instanceRiskTable->findByIdAndAnr($data['instanceRisk'], $anr);

        /* Verify existence. */
        if (($instanceRisk instanceof Entity\InstanceRisk && $this->recommendationRiskTable
                ->existsWithAnrRecommendationAndInstanceRisk($anr, $recommendation, $instanceRisk)
            )
            || ($instanceRisk instanceof Entity\InstanceRiskOp && $this->recommendationRiskTable
                ->existsWithAnrRecommendationAndInstanceRiskOp($anr, $recommendation, $instanceRisk)
            )
        ) {
            throw new Exception('The risk is already linked to this recommendation', 412);
        }

        $recommendationRisk = $this->createRecommendationRisk($recommendation, $instanceRisk, '', $saveInDb);

        if ($instanceRisk instanceof Entity\InstanceRisk
            && $instanceRisk->getAmv()
            && $instanceRisk->getInstance()->getObject()->isScopeGlobal()
        ) {
            /* Link recommendation for the other global instances, where AMVs are matched   */
            $siblingInstances = $this->instanceTable->findGlobalSiblingsByAnrAndInstance(
                $anr,
                $instanceRisk->getInstance()
            );
            foreach ($siblingInstances as $siblingInstance) {
                foreach ($siblingInstance->getInstanceRisks() as $siblingInstanceRisk) {
                    if ($siblingInstanceRisk->getAmv()
                        && $siblingInstanceRisk->getAmv()->getUuid() === $instanceRisk->getAmv()->getUuid()
                    ) {
                        $this->createRecommendationRisk($recommendation, $siblingInstanceRisk, '', $saveInDb);
                    }
                }
            }
        }

        $this->updateInstanceRiskRecommendationsPositions($instanceRisk);

        return $recommendationRisk;
    }

    public function patch(Entity\Anr $anr, int $id, array $data): Entity\RecommendationRisk
    {
        /** @var Entity\RecommendationRisk $recommendationRisk */
        $recommendationRisk = $this->recommendationRiskTable->findByIdAndAnr($id, $anr);

        if ($data['commentAfter'] !== $recommendationRisk->getCommentAfter()) {
            $recommendationRisk->setCommentAfter($data['commentAfter'])
                ->setUpdater($this->connectedUser->getEmail());

            $this->recommendationRiskTable->save($recommendationRisk);
        }

        return $recommendationRisk;
    }

    public function delete(Entity\Anr $anr, int $id): void
    {
        /** @var Entity\RecommendationRisk $recommendationRisk */
        $recommendationRisk = $this->recommendationRiskTable->findByIdAndAnr($id, $anr);

        $recommendation = $recommendationRisk->getRecommendation();
        $instanceRisk = $recommendationRisk->getInstanceRisk();
        if ($instanceRisk !== null
            && $instanceRisk->getAmv() !== null
            && $recommendationRisk->hasGlobalObjectRelation()
        ) {
            /* Removing all the other instance risks, except of current linked to the global object, retrieved by AMV */
            /** @var Entity\Amv $amv */
            $amv = $instanceRisk->getAmv();
            $siblingInstanceRisks = $this->instanceRiskTable->findByAmv($amv);
            $siblingInstanceRisksIds = [];
            foreach ($siblingInstanceRisks as $siblingInstanceRisk) {
                if ($instanceRisk->getInstance()->getObject()->isEqualTo(
                    $siblingInstanceRisk->getInstance()->getObject()
                )) {
                    $siblingInstanceRisksIds[] = $siblingInstanceRisk->getId();
                }
            }

            foreach ($recommendation->getRecommendationRisks() as $otherRecommendationRisk) {
                if ($otherRecommendationRisk->getInstanceRisk() !== null
                    && $otherRecommendationRisk->getId() !== $recommendationRisk->getId()
                    && \in_array($otherRecommendationRisk->getInstanceRisk()->getId(), $siblingInstanceRisksIds, true)
                ) {
                    $this->recommendationRiskTable->remove($otherRecommendationRisk);
                }
            }
        }

        $this->recommendationRiskTable->remove($recommendationRisk);

        // Reset the recommendation's position if it's not linked to other risks anymore.
        if (!$recommendation->hasLinkedRecommendationRisks()) {
            $this->resetRecommendationsPositions(
                $recommendation->getAnr(),
                [$recommendation->getUuid() => $recommendation]
            );
        }
    }

    public function getTreatmentPlan(Entity\Anr $anr): array
    {
        $linkedRecommendations = $this->recommendationTable
            ->findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
                $anr,
                [],
                ['r.position' => 'ASC']
            );

        $treatmentPlan = [];
        foreach ($linkedRecommendations as $linkedRecommendation) {
            $instanceRisksData = [];
            $globalObjects = [];
            foreach ($linkedRecommendation->getRecommendationRisks() as $index => $recommendationRisk) {
                $instanceRisk = $recommendationRisk->getInstanceRisk();
                if ($instanceRisk === null) {
                    $instanceRisk = $recommendationRisk->getInstanceRiskOp();
                    $type = 'risksop';
                    $instanceRiskData = [
                        'id' => $instanceRisk->getId(),
                        'cacheNetRisk' => $instanceRisk->getCacheNetRisk(),
                        'cacheTargetedRisk' => $instanceRisk->getCacheTargetedRisk(),
                        'comment' => $instanceRisk->getComment(),
                    ];
                } else {
                    $type = 'risks';
                    $instanceRiskData = [
                        'id' => $instanceRisk->getId(),
                        'cacheMaxRisk' => $instanceRisk->getCacheMaxRisk(),
                        'cacheTargetedRisk' => $instanceRisk->getCacheTargetedRisk(),
                        'comment' => $instanceRisk->getComment(),
                    ];
                }

                if ($type === 'risks' && $recommendationRisk->hasGlobalObjectRelation()) {
                    $path = $recommendationRisk->getInstance()->getName($anr->getLanguage());
                    $globalObjectUuid = $recommendationRisk->getGlobalObject()->getUuid();
                    $assetUuid = $recommendationRisk->getAsset()->getUuid();
                    $threatUuid = $recommendationRisk->getThreat()->getUuid();
                    $vulnerabilityUuid = $recommendationRisk->getVulnerability()->getUuid();
                    $globalObject = $globalObjects[$globalObjectUuid][$assetUuid][$threatUuid][$vulnerabilityUuid]
                        ?? null;
                    if ($globalObject !== null) {
                        if ($globalObject['maxRisk'] < $instanceRisk->getCacheMaxRisk()) {
                            $globalObjects[$globalObjectUuid][$assetUuid][$threatUuid][$vulnerabilityUuid]['maxRisk']
                                = $instanceRisk->getCacheMaxRisk();
                            $instanceRisksData[$type][$globalObject['index']] = array_merge($instanceRiskData, [
                                'path' => $path,
                                'isGlobal' => true,
                            ]);
                        }

                        continue;
                    }

                    $globalObjects[$globalObjectUuid][$assetUuid][$threatUuid][$vulnerabilityUuid] = [
                        'index' => $index,
                        'maxRisk' => $instanceRisk->getCacheMaxRisk(),
                    ];
                } else {
                    $path = $instanceRisk->getInstance()->getHierarchyString();
                }

                $instanceRisksData[$type][$index] = array_merge($instanceRiskData, [
                    'path' => $path,
                    'isGlobal' => $recommendationRisk->hasGlobalObjectRelation(),
                ]);
            }

            $treatmentPlan[] = array_merge([
                'uuid' => $linkedRecommendation->getUuid(),
                'code' => $linkedRecommendation->getCode(),
                'description' => $linkedRecommendation->getDescription(),
                'importance' => $linkedRecommendation->getImportance(),
                'position' => $linkedRecommendation->getPosition(),
                'comment' => $linkedRecommendation->getComment(),
                'status' => $linkedRecommendation->getStatus(),
                'responsable' => $linkedRecommendation->getResponsible(),
                'duedate' => $linkedRecommendation->getDueDate() !== null
                    ? $linkedRecommendation->getDueDate()->format('Y-m-d')
                    : '',
                'counterTreated' => $linkedRecommendation->getCounterTreated(),
            ], $instanceRisksData);
        }

        return $treatmentPlan;
    }

    public function createRecommendationRisk(
        Entity\Recommendation $recommendation,
        Entity\InstanceRisk|Entity\InstanceRiskOp $instanceRisk,
        string $commentAfter = '',
        bool $saveInDb = true
    ): Entity\RecommendationRisk {
        /** @var Entity\Instance $instance */
        $instance = $instanceRisk->getInstance();
        $recommendationRisk = (new Entity\RecommendationRisk())
            ->setAnr($recommendation->getAnr())
            ->setRecommendation($recommendation)
            ->setInstance($instance)
            ->setCommentAfter($commentAfter)
            ->setCreator($this->connectedUser->getEmail());
        if ($instanceRisk instanceof Entity\InstanceRiskOp) {
            $recommendationRisk->setInstanceRiskOp($instanceRisk);
        } else {
            $recommendationRisk->setInstanceRisk($instanceRisk)
                ->setAsset($instanceRisk->getAsset())
                ->setThreat($instanceRisk->getThreat())
                ->setVulnerability($instanceRisk->getVulnerability());
        }

        if ($instance->getObject()->isScopeGlobal()) {
            /** @var Entity\MonarcObject $object */
            $object = $instance->getObject();
            $recommendationRisk->setGlobalObject($object);
        }

        $this->recommendationRiskTable->save($recommendationRisk, $saveInDb);

        return $recommendationRisk;
    }

    public function resetRecommendationsPositionsToDefault(Entity\Anr $anr): void
    {
        $recommendationRisks = $this->recommendationRiskTable->findByAnrOrderByAndCanExcludeNotTreated(
            $anr,
            ['r.importance' => 'DESC', 'r.code' => 'ASC']
        );

        $position = 1;
        $updatedRecommendationsUuid = [];
        foreach ($recommendationRisks as $recommendationRisk) {
            $recommendation = $recommendationRisk->getRecommendation();
            if (!isset($updatedRecommendationsUuid[$recommendation->getUuid()])) {
                $this->recommendationTable->save(
                    $recommendation->setPosition($position++)->setUpdater($this->connectedUser->getEmail()),
                    false
                );
                $updatedRecommendationsUuid[$recommendation->getUuid()] = true;
            }
        }
        $this->recommendationTable->flush();
    }

    /**
     * Validates a recommendation risk.
     */
    public function validateFor(Entity\Anr $anr, int $recommendationRiskId, array $data): void
    {
        /** @var Entity\RecommendationRisk $recommendationRisk */
        $recommendationRisk = $this->recommendationRiskTable->findByIdAndAnr($recommendationRiskId, $anr);

        if ($recommendationRisk->getInstanceRiskOp() !== null) {
            /** @var Entity\InstanceRiskOp $instanceRiskOp */
            $instanceRiskOp = $recommendationRisk->getInstanceRiskOp();

            /* Verify if recommendation risk is final (are there more recommendations linked to the risk) */
            $isFinalValidation = $instanceRiskOp->getRecommendationRisks()->count() === 1;

            /* Tracks the change in history before modifying values. */
            $recommendationHistory = $this->recommendationHistoryService->createFromRecommendationRisk(
                $data,
                $recommendationRisk,
                $isFinalValidation,
                false
            );

            if ($isFinalValidation) {
                /* Obtain list of new controls (commentAfter), that were specified since the new validation process
                is started of all the recommendations linked to the risk. */
                $cacheCommentsAfter = $this->recommendationHistoryService->getValidatedCachedCommentsList(
                    $instanceRiskOp,
                    $recommendationHistory
                );

                /* array_reverse because "['id' => 'DESC']" */
                $instanceRiskOp->setComment(implode("\n\n", array_reverse($cacheCommentsAfter)))
                    ->setMitigation('')
                    ->setNetProb($instanceRiskOp->getTargetedProb())
                    ->setCacheNetRisk($instanceRiskOp->getCacheTargetedRisk())
                    ->setTargetedProb(-1)
                    ->setCacheTargetedRisk(-1)
                    ->setKindOfMeasure(InstanceRiskOpSuperClass::KIND_NOT_TREATED);
                foreach ($instanceRiskOp->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                    $operationalInstanceRiskScale->setNetValue($operationalInstanceRiskScale->getTargetedValue());
                    $operationalInstanceRiskScale->setTargetedValue(-1);
                }

                $this->instanceRiskOpTable->save($instanceRiskOp, false);
            }
        } elseif ($recommendationRisk->getInstanceRisk() !== null) {
            /** @var Entity\InstanceRisk $instanceRisk */
            $instanceRisk = $recommendationRisk->getInstanceRisk();

            /* Verify if recommendation risk is final (are there more recommendations linked to the risk) */
            $isFinalValidation = $instanceRisk->getRecommendationRisks()->count() === 1;

            /* Tracks the change in history before modifying values. */
            $recommendationHistory = $this->recommendationHistoryService->createFromRecommendationRisk(
                $data,
                $recommendationRisk,
                $isFinalValidation,
                false
            );

            if ($isFinalValidation) {
                /* Obtain list of new controls (commentAfter), that were specified since the new validation process
                is started of all the recommendations linked to the risk. */
                $cacheCommentsAfter = $this->recommendationHistoryService->getValidatedCachedCommentsList(
                    $instanceRisk,
                    $recommendationHistory
                );

                /* Apply reduction vulnerability on risk. */
                $oldVulRate = $instanceRisk->getVulnerabilityRate();
                $newVulnerabilityRate = $instanceRisk->getVulnerabilityRate() - $instanceRisk->getReductionAmount();
                /* array_reverse because "['id' => 'DESC']" */
                $instanceRisk
                    ->setComment(implode("\n\n", array_reverse($cacheCommentsAfter)))
                    ->setCommentAfter('')
                    ->setVulnerabilityRate($newVulnerabilityRate >= 0 ? $newVulnerabilityRate : 0)
                    ->setRiskConfidentiality($this->calculateRiskConfidentiality(
                        $instanceRisk->getInstance()->getConfidentiality(),
                        $instanceRisk->getThreatRate(),
                        $instanceRisk->getVulnerabilityRate()
                    ))->setRiskIntegrity($this->calculateRiskIntegrity(
                        $instanceRisk->getInstance()->getIntegrity(),
                        $instanceRisk->getThreatRate(),
                        $instanceRisk->getVulnerabilityRate()
                    ))->setRiskAvailability($this->calculateRiskAvailability(
                        $instanceRisk->getInstance()->getAvailability(),
                        $instanceRisk->getThreatRate(),
                        $instanceRisk->getVulnerabilityRate()
                    ));

                $risks = [];
                $impacts = [];
                if ($instanceRisk->getThreat()->getConfidentiality()) {
                    $risks[] = $instanceRisk->getRiskConfidentiality();
                    $impacts[] = $instanceRisk->getInstance()->getConfidentiality();
                }
                if ($instanceRisk->getThreat()->getIntegrity()) {
                    $risks[] = $instanceRisk->getRiskIntegrity();
                    $impacts[] = $instanceRisk->getInstance()->getIntegrity();
                }
                if ($instanceRisk->getThreat()->getAvailability()) {
                    $risks[] = $instanceRisk->getRiskAvailability();
                    $impacts[] = $instanceRisk->getInstance()->getAvailability();
                }

                $instanceRisk->setCacheMaxRisk(\count($risks) ? max($risks) : -1)
                    ->setCacheTargetedRisk($this->calculateTargetRisk(
                        $impacts,
                        $instanceRisk->getThreatRate(),
                        $oldVulRate,
                        $instanceRisk->getReductionAmount()
                    ))
                    ->setReductionAmount(0)
                    ->setKindOfMeasure(InstanceRiskSuperClass::KIND_NOT_TREATED);

                $this->instanceRiskTable->save($instanceRisk, false);

                // Impact on brothers
                if ($recommendationRisk->hasGlobalObjectRelation()) {
                    $brothersInstances = $this->instanceTable->findByAnrAndObject(
                        $recommendationRisk->getAnr(),
                        $recommendationRisk->getGlobalObject()
                    );
                    foreach ($brothersInstances as $brotherInstance) {
                        $brothersInstancesRisks = $this->instanceRiskTable->findByInstanceAndInstanceRiskRelations(
                            $brotherInstance,
                            $instanceRisk,
                            false,
                            true
                        );

                        foreach ($brothersInstancesRisks as $brotherInstanceRisk) {
                            $brotherInstanceRisk->setComment($instanceRisk->getComment())
                                ->setCommentAfter($instanceRisk->getCommentAfter())
                                ->setVulnerabilityRate($instanceRisk->getVulnerabilityRate())
                                ->setRiskConfidentiality($instanceRisk->getRiskConfidentiality())
                                ->setRiskIntegrity($instanceRisk->getRiskIntegrity())
                                ->setRiskAvailability($instanceRisk->getRiskAvailability())
                                ->setCacheMaxRisk($instanceRisk->getCacheMaxRisk())
                                ->setCacheTargetedRisk($instanceRisk->getCacheTargetedRisk())
                                ->setReductionAmount($instanceRisk->getReductionAmount())
                                ->setKindOfMeasure($instanceRisk->getKindOfMeasure());

                            $this->instanceRiskTable->save($brotherInstanceRisk, false);
                        }
                    }
                }
            }
        }

        $this->removeTheLink($recommendationRisk);
        $this->validateRecommendation($recommendationRisk->getRecommendation());
    }

    /**
     * Unlink the recommendation from related risk (remove the passed link between risks and recommendation).
     */
    private function removeTheLink(Entity\RecommendationRisk $recommendationRisk): void
    {
        if ($recommendationRisk->hasGlobalObjectRelation()) {
            $recommendationsRisksLinkedByRecommendationGlobalObjectAndAmv = $this->recommendationRiskTable
                ->findAllLinkedByRecommendationGlobalObjectAndAmv($recommendationRisk);

            foreach ($recommendationsRisksLinkedByRecommendationGlobalObjectAndAmv as $linkedRecommendationRisk) {
                $this->recommendationRiskTable->remove($linkedRecommendationRisk, false);
            }
            $this->recommendationRiskTable->flush();
        } else {
            $this->recommendationRiskTable->remove($recommendationRisk);
        }
    }

    private function validateRecommendation(Entity\Recommendation $recommendation): void
    {
        $recommendation->incrementCounterTreated();

        if (!$recommendation->hasLinkedRecommendationRisks()) {
            $recommendation->setDueDate(null)
                ->setResponsible('')
                ->setComment('');

            $this->resetRecommendationsPositions(
                $recommendation->getAnr(),
                [$recommendation->getUuid() => $recommendation]
            );
        }

        $this->recommendationTable->save($recommendation);
    }

    private function getPreparedRecommendationRiskData(
        Entity\RecommendationRisk $recommendationRisk,
        bool $extendedFormat = true
    ): array {
        $recommendation = $recommendationRisk->getRecommendation();
        $recommendationRiskData = [
            'id' => $recommendationRisk->getId(),
            'recommendation' => [
                'uuid' => $recommendation->getUuid(),
                'code' => $recommendation->getCode(),
                'description' => $recommendation->getDescription(),
                'importance' => $recommendation->getImportance(),
                'position' => $recommendation->getPosition(),
                'recommendationSet' => [
                    'uuid' => $recommendation->getRecommendationSet()->getUuid(),
                    'label' => $recommendation->getRecommendationSet()->getLabel(),
                ],
            ],
            'commentAfter' => $recommendationRisk->getCommentAfter(),
        ];
        if ($extendedFormat) {
            $instance = $recommendationRisk->getInstance();
            $recommendationRiskData['instance'] = array_merge([
                'id' => $instance->getId(),
                'object' => [
                    'uuid' => $instance->getObject()->getUuid(),
                ],
            ], $instance->getNames());
            $recommendationRiskData['asset'] = array_merge([
                'uuid' => $instance->getAsset()->getUuid(),
                'type' => $instance->getAsset()->getType(),
            ], $instance->getAsset()->getLabels());
            if ($recommendationRisk->getThreat() !== null && $recommendationRisk->getVulnerability() !== null) {
                $recommendationRiskData['threat'] = array_merge([
                    'uuid' => $recommendationRisk->getThreat()->getUuid(),
                    'code' => $recommendationRisk->getThreat()->getCode(),
                ], $recommendationRisk->getThreat()->getLabels());
                $recommendationRiskData['vulnerability'] = array_merge([
                    'uuid' => $recommendationRisk->getVulnerability()->getUuid(),
                    'code' => $recommendationRisk->getVulnerability()->getCode(),
                ], $recommendationRisk->getVulnerability()->getLabels());
            }
            if ($recommendationRisk->getInstanceRisk()) {
                $recommendationRiskData['instanceRisk'] = [
                    'id' => $recommendationRisk->getInstanceRisk()->getId(),
                    'kindOfMeasure' => $recommendationRisk->getInstanceRisk()->getKindOfMeasure(),
                    'cacheMaxRisk' => $recommendationRisk->getInstanceRisk()->getCacheMaxRisk(),
                    'cacheTargetedRisk' => $recommendationRisk->getInstanceRisk()->getCacheTargetedRisk(),
                    'comment' => $recommendationRisk->getInstanceRisk()->getComment(),
                ];
            }
            if ($recommendationRisk->getInstanceRiskOp()) {
                $recommendationRiskData['instanceRiskOp'] = array_merge([
                    'id' => $recommendationRisk->getInstanceRiskOp()->getId(),
                    'kindOfMeasure' => $recommendationRisk->getInstanceRiskOp()->getKindOfMeasure(),
                    'cacheNetRisk' => $recommendationRisk->getInstanceRiskOp()->getCacheNetRisk(),
                    'cacheTargetedRisk' => $recommendationRisk->getInstanceRiskOp()->getCacheTargetedRisk(),
                    'rolfRisk' => [
                        'id' => $recommendationRisk->getInstanceRiskOp()->getRolfRisk()?->getId(),
                    ],
                    'comment' => $recommendationRisk->getInstanceRiskOp()->getComment(),
                ], $recommendationRisk->getInstanceRiskOp()->getRiskCacheLabels());
            }
        }

        return $recommendationRiskData;
    }
}
