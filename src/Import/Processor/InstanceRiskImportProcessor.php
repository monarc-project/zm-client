<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\ScaleSuperClass;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Import\Traits\EvaluationConverterTrait;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;
use Monarc\FrontOffice\Service\AnrRecommendationRiskService;
use Monarc\FrontOffice\Service\InstanceRiskOwnerService;
use Monarc\FrontOffice\Table\InstanceRiskTable;

class InstanceRiskImportProcessor
{
    use EvaluationConverterTrait;

    public function __construct(
        private InstanceRiskTable $instanceRiskTable,
        private AnrInstanceRiskService $anrInstanceRiskService,
        private InformationRiskImportProcessor $informationRiskImportProcessor,
        private RecommendationImportProcessor $recommendationImportProcessor,
        private ThreatImportProcessor $threatImportProcessor,
        private VulnerabilityImportProcessor $vulnerabilityImportProcessor,
        private ImportCacheHelper $importCacheHelper,
        private AnrRecommendationRiskService $anrRecommendationRiskService,
        private InstanceRiskOwnerService $instanceRiskOwnerService
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
            $amv = $this->informationRiskImportProcessor
                ->processInformationRiskData($anr, $instanceRiskData['informationRisk']);
            $threat = null;
            $vulnerability = null;
        } else {
            $amv = null;
            $threat = $this->threatImportProcessor->processThreatData($anr, $instanceRiskData['threat']);
            $vulnerability = $this->vulnerabilityImportProcessor
                ->processVulnerabilityData($anr, $instanceRiskData['vulnerability']);
        }

        $instanceRisk = $this->anrInstanceRiskService
            ->createInstanceRisk($instance, $amv, null, $threat, $vulnerability);

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
                ->setRiskConfidentiality($instanceRiskData['riskConfidentiality'])
                ->setRiskIntegrity($instanceRiskData['riskIntegrity'])
                ->setRiskAvailability($instanceRiskData['riskAvailability'])
                ->setThreatRate($instanceRiskData['threatRate'])
                ->setVulnerabilityRate($instanceRiskData['vulnerabilityRate'])
                ->setReductionAmount($instanceRiskData['reductionAmount'])
                ->setCacheMaxRisk($instanceRiskData['cacheMaxRisk'])
                ->setCacheTargetedRisk($instanceRiskData['cacheTargetedRisk'])
                ->setKindOfMeasure($instanceRiskData['kindOfMeasure'])
                ->setComment($instanceRiskData['comment'] ?? '')
                ->setCommentAfter($instanceRiskData['commentAfter'] ?? '')
                ->setIsThreatRateNotSetOrModifiedExternally(
                    (bool)$instanceRiskData['isThreatRateNotSetOrModifiedExternally']
                )
                ->setContext($instanceRiskData['context'] ?? '');
            if (!empty($instanceRiskData['riskOwner'])) {
                $this->instanceRiskOwnerService
                    ->processRiskOwnerNameAndAssign($instanceRiskData['riskOwner'], $instanceRisk);
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
            ->setAmv($fromInstanceRisk->getAmv())
            ->setContext($fromInstanceRisk->getContext())
            ->setInstanceRiskOwner($fromInstanceRisk->getInstanceRiskOwner());
        $this->anrInstanceRiskService->recalculateRiskRates($toInstanceRisk);

        $this->instanceRiskTable->save($toInstanceRisk, false);
    }

    private function convertInstanceRiskEvaluations(array &$instanceRiskData): void
    {
        $currentScaleRange = $this->importCacheHelper
            ->getItemFromArrayCache('current_scales_data_by_type')[ScaleSuperClass::TYPE_IMPACT];
        $externalScaleRange = $this->importCacheHelper
            ->getItemFromArrayCache('external_scales_data_by_type')[ScaleSuperClass::TYPE_IMPACT];
        foreach (['riskConfidentiality', 'riskIntegrity', 'riskAvailability', 'threatRate', 'vulnerabilityRate',
            'reductionAmount', 'cacheMaxRisk', 'cacheTargetedRisk'] as $propertyName) {
            $instanceRiskData[$propertyName] = $this->convertValueWithinNewScalesRange(
                $instanceRiskData[$propertyName],
                $externalScaleRange['min'],
                $externalScaleRange['max'],
                $currentScaleRange['min'],
                $currentScaleRange['max'],
            );
        }
    }
}
