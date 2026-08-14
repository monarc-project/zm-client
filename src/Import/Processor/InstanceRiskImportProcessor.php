<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use DateTime;
use Monarc\Core\Entity\ScaleSuperClass;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Import\Traits\EvaluationConverterTrait;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;
use Monarc\FrontOffice\Service\AnrRecommendationRiskService;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table\InstanceRiskTable;

class InstanceRiskImportProcessor
{
    use EvaluationConverterTrait;

    public function __construct(
        private InstanceRiskTable $instanceRiskTable,
        private AnrInstanceRiskService $anrInstanceRiskService,
        private InformationRiskImportProcessor $informationRiskImportProcessor,
        private RecommendationImportProcessor $recommendationImportProcessor,
        private RiskSourceImportProcessor $riskSourceImportProcessor,
        private ThreatImportProcessor $threatImportProcessor,
        private VulnerabilityImportProcessor $vulnerabilityImportProcessor,
        private ImportCacheHelper $importCacheHelper,
        private AnrRecommendationRiskService $anrRecommendationRiskService,
        private AnrSupervisorService $anrSupervisorService
    ) {
    }

    public function processInstanceRisksData(
        Entity\Instance $instance,
        array $siblingInstances,
        array $instanceRisksData
    ): void {
        /* Create new instance risks. */
        foreach ($instanceRisksData as $instanceRiskData) {
            $this->processInstanceRiskData($instance, $instanceRiskData);
        }
        if (!empty($siblingInstances)) {
            /* Match the created instance risks with sibling instances' ones. */
            $this->matchCreatedInstanceRisksWithSiblingInstances($instance, $siblingInstances);
        }
    }

    private function processInstanceRiskData(Entity\Instance $instance, array $instanceRiskData): Entity\InstanceRisk
    {
        /** @var Entity\Anr $anr */
        $anr = $instance->getAnr();
        if (!empty($instanceRiskData['informationRisk'])) {
            /* The case of normal instance risk, where threat and vulnerability are taken from AMV. */
            $amv = $this->informationRiskImportProcessor
                ->processInformationRiskData($anr, $instanceRiskData['informationRisk']);
            $threat = null;
            $vulnerability = null;
        } else {
            /* The case of specific instance risk that has no relation to AMV. */
            $amv = null;
            $threat = $this->threatImportProcessor->processThreatData($anr, $instanceRiskData['threat']);
            $vulnerability = $this->vulnerabilityImportProcessor
                ->processVulnerabilityData($anr, $instanceRiskData['vulnerability']);
        }

        $instanceRisk = $this->anrInstanceRiskService
            ->createInstanceRisk($instance, $amv, null, $threat, $vulnerability);

        if (!empty($instanceRiskData['riskSource'])) {
            $instanceRisk->setRiskSource(
                $this->riskSourceImportProcessor->processRiskSourceData($anr, $instanceRiskData['riskSource'])
            );
        }

        foreach ($instanceRiskData['recommendations'] as $recommendationData) {
            $recommendationSet = $this->recommendationImportProcessor
                ->processRecommendationSetData($anr, $recommendationData['recommendationSet']);
            $recommendation = $this->recommendationImportProcessor
                ->processRecommendationData($recommendationSet, $recommendationData);
            $this->anrRecommendationRiskService->createRecommendationRisk(
                $recommendation,
                $instanceRisk,
                $recommendationData['commentAfter'] ?? '',
                false
            );
        }

        if ($this->importCacheHelper->getValueFromArrayCache('with_eval')) {
            /* For the instances import the values have to be converted to local scales. */
            if ($this->importCacheHelper
                ->getValueFromArrayCache('import_type') === InstanceImportService::IMPORT_TYPE_INSTANCE
            ) {
                $this->convertInstanceRiskEvaluations($instanceRiskData);
            }

            $instanceRisk
                ->setContext($instanceRiskData['context'] ?? '')
                ->setRiskConfidentiality((int)$instanceRiskData['riskConfidentiality'])
                ->setRiskIntegrity((int)$instanceRiskData['riskIntegrity'])
                ->setRiskAvailability((int)$instanceRiskData['riskAvailability'])
                ->setThreatRate((int)$instanceRiskData['threatRate'])
                ->setVulnerabilityRate((int)$instanceRiskData['vulnerabilityRate'])
                ->setReductionAmount((int)$instanceRiskData['reductionAmount'])
                ->setCacheMaxRisk((int)$instanceRiskData['cacheMaxRisk'])
                ->setCacheTargetedRisk((int)$instanceRiskData['cacheTargetedRisk'])
                ->setKindOfMeasure((int)$instanceRiskData['kindOfMeasure'])
                ->setComment($instanceRiskData['comment'] ?? '')
                ->setCommentAfter($instanceRiskData['commentAfter'] ?? '')
                ->setIsThreatRateNotSetOrModifiedExternally(
                    (bool)$instanceRiskData['isThreatRateNotSetOrModifiedExternally']
                );
            if (array_key_exists('lastReviewDate', $instanceRiskData)) {
                $instanceRisk->setLastReviewDate(
                    empty($instanceRiskData['lastReviewDate'])
                        ? null
                        : (DateTime::createFromFormat('Y-m-d', $instanceRiskData['lastReviewDate']) ?: null)
                );
            }
            if (array_key_exists('reviewFrequency', $instanceRiskData)) {
                $reviewFrequency = trim((string)$instanceRiskData['reviewFrequency']);
                $instanceRisk->setReviewFrequency($reviewFrequency === '' ? null : $reviewFrequency);
            }
            $riskOwnerSupervisor = $instanceRiskData['riskOwnerSupervisor'] ?? null;
            if (!empty($riskOwnerSupervisor) && is_array($riskOwnerSupervisor)) {
                $this->anrSupervisorService->assignRiskOwnerSupervisorData(
                    $anr,
                    $riskOwnerSupervisor,
                    $instanceRisk,
                    false
                );
            } else {
                $legacyRiskOwnerName = trim((string)($instanceRiskData['riskOwner'] ?? ''));
                if ($legacyRiskOwnerName !== '') {
                    $this->anrSupervisorService->assignRiskOwnerSupervisorName(
                        $anr,
                        $legacyRiskOwnerName,
                        $instanceRisk,
                        false
                    );
                }
            }
            if (array_key_exists('residualRiskDecision', $instanceRiskData)) {
                $instanceRisk->setResidualRiskDecision(
                    $this->normalizeResidualRiskDecision($instanceRiskData['residualRiskDecision'])
                );
            }
            if (array_key_exists('residualAcceptanceUseRiskOwner', $instanceRiskData)) {
                $instanceRisk->setResidualAcceptanceUseRiskOwner((bool)$instanceRiskData['residualAcceptanceUseRiskOwner']);
            }
            $residualAcceptanceApproverSupervisor = $instanceRiskData['residualAcceptanceApproverSupervisor'] ?? null;
            if (!empty($residualAcceptanceApproverSupervisor) && is_array($residualAcceptanceApproverSupervisor)) {
                $instanceRisk->setResidualAcceptanceApproverSupervisor(
                    $this->anrSupervisorService->getOrCreateSupervisor(
                        $anr,
                        $residualAcceptanceApproverSupervisor['name'] ?? null,
                        $residualAcceptanceApproverSupervisor['email'] ?? null,
                        [Entity\AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER],
                        false
                    )
                );
            }
            if (array_key_exists('residualRiskDecidedAt', $instanceRiskData)) {
                $instanceRisk->setResidualRiskDecidedAt(
                    empty($instanceRiskData['residualRiskDecidedAt'])
                        ? null
                        : (DateTime::createFromFormat('Y-m-d', $instanceRiskData['residualRiskDecidedAt']) ?: null)
                );
            }
            if (array_key_exists('residualAcceptancePerformedByName', $instanceRiskData)) {
                $performedByName = trim((string)$instanceRiskData['residualAcceptancePerformedByName']);
                $instanceRisk->setResidualAcceptancePerformedByName($performedByName === '' ? null : $performedByName);
            }
            if (array_key_exists('residualAcceptancePerformedByEmail', $instanceRiskData)) {
                $performedByEmail = trim((string)$instanceRiskData['residualAcceptancePerformedByEmail']);
                $instanceRisk->setResidualAcceptancePerformedByEmail($performedByEmail === '' ? null : $performedByEmail);
            }
            if (array_key_exists('residualAcceptancePerformedOnBehalf', $instanceRiskData)) {
                $instanceRisk->setResidualAcceptancePerformedOnBehalf(
                    (bool)$instanceRiskData['residualAcceptancePerformedOnBehalf']
                );
            }
            if (array_key_exists('residualRiskJustification', $instanceRiskData)) {
                $residualRiskJustification = trim((string)$instanceRiskData['residualRiskJustification']);
                $instanceRisk->setResidualRiskJustification(
                    $residualRiskJustification === '' ? null : $residualRiskJustification
                );
            }
            $residualRiskAcceptance = $instanceRiskData['residualRiskAcceptance'] ?? null;
            if (!empty($residualRiskAcceptance) && is_array($residualRiskAcceptance)) {
                $instanceRisk->setResidualRiskDecision(
                    $this->normalizeResidualRiskDecision($residualRiskAcceptance['decision'] ?? null)
                );
                if (array_key_exists('performedByName', $residualRiskAcceptance)) {
                    $performedByName = trim((string)$residualRiskAcceptance['performedByName']);
                    $instanceRisk->setResidualAcceptancePerformedByName($performedByName === '' ? null : $performedByName);
                }
                if (array_key_exists('performedByEmail', $residualRiskAcceptance)) {
                    $performedByEmail = trim((string)$residualRiskAcceptance['performedByEmail']);
                    $instanceRisk->setResidualAcceptancePerformedByEmail($performedByEmail === '' ? null : $performedByEmail);
                }
                if (array_key_exists('performedOnBehalf', $residualRiskAcceptance)) {
                    $instanceRisk->setResidualAcceptancePerformedOnBehalf(
                        (bool)$residualRiskAcceptance['performedOnBehalf']
                    );
                }
                $approverData = $residualRiskAcceptance['approver'] ?? null;
                $legacyDecidedBy = trim((string)($residualRiskAcceptance['decidedBy'] ?? ''));
                $legacyDecidedByEmail = trim((string)($residualRiskAcceptance['decidedByEmail'] ?? ''));
                if (empty($approverData) && ($legacyDecidedBy !== '' || $legacyDecidedByEmail !== '')) {
                    $approverData = [
                        'name' => $legacyDecidedBy !== '' ? $legacyDecidedBy : null,
                        'email' => $legacyDecidedByEmail !== '' ? $legacyDecidedByEmail : null,
                    ];
                }
                if (!empty($approverData) && is_array($approverData)) {
                    $approverSupervisor = !empty($riskOwnerSupervisor)
                        && is_array($riskOwnerSupervisor)
                        && (($riskOwnerSupervisor['name'] ?? '') === ($approverData['name'] ?? '')
                            || ($riskOwnerSupervisor['email'] ?? '') === ($approverData['email'] ?? ''))
                        ? $instanceRisk->getRiskOwnerSupervisor()
                        : $this->anrSupervisorService->getOrCreateSupervisor(
                            $anr,
                            $approverData['name'] ?? null,
                            $approverData['email'] ?? null,
                            [Entity\AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER],
                            false
                        );
                    $instanceRisk->setResidualAcceptanceApproverSupervisor($approverSupervisor)
                        ->setResidualRiskDecidedBySupervisor($approverSupervisor);
                }
                if (!empty($residualRiskAcceptance['date'])) {
                    $date = DateTime::createFromFormat('Y-m-d', (string)$residualRiskAcceptance['date']) ?: null;
                    $instanceRisk->setResidualRiskDecidedAt($date);
                }
                if (array_key_exists('justification', $residualRiskAcceptance)) {
                    $justification = trim((string)$residualRiskAcceptance['justification']);
                    $instanceRisk->setResidualRiskJustification($justification === '' ? null : $justification);
                }
            }
        }

        $this->anrInstanceRiskService->recalculateRiskRates($instanceRisk);

        $this->instanceRiskTable->save($instanceRisk, false);

        return $instanceRisk;
    }

    /**
     * @param Entity\Instance[] $siblingInstances
     */
    private function matchCreatedInstanceRisksWithSiblingInstances(
        Entity\Instance $instance,
        array $siblingInstances
    ): void {
        $createdRiskKeys = [];
        $siblingRiskKeys = [];
        $withEval = $this->importCacheHelper->getValueFromArrayCache('with_eval');
        foreach ($siblingInstances as $siblingInstance) {
            /** @var Entity\InstanceRisk $createdInstanceRisk */
            foreach ($instance->getInstanceRisks() as $createdInstanceRisk) {
                $createdRiskKey = $createdInstanceRisk->getAsset()->getUuid()
                    . $createdInstanceRisk->getThreat()->getUuid()
                    . $createdInstanceRisk->getVulnerability()->getUuid();
                $createdRiskKeys[$createdRiskKey] = $createdInstanceRisk;
                $isRiskMatched = false;
                foreach ($siblingInstance->getInstanceRisks() as $siblingInstanceRisk) {
                    $siblingRiskKey = $siblingInstanceRisk->getAsset()->getUuid()
                        . $siblingInstanceRisk->getThreat()->getUuid()
                        . $siblingInstanceRisk->getVulnerability()->getUuid();
                    $siblingRiskKeys[$siblingRiskKey][] = $siblingInstanceRisk;
                    if ($createdRiskKey === $siblingRiskKey) {
                        if ($withEval) {
                            /* Apply the evaluations to the sibling instance's risk. */
                            $this->applyRiskDataToItsSibling($createdInstanceRisk, $siblingInstanceRisk);
                        } else {
                            /* Apply not evaluated data to the created risk from the sibling one. */
                            $this->applyRiskDataToItsSibling($siblingInstanceRisk, $createdInstanceRisk);
                        }
                        $isRiskMatched = true;
                    }
                }
                /* If the instance risk is not presented then create from the original one */
                if (!$isRiskMatched) {
                    /** @var ?Entity\Amv $amv */
                    $amv = $createdInstanceRisk->getAmv();
                    $newSiblingInstanceRisk = $this->anrInstanceRiskService
                        ->createInstanceRisk($siblingInstance, $amv, $createdInstanceRisk);
                    foreach ($createdInstanceRisk->getRecommendationRisks() as $createdRecommendationRisk) {
                        $newSiblingRecommendationRisk = $this->anrRecommendationRiskService->createRecommendationRisk(
                            $createdRecommendationRisk->getRecommendation(),
                            $newSiblingInstanceRisk,
                            $createdRecommendationRisk->getCommentAfter()
                        );
                        $newSiblingInstanceRisk->addRecommendationRisk($newSiblingRecommendationRisk);
                    }
                    $this->instanceRiskTable->save($newSiblingInstanceRisk, false);
                }
            }
        }
        /* Remove not matched instance risks. */
        /** @var Entity\InstanceRisk[] $siblingInstanceRisksToRemove */
        foreach (array_diff_key($siblingRiskKeys, $createdRiskKeys) as $siblingInstanceRisksToRemove) {
            foreach ($siblingInstanceRisksToRemove as $siblingInstanceRiskToRemove) {
                $siblingInstanceRiskToRemove->getInstance()->removeInstanceRisk($siblingInstanceRiskToRemove);
                $this->instanceRiskTable->remove($siblingInstanceRiskToRemove, false);
            }
        }
    }

    private function applyRiskDataToItsSibling(
        Entity\InstanceRisk $fromInstanceRisk,
        Entity\InstanceRisk $toInstanceRisk
    ): void {
        $toInstanceRisk
            ->setContext($fromInstanceRisk->getContext())
            ->setRiskOwnerSupervisor($fromInstanceRisk->getRiskOwnerSupervisor())
            ->setLastReviewDate($fromInstanceRisk->getLastReviewDate())
            ->setReviewFrequency($fromInstanceRisk->getReviewFrequency())
            ->setResidualRiskDecision($fromInstanceRisk->getResidualRiskDecision())
            ->setResidualAcceptanceUseRiskOwner($fromInstanceRisk->isResidualAcceptanceUseRiskOwner())
            ->setResidualAcceptanceApproverSupervisor($fromInstanceRisk->getResidualAcceptanceApproverSupervisor())
            ->setResidualAcceptancePerformedByName($fromInstanceRisk->getResidualAcceptancePerformedByName())
            ->setResidualAcceptancePerformedByEmail($fromInstanceRisk->getResidualAcceptancePerformedByEmail())
            ->setResidualAcceptancePerformedOnBehalf($fromInstanceRisk->isResidualAcceptancePerformedOnBehalf())
            ->setResidualRiskDecidedBySupervisor($fromInstanceRisk->getResidualRiskDecidedBySupervisor())
            ->setResidualRiskDecidedByUser($fromInstanceRisk->getResidualRiskDecidedByUser())
            ->setResidualRiskDecidedAt($fromInstanceRisk->getResidualRiskDecidedAt())
            ->setResidualRiskJustification($fromInstanceRisk->getResidualRiskJustification())
            ->setThreatRate($fromInstanceRisk->getThreatRate())
            ->setVulnerabilityRate($fromInstanceRisk->getVulnerabilityRate())
            ->setKindOfMeasure($fromInstanceRisk->getKindOfMeasure())
            ->setReductionAmount($fromInstanceRisk->getReductionAmount())
            ->setRiskConfidentiality($fromInstanceRisk->getRiskConfidentiality())
            ->setRiskIntegrity($fromInstanceRisk->getRiskIntegrity())
            ->setRiskAvailability($fromInstanceRisk->getRiskAvailability())
            ->setCacheMaxRisk($fromInstanceRisk->getCacheMaxRisk())
            ->setCacheTargetedRisk($fromInstanceRisk->getCacheTargetedRisk())
            ->setSpecific((int)$fromInstanceRisk->isSpecific())
            ->setAmv($fromInstanceRisk->getAmv());
        if ($fromInstanceRisk->getComment() !== '') {
            $toInstanceRisk->setComment($fromInstanceRisk->getComment());
        }
        if ($fromInstanceRisk->getCommentAfter() !== '') {
            $toInstanceRisk->setCommentAfter($fromInstanceRisk->getCommentAfter());
        }

        $this->anrInstanceRiskService->recalculateRiskRates($toInstanceRisk);

        $this->instanceRiskTable->save($toInstanceRisk, false);
    }

    private function convertInstanceRiskEvaluations(array &$instanceRiskData): void
    {
        $currentScalesRanges = $this->importCacheHelper->getItemFromArrayCache('current_scales_data_by_type');
        $externalScalesRanges = $this->importCacheHelper->getItemFromArrayCache('external_scales_data_by_type');
        foreach (['riskConfidentiality', 'riskIntegrity', 'riskAvailability'] as $propertyName) {
            $instanceRiskData[$propertyName] = $this->convertValueWithinNewScalesRange(
                $instanceRiskData[$propertyName],
                $externalScalesRanges[ScaleSuperClass::TYPE_IMPACT]['min'],
                $externalScalesRanges[ScaleSuperClass::TYPE_IMPACT]['max'],
                $currentScalesRanges[ScaleSuperClass::TYPE_IMPACT]['min'],
                $currentScalesRanges[ScaleSuperClass::TYPE_IMPACT]['max'],
            );
        }
        $instanceRiskData['threatRate'] = $this->convertValueWithinNewScalesRange(
            $instanceRiskData['threatRate'],
            $externalScalesRanges[ScaleSuperClass::TYPE_THREAT]['min'],
            $externalScalesRanges[ScaleSuperClass::TYPE_THREAT]['max'],
            $currentScalesRanges[ScaleSuperClass::TYPE_THREAT]['min'],
            $currentScalesRanges[ScaleSuperClass::TYPE_THREAT]['max'],
        );
        $previousVulnerabilityRate = $instanceRiskData['vulnerabilityRate'];
        $instanceRiskData['vulnerabilityRate'] = $this->convertValueWithinNewScalesRange(
            $instanceRiskData['vulnerabilityRate'],
            $externalScalesRanges[ScaleSuperClass::TYPE_VULNERABILITY]['min'],
            $externalScalesRanges[ScaleSuperClass::TYPE_VULNERABILITY]['max'],
            $currentScalesRanges[ScaleSuperClass::TYPE_VULNERABILITY]['min'],
            $currentScalesRanges[ScaleSuperClass::TYPE_VULNERABILITY]['max'],
        );
        $instanceRiskData['reductionAmount'] = $this->convertValueWithinNewScalesRange(
            $instanceRiskData['reductionAmount'],
            0,
            $previousVulnerabilityRate,
            0,
            $instanceRiskData['vulnerabilityRate'],
            0
        );
    }

    private function normalizeResidualRiskDecision(mixed $decision): ?string
    {
        $decision = mb_strtolower(trim((string)$decision));

        return match ($decision) {
            '', null => null,
            'accepted' => 'accepted',
            'rejected', 'not_accepted' => 'not_accepted',
            default => $decision,
        };
    }
}
