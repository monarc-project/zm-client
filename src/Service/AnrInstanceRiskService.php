<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use DateTime;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity as CoreEntity;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Traits\ImpactVerificationTrait;
use Monarc\Core\Service\Traits\RiskCalculationTrait;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Entity\AnrHistory;
use Monarc\FrontOffice\Entity\AnrSupervisorRole;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskService
{
    use RiskCalculationTrait;
    use ImpactVerificationTrait;
    use RecommendationsPositionsUpdateTrait;

    private CoreEntity\UserSuperClass $connectedUser;

    public function __construct(
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\RecommendationRiskTable $recommendationRiskTable,
        private Table\InstanceTable $instanceTable,
        private Table\ThreatTable $threatTable,
        private Table\VulnerabilityTable $vulnerabilityTable,
        private Table\ScaleTable $scaleTable,
        private Table\RiskSourceTable $riskSourceTable,
        private Table\ReassessmentTriggerTable $reassessmentTriggerTable,
        private Table\RecommendationTable $recommendationTable,
        private TranslateService $translateService,
        private AnrSupervisorService $anrSupervisorService,
        private AnrHistoryService $anrHistoryService,
        private ImportCacheHelper $importCacheHelper,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getInstanceRisks(Entity\Anr $anr, ?int $instanceId, array $params = []): array
    {
        if ($instanceId !== null) {
            /** @var Entity\Instance $instance */
            $instance = $this->instanceTable->findByIdAndAnr($instanceId, $anr);
            $params['instanceIds'] = $instance->getSelfAndChildrenIds();
        }

        $languageIndex = $anr->getLanguage();

        /** @var Entity\InstanceRisk[] $instanceRisks */
        $instanceRisks = $this->instanceRiskTable->findInstancesRisksByParams($anr, $languageIndex, $params);

        $result = [];
        foreach ($instanceRisks as $instanceRisk) {
            $object = $instanceRisk->getInstance()->getObject();
            $threat = $instanceRisk->getThreat();
            $vulnerability = $instanceRisk->getVulnerability();
            $riskSource = $instanceRisk->getRiskSource();
            $key = $object->isScopeGlobal()
                ? 'o' . $object->getUuid() . '-' . $threat->getUuid() . '-' . $vulnerability->getUuid()
                : 'r' . $instanceRisk->getId();
            if (!isset($result[$key]) || $this->areInstanceRiskImpactsHigher($instanceRisk, $result[$key])) {
                $recommendationsUuids = [];
                foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                    if ($recommendationRisk->getRecommendation() !== null) {
                        $recommendationsUuids[] = $recommendationRisk->getRecommendation()->getUuid();
                    }
                }
                $measures = [];
                if ($instanceRisk->getAmv() !== null) {
                    foreach ($instanceRisk->getAmv()->getMeasures() as $measure) {
                        $measures[] = [
                            'uuid' => $measure->getUuid(),
                            'code' => $measure->getCode(),
                            'label' . $languageIndex => $measure->getLabel($languageIndex),
                            'referential' => [
                                'uuid' => $measure->getReferential()->getUuid(),
                                'label' . $languageIndex => $measure->getReferential()->getLabel($languageIndex),
                            ],
                        ];
                    }
                }

                $result[$key] = [
                    'id' => $instanceRisk->getId(),
                    'oid' => $object->getUuid(),
                    'instance' => $instanceRisk->getInstance()->getId(),
                    'instanceName' . $languageIndex => $instanceRisk->getInstance()->getName($languageIndex),
                    'amv' => $instanceRisk->getAmv()?->getUuid(),
                    'asset' => $instanceRisk->getAsset()->getUuid(),
                    'assetLabel' . $languageIndex => $instanceRisk->getAsset()->getLabel($languageIndex),
                    'assetDescription' . $languageIndex => $instanceRisk->getAsset()->getDescription($languageIndex),
                    'riskSourceId' => $riskSource?->getId(),
                    'riskSourceLabel' => $riskSource?->getLabel() ?? '',
                    'threat' => $threat->getUuid(),
                    'threatCode' => $threat->getCode(),
                    'threatLabel' . $languageIndex => $threat->getLabel($languageIndex),
                    'threatDescription' . $languageIndex => $threat->getDescription($languageIndex),
                    'threatRate' => $instanceRisk->getThreatRate(),
                    'vulnerability' => $vulnerability->getUuid(),
                    'vulnCode' => $vulnerability->getCode(),
                    'vulnLabel' . $languageIndex => $vulnerability->getLabel($languageIndex),
                    'vulnDescription' . $languageIndex => $vulnerability->getDescription($languageIndex),
                    'vulnerabilityRate' => $instanceRisk->getVulnerabilityRate(),
                    'specific' => $instanceRisk->getSpecific(),
                    'reductionAmount' => $instanceRisk->getReductionAmount(),
                    'c_impact' => $instanceRisk->getInstance()->getConfidentiality(),
                    'c_risk' => $instanceRisk->getRiskConfidentiality(),
                    'c_risk_enabled' => $threat->getConfidentiality(),
                    'i_impact' => $instanceRisk->getInstance()->getIntegrity(),
                    'i_risk' => $instanceRisk->getRiskIntegrity(),
                    'i_risk_enabled' => $threat->getIntegrity(),
                    'd_impact' => $instanceRisk->getInstance()->getAvailability(),
                    'd_risk' => $instanceRisk->getRiskAvailability(),
                    'd_risk_enabled' => $threat->getAvailability(),
                    'target_risk' => $instanceRisk->getCacheTargetedRisk(),
                    'max_risk' => $instanceRisk->getCacheMaxRisk(),
                    'comment' => $instanceRisk->getComment(),
                    'scope' => $object->getScope(),
                    'kindOfMeasure' => $instanceRisk->getKindOfMeasure(),
                    'lastReviewDate' => $instanceRisk->getLastReviewDate()?->format('Y-m-d'),
                    'nextReassessmentDate' => $instanceRisk->getNextReassessmentDate()?->format('Y-m-d'),
                    'reassessmentTriggers' => $this->prepareReassessmentTriggers($instanceRisk->getReassessmentTriggers()),
                    'reviewFrequency' => $instanceRisk->getReviewFrequency(),
                    'reviewFrequencyLabel' => $this->getReviewFrequencyLabel(
                        $instanceRisk->getReviewFrequency(),
                        $languageIndex
                    ),
                    'residualRiskDecision' => $instanceRisk->getResidualRiskDecision(),
                    'residualRiskDecidedAt' => $instanceRisk->getResidualRiskDecidedAt()?->format('Y-m-d'),
                    'residualAcceptanceUseRiskOwner' => $instanceRisk->isResidualAcceptanceUseRiskOwner(),
                    'residualAcceptanceApproverSupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                        $instanceRisk->getResidualAcceptanceApproverSupervisor()
                    ),
                    'residualAcceptanceApproverSupervisorId' => $instanceRisk->getResidualAcceptanceApproverSupervisor()?->getId(),
                    'residualAcceptancePerformedByName' => $instanceRisk->getResidualAcceptancePerformedByName(),
                    'residualAcceptancePerformedByEmail' => $instanceRisk->getResidualAcceptancePerformedByEmail(),
                    'residualAcceptancePerformedOnBehalf' => $instanceRisk->isResidualAcceptancePerformedOnBehalf(),
                    'residualRiskDecidedBySupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                        $instanceRisk->getResidualRiskDecidedBySupervisor()
                    ),
                    'residualRiskDecidedBySupervisorId' => $instanceRisk->getResidualRiskDecidedBySupervisor()?->getId(),
                    'residualRiskDecidedByUserId' => $instanceRisk->getResidualRiskDecidedByUser()?->getId(),
                    'residualRiskJustification' => $instanceRisk->getResidualRiskJustification(),
                    't' => $instanceRisk->isTreated(),
                    'tid' => $threat->getUuid(),
                    'vid' => $vulnerability->getUuid(),
                    'context' => $instanceRisk->getContext(),
                    'owner' => $this->getRiskOwnerName($instanceRisk),
                    'riskOwnerSupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                        $instanceRisk->getRiskOwnerSupervisor()
                    ),
                    'riskOwnerSupervisorId' => $instanceRisk->getRiskOwnerSupervisor()?->getId(),
                    'riskOwnerSupervisorName' => $instanceRisk->getRiskOwnerSupervisor()?->getName(),
                    'recommendations' => implode(',', $recommendationsUuids),
                    'measures' => $measures,
                ];
            }
        }

        return array_values($result);
    }

    public function createInstanceRisk(
        Entity\Instance $instance,
        ?Entity\Amv $amv,
        ?Entity\InstanceRisk $fromInstanceRisk = null,
        ?Entity\Threat $threat = null,
        ?Entity\Vulnerability $vulnerability = null,
        bool $saveInDb = false
    ): Entity\InstanceRisk {
        if ($fromInstanceRisk === null && $amv === null && $threat === null && $vulnerability === null) {
            throw new \LogicException('Instance risk can\'t be created without threat and vulnerability.');
        }

        $instanceRisk = $fromInstanceRisk !== null
            ? Entity\InstanceRisk::constructFromObjectOfTheSameAnr($fromInstanceRisk)
            : new Entity\InstanceRisk();

        $instanceRisk
            ->setInstance($instance)
            ->setCreator($this->connectedUser->getEmail());
        if ($fromInstanceRisk === null) {
            $instanceRisk
                ->setAnr($instance->getAnr())
                ->setAmv($amv)
                ->setAsset($amv !== null ? $amv->getAsset() : $instance->getAsset())
                ->setThreat($amv !== null ? $amv->getThreat() : $threat)
                ->setVulnerability($amv !== null ? $amv->getVulnerability() : $vulnerability);
            if ($amv === null) {
                $instanceRisk->setSpecific(CoreEntity\InstanceRiskSuperClass::TYPE_SPECIFIC);
            }
        } else {
            /* The evaluation values are only set when the object is created based on the other instance risk. */
            $this->recalculateRiskRates($instanceRisk);
        }

        $this->instanceRiskTable->save($instanceRisk, $saveInDb);

        return $instanceRisk;
    }

    /**
     * Is used when a new library object is instantiated to an ANR and during the import.
     */
    public function createInstanceRisks(
        Entity\Instance $instance,
        Entity\MonarcObject $object,
        array $params = [],
        bool $saveInDb = true
    ): void {
        $siblingInstance = null;
        if ($object->isScopeGlobal()) {
            $siblingInstance = $this->instanceTable
                ->findOneByAnrAndObjectExcludeInstance($instance->getAnr(), $object, $instance);
        }

        if ($siblingInstance !== null) {
            /* In case the object is global and another instance is already presented in the ANR,
            the same risks have to be created (including possible specific ones). */
            foreach ($siblingInstance->getInstanceRisks() as $siblingInstanceRisk) {
                /** @var Entity\Amv $amv */
                $amv = $siblingInstanceRisk->getAmv();
                $instanceRisk = $this
                    ->createInstanceRisk($instance, $amv, $siblingInstanceRisk, null, null, $saveInDb);

                $this->duplicateRecommendationRisks($siblingInstanceRisk, $instanceRisk);
                $this->updateInstanceRiskRecommendationsPositions($instanceRisk);
            }
        } else {
            foreach ($object->getAsset()->getAmvs() as $amv) {
                $instanceRisk = $this->createInstanceRisk($instance, $amv, null, null, null, $saveInDb);

                /* Process risk owner and context in case of import. */
                if (!empty($params['risks'])) {
                    $riskKey = array_search($amv->getUuid(), array_column($params['risks'], 'amv'), true);
                    if ($riskKey !== false) {
                        $instanceRiskData = array_values($params['risks'])[$riskKey];
                        $instanceRisk->setContext($instanceRiskData['context'] ?? '');
                        $riskSourceLabel = $instanceRiskData['riskSourceLabel']
                            ?? ($instanceRiskData['riskSource']['label'] ?? null);
                        /** @var Entity\Anr $anr */
                        $anr = $instance->getAnr();
                        if (!empty($riskSourceLabel)) {
                            $instanceRisk->setRiskSource(
                                $this->getOrCreateRiskSourceByLabel($anr, $riskSourceLabel, $saveInDb)
                            );
                        }
                        $riskOwnerSupervisor = $instanceRiskData['riskOwnerSupervisor']
                            ?? $instanceRiskData['risk_owner_supervisor']
                            ?? null;
                        if (!empty($riskOwnerSupervisor) && is_array($riskOwnerSupervisor)) {
                            $this->anrSupervisorService->assignRiskOwnerSupervisorData(
                                $anr,
                                $riskOwnerSupervisor,
                                $instanceRisk,
                                $saveInDb
                            );
                        } elseif (!empty($instanceRiskData['riskOwner'])) {
                            $this->anrSupervisorService->assignRiskOwnerSupervisorName(
                                $anr,
                                (string)$instanceRiskData['riskOwner'],
                                $instanceRisk,
                                $saveInDb
                            );
                        }
                        if (array_key_exists('lastReviewDate', $instanceRiskData)) {
                            $instanceRisk->setLastReviewDate(
                                $this->createLastReviewDateFromString($instanceRiskData['lastReviewDate'])
                            );
                        }
                        if (array_key_exists('reviewFrequency', $instanceRiskData)) {
                            $reviewFrequency = trim((string)$instanceRiskData['reviewFrequency']);
                            $instanceRisk->setReviewFrequency($reviewFrequency === '' ? null : $reviewFrequency);
                        }
                    }
                }

                $this->instanceRiskTable->save($instanceRisk, false);
            }
        }

        if ($saveInDb) {
            $this->instanceRiskTable->flush();
        }
    }

    public function createSpecificInstanceRisk(Entity\Anr $anr, array $data): Entity\InstanceRisk
    {
        /** @var Entity\Instance $instance */
        $instance = $this->instanceTable->findByIdAndAnr($data['instance'], $anr);
        /** @var Entity\Threat $threat */
        $threat = $this->threatTable->findByUuidAndAnr($data['threat'], $anr);
        /** @var Entity\Vulnerability $vulnerability */
        $vulnerability = $this->vulnerabilityTable->findByUuidAndAnr($data['vulnerability'], $anr);

        if ($this->instanceRiskTable
            ->existsInAnrWithInstanceThreatAndVulnerability($instance, $threat, $vulnerability)
        ) {
            throw new Exception('This risk already exists in this instance', 412);
        }

        $instanceRisk = (new Entity\InstanceRisk())
            ->setAnr($anr)
            ->setInstance($instance)
            ->setAsset($instance->getAsset())
            ->setThreat($threat)
            ->setVulnerability($vulnerability)
            ->setSpecific(CoreEntity\InstanceRiskSuperClass::TYPE_SPECIFIC)
            ->setCreator($this->connectedUser->getEmail());

        if ($instance->getObject()->isScopeGlobal()) {
            /* Creates the same specific instance risk inside sibling instances based on the global object. */
            $siblingGlobalInstances = $this->instanceTable->findGlobalSiblingsByAnrAndInstance($anr, $instance);
            foreach ($siblingGlobalInstances as $siblingGlobalInstance) {
                $this->instanceRiskTable->save(
                    Entity\InstanceRisk::constructFromObjectOfTheSameAnr($instanceRisk)
                        ->setInstance($siblingGlobalInstance)
                        ->setCreator($this->connectedUser->getEmail()),
                    false
                );
            }
        }

        $this->instanceRiskTable->save($instanceRisk);

        return $instanceRisk;
    }

    public function update(
        Entity\Anr $anr,
        int $id,
        array $data,
        bool $manageGlobal = true
    ): Entity\InstanceRisk {
        /** @var Entity\InstanceRisk $instanceRisk */
        $instanceRisk = $this->instanceRiskTable->findByIdAndAnr($id, $anr);
        $historyBeforeByRiskId = [$instanceRisk->getId() => $this->captureHistoryState($instanceRisk)];

        $this->verifyInstanceRiskRates($instanceRisk, $this->scaleTable, $data);

        $this->updateInstanceRiskData($instanceRisk, $data);

        if ($manageGlobal) {
            /* The impact has to be updated for the siblings / other global instances and risks. */
            $object = $instanceRisk->getInstance()->getObject();
            if ($object->isScopeGlobal()) {
                $instances = $this->instanceTable->findByAnrAndObject($instanceRisk->getAnr(), $object);

                foreach ($instances as $instance) {
                    if ($instanceRisk->getInstance()->getId() === $instance->getId()) {
                        continue;
                    }

                    $siblingInstancesRisks = $this->instanceRiskTable->findByInstanceAndInstanceRiskRelations(
                        $instance,
                        $instanceRisk
                    );

                    foreach ($siblingInstancesRisks as $siblingInstanceRisk) {
                        $historyBeforeByRiskId[$siblingInstanceRisk->getId()] = $this->captureHistoryState(
                            $siblingInstanceRisk
                        );
                        $this->updateInstanceRiskData($siblingInstanceRisk, $data);
                    }
                }
            }
        }

        $this->instanceRiskTable->save($instanceRisk);
        $this->recordHistoryChanges($anr, $instanceRisk, $historyBeforeByRiskId[$instanceRisk->getId()]);

        if ($manageGlobal) {
            $object = $instanceRisk->getInstance()->getObject();
            if ($object->isScopeGlobal()) {
                $instances = $this->instanceTable->findByAnrAndObject($instanceRisk->getAnr(), $object);

                foreach ($instances as $instance) {
                    if ($instanceRisk->getInstance()->getId() === $instance->getId()) {
                        continue;
                    }

                    $siblingInstancesRisks = $this->instanceRiskTable->findByInstanceAndInstanceRiskRelations(
                        $instance,
                        $instanceRisk
                    );

                    foreach ($siblingInstancesRisks as $siblingInstanceRisk) {
                        if (isset($historyBeforeByRiskId[$siblingInstanceRisk->getId()])) {
                            $this->recordHistoryChanges(
                                $anr,
                                $siblingInstanceRisk,
                                $historyBeforeByRiskId[$siblingInstanceRisk->getId()]
                            );
                        }
                    }
                }
            }
        }

        return $instanceRisk;
    }

    public function delete(Entity\Anr $anr, int $id): void
    {
        /** @var Entity\InstanceRisk $instanceRisk */
        $instanceRisk = $this->instanceRiskTable->findByIdAndAnr($id, $anr);

        if (!$instanceRisk->isSpecific()) {
            throw new Exception('You can not delete a not specific risk', 412);
        }

        /* If the object is global, delete all risks linked to sibling instances. */
        if ($instanceRisk->getInstance()->getObject()->isScopeGlobal()) {
            $siblingInstanceRisks = $this->instanceRiskTable->findSiblingSpecificInstanceRisks($instanceRisk);
            foreach ($siblingInstanceRisks as $siblingInstanceRisk) {
                $this->instanceRiskTable->remove($siblingInstanceRisk, false);
            }
        }

        $this->instanceRiskTable->remove($instanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($instanceRisk);
    }

    public function recalculateRiskRatesAndUpdateRecommendationsPositions(Entity\InstanceRisk $instanceRisk): void
    {
        $this->recalculateRiskRates($instanceRisk);

        $this->updateInstanceRiskRecommendationsPositions($instanceRisk);
    }

    public function getInstanceRisksInCsv(Entity\Anr $anr, int $instanceId = null, array $params = []): string
    {
        $languageIndex = $anr->getLanguage();

        // Fill in the header
        $output = implode(';', [
            $this->translateService->translate('Asset', $languageIndex),
            $this->translateService->translate('Risk source', $languageIndex),
            $this->translateService->translate('C Impact', $languageIndex),
            $this->translateService->translate('I Impact', $languageIndex),
            $this->translateService->translate('A Impact', $languageIndex),
            $this->translateService->translate('Threat', $languageIndex),
            $this->translateService->translate('Prob.', $languageIndex),
            $this->translateService->translate('Vulnerability', $languageIndex),
            $this->translateService->translate('Existing controls', $languageIndex),
            $this->translateService->translate('Qualif.', $languageIndex),
            $this->translateService->translate('Current risk', $languageIndex). ' C',
            $this->translateService->translate('Current risk', $languageIndex) . ' I',
            $this->translateService->translate('Current risk', $languageIndex) . ' '
                . $this->translateService->translate('A', $languageIndex),
            $this->translateService->translate('Treatment', $languageIndex),
            $this->translateService->translate('Residual risk', $languageIndex),
            $this->translateService->translate('Risk owner', $languageIndex),
            $this->translateService->translate('Risk context', $languageIndex),
            $this->translateService->translate('Last review date', $languageIndex),
            $this->translateService->translate('Review frequency', $languageIndex),
            $this->translateService->translate('Residual risk acceptance decision', $languageIndex),
            $this->translateService->translate('Residual risk acceptance approver', $languageIndex),
            $this->translateService->translate('Residual risk decision date', $languageIndex),
            $this->translateService->translate('Performed by', $languageIndex),
            $this->translateService->translate('Performed on behalf', $languageIndex),
            $this->translateService->translate('Residual risk acceptance justification', $languageIndex),
            $this->translateService->translate('Recommendations', $languageIndex),
            $this->translateService->translate('Security referentials', $languageIndex),
        ]) . "\n";

        if ($instanceId !== null) {
            /** @var Entity\Instance $instance */
            $instance = $this->instanceTable->findByIdAndAnr($instanceId, $anr);
            $params['instanceIds'] = $instance->getSelfAndChildrenIds();
        }

        /** @var Entity\InstanceRisk[] $instanceRisks */
        $instanceRisks = $this->instanceRiskTable->findInstancesRisksByParams($anr, $languageIndex, $params);

        $impactValues = [];
        foreach ($instanceRisks as $instanceRisk) {
            $instance = $instanceRisk->getInstance();
            $object = $instance->getObject();
            $threat = $instanceRisk->getThreat();
            $vulnerability = $instanceRisk->getVulnerability();
            $key = $object->isScopeGlobal()
                ? 'o' . $object->getUuid() . '-' . $threat->getUuid() . '-' . $vulnerability->getUuid()
                : 'r' . $instanceRisk->getId();
            if (!isset($values[$key]) || $this->areInstanceRiskImpactsHigher($instanceRisk, $impactValues[$key])) {
                $recommendationData = [];
                foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                    $recommendationData[] = $recommendationRisk->getRecommendation()->getCode()
                        . ' - ' . $recommendationRisk->getRecommendation()->getDescription();
                }
                $measuresData = [];
                if ($instanceRisk->getAmv() !== null) {
                    foreach ($instanceRisk->getAmv()->getMeasures() as $measure) {
                        $measuresData[] = '[' . $measure->getReferential()->getLabel($anr->getLanguage()) . '] '
                            . $measure->getCode() . ' - ' . $measure->getLabel($anr->getLanguage());
                    }
                }

                $impactValues[$key] = [
                    'max_risk' => $instanceRisk->getCacheMaxRisk(),
                    'c_impact' => $instance->getConfidentiality(),
                    'i_impact' => $instance->getIntegrity(),
                    'd_impact' => $instance->getAvailability(),
                ];

                $values[$key] = [
                    $instance->getName($languageIndex),
                    $instanceRisk->getRiskSource()?->getLabel(),
                    $instance->getConfidentiality() === -1 ? null : $instance->getConfidentiality(),
                    $instance->getIntegrity() === -1 ? null : $instance->getIntegrity(),
                    $instance->getAvailability() === -1 ? null : $instance->getAvailability(),
                    $threat->getLabel($languageIndex),
                    $instanceRisk->getThreatRate() === -1 ? null : $instanceRisk->getThreatRate(),
                    $vulnerability->getLabel($languageIndex),
                    $instanceRisk->getComment(),
                    $instanceRisk->getVulnerabilityRate() === -1 ? null : $instanceRisk->getVulnerabilityRate(),
                    $threat->getConfidentiality() === 0 || $instanceRisk->getRiskConfidentiality() === -1
                        ? null
                        : $instanceRisk->getRiskConfidentiality(),
                    $instanceRisk->getThreat()->getIntegrity() === 0 || $instanceRisk->getRiskIntegrity() === -1
                        ? null
                        : $instanceRisk->getRiskIntegrity(),
                    $instanceRisk->getThreat()->getAvailability() === 0 || $instanceRisk->getRiskAvailability() === -1
                        ? null
                        : $instanceRisk->getRiskAvailability(),
                    $this->translateService->translate(
                        Entity\InstanceRisk::getAvailableMeasureTypes()[$instanceRisk->getKindOfMeasure()],
                        $languageIndex
                    ),
                    $instanceRisk->getCacheTargetedRisk() === -1 ? null : $instanceRisk->getCacheTargetedRisk(),
                    $this->getRiskOwnerName($instanceRisk),
                    $instanceRisk->getContext(),
                    $instanceRisk->getLastReviewDate()?->format('Y-m-d'),
                    $this->getReviewFrequencyLabel($instanceRisk->getReviewFrequency(), $languageIndex),
                    $instanceRisk->getResidualRiskDecision() !== null
                        ? $this->translateResidualRiskDecision($instanceRisk->getResidualRiskDecision(), $languageIndex)
                        : null,
                    $instanceRisk->getResidualAcceptanceApproverSupervisor()?->getName(),
                    $instanceRisk->getResidualRiskDecidedAt()?->format('Y-m-d'),
                    $instanceRisk->getResidualAcceptancePerformedByName(),
                    $instanceRisk->isResidualAcceptancePerformedOnBehalf()
                        ? $this->translateService->translate('Yes', $languageIndex)
                        : $this->translateService->translate('No', $languageIndex),
                    $instanceRisk->getResidualRiskJustification(),
                    implode("\n", $recommendationData),
                    implode("\n", $measuresData),
                ];

                $output .= '"';
                $search = ['"'];
                $replace = ["'"];
                $output .= implode('";"', str_replace($search, $replace, $values[$key]));
                $output .= "\"\r\n";
            }
        }

        return $output;
    }

    private function updateInstanceRiskData(Entity\InstanceRisk $instanceRisk, array $data): void
    {
        $previousRiskOwnerSupervisorId = $instanceRisk->getRiskOwnerSupervisor()?->getId();

        /* If the request is from the Supervisor who is just approving the risk (fills 3 fields)
            or Risk Owner (2 fields) then we allow it without changing the risk owner of approver. */
        if (array_key_exists('riskSourceId', $data)) {
            $riskSourceId = $data['riskSourceId'];
            $instanceRisk->setRiskSource(
                $riskSourceId === null || $riskSourceId === ''
                    ? null
                    : $this->riskSourceTable->findById((int)$riskSourceId)
            );
        }
        if (array_key_exists('lastReviewDate', $data)) {
            $instanceRisk->setLastReviewDate($this->prepareLastReviewDate($instanceRisk, $data['lastReviewDate']));
        }
        if (array_key_exists('nextReassessmentDate', $data)) {
            $instanceRisk->setNextReassessmentDate($this->createDateFromString(
                $data['nextReassessmentDate'],
                'Invalid next reassessment date format.'
            ));
        }
        if (array_key_exists('lastReviewDate', $data) && $instanceRisk->getNextReassessmentDate() === null) {
            $instanceRisk->setNextReassessmentDate(
                $this->getDefaultNextReassessmentDate($instanceRisk->getLastReviewDate())
            );
        }
        if (array_key_exists('reassessmentTriggerIds', $data)) {
            if ($data['reassessmentTriggerIds'] !== [] && $instanceRisk->getNextReassessmentDate() === null) {
                throw new Exception('A next reassessment date is required when selecting trigger criteria.', 412);
            }
            $instanceRisk->setReassessmentTriggers($data['reassessmentTriggerIds'] === []
                ? []
                : $this->reassessmentTriggerTable->findByIdsAndAnr(
                    $data['reassessmentTriggerIds'],
                    $instanceRisk->getAnr()
                ));
        }
        if (array_key_exists('reviewFrequency', $data)) {
            $reviewFrequency = trim((string)$data['reviewFrequency']);
            $instanceRisk->setReviewFrequency($reviewFrequency === '' ? null : $reviewFrequency);
        }
        if (array_key_exists('residualRiskDecision', $data)) {
            $residualRiskDecision = mb_strtolower(trim((string)$data['residualRiskDecision']));
            $instanceRisk->setResidualRiskDecision($residualRiskDecision === '' ? null : $residualRiskDecision);
        }
        if (array_key_exists('residualRiskJustification', $data)) {
            $residualRiskJustification = trim((string)$data['residualRiskJustification']);
            $instanceRisk->setResidualRiskJustification(
                $residualRiskJustification === '' ? null : $residualRiskJustification
            );
        }
        if (array_key_exists('riskOwnerSupervisorId', $data)) {
            $this->anrSupervisorService->assignRiskOwnerSupervisorById(
                $instanceRisk->getAnr(),
                $data['riskOwnerSupervisorId'],
                $instanceRisk
            );
        }
        if ($previousRiskOwnerSupervisorId !== $instanceRisk->getRiskOwnerSupervisor()?->getId()
            && (
                $instanceRisk->getRiskOwnerSupervisor() === null
                || $instanceRisk->isResidualAcceptanceUseRiskOwner()
            )
        ) {
            $this->resetResidualRiskAcceptanceData($instanceRisk);
        }
        $this->applyResidualRiskAcceptanceData($instanceRisk, $data);
        if (isset($data['context'])) {
            $instanceRisk->setContext($data['context']);
        }
        if (isset($data['reductionAmount'])) {
            $instanceRisk->setReductionAmount((int)$data['reductionAmount']);
        }
        if (isset($data['threatRate']) && $instanceRisk->getThreatRate() !== $data['threatRate']) {
            $instanceRisk->setThreatRate((int)$data['threatRate'])
                ->setIsThreatRateNotSetOrModifiedExternally(false);
        }
        if (isset($data['vulnerabilityRate'])) {
            $instanceRisk->setVulnerabilityRate((int)$data['vulnerabilityRate']);
        }
        if (isset($data['comment'])) {
            $instanceRisk->setComment($data['comment']);
        }
        if (isset($data['kindOfMeasure'])) {
            $instanceRisk->setKindOfMeasure((int)$data['kindOfMeasure']);
        }

        $instanceRisk->setUpdater($this->connectedUser->getEmail());

        $this->recalculateRiskRatesAndUpdateRecommendationsPositions($instanceRisk);
    }

    private function getOrCreateRiskSourceByLabel(
        Entity\Anr $anr,
        string $label,
        bool $saveInDb = true
    ): Entity\RiskSource {
        $normalizedLabel = trim($label);
        $riskSource = $this->getRiskSourceFromImportCache($anr, $normalizedLabel);
        if ($riskSource !== null) {
            return $riskSource;
        }

        $riskSource = (new Entity\RiskSource())
            ->setAnr($anr)
            ->setLabel($normalizedLabel)
            ->setIsDefault(false)
            ->setIsActive(true)
            ->setCreator($this->connectedUser->getEmail());
        $this->riskSourceTable->save($riskSource, $saveInDb);
        $this->cacheRiskSource($riskSource);

        return $riskSource;
    }

    private function getRiskSourceFromImportCache(Entity\Anr $anr, string $label): ?Entity\RiskSource
    {
        $cacheKey = $this->getRiskSourceCacheKey($label);
        /** @var ?Entity\RiskSource $cachedRiskSource */
        $cachedRiskSource = $this->importCacheHelper->getItemFromArrayCache('risk_sources_by_label', $cacheKey);
        if ($cachedRiskSource !== null) {
            return $cachedRiskSource;
        }

        $riskSource = $this->riskSourceTable->findOneByAnrAndLabel($anr, $label);
        if ($riskSource !== null) {
            $this->cacheRiskSource($riskSource);
        }

        return $riskSource;
    }

    private function cacheRiskSource(Entity\RiskSource $riskSource): void
    {
        $this->importCacheHelper->addItemToArrayCache(
            'risk_sources_by_label',
            $riskSource,
            $this->getRiskSourceCacheKey($riskSource->getLabel())
        );
    }

    private function getRiskSourceCacheKey(string $label): string
    {
        return mb_strtolower(trim($label));
    }

    private function prepareLastReviewDate(Entity\InstanceRisk $instanceRisk, mixed $lastReviewDate): ?DateTime
    {
        $normalizedDate = $this->createLastReviewDateFromString($lastReviewDate);
        $currentLastReviewDate = $instanceRisk->getLastReviewDate();
        if ($normalizedDate !== null
            && $currentLastReviewDate !== null
            && $normalizedDate->format('Y-m-d') !== $currentLastReviewDate->format('Y-m-d')
            && $normalizedDate <= $currentLastReviewDate
        ) {
            throw new Exception('Last review date must be later than the existing last review date.', 412);
        }

        return $normalizedDate;
    }

    public function prepareResidualRiskAcceptanceData(Entity\InstanceRisk $instanceRisk): array
    {
        return [
            'residualRiskDecision' => $instanceRisk->getResidualRiskDecision(),
            'residualRiskDecidedAt' => $instanceRisk->getResidualRiskDecidedAt()?->format('Y-m-d'),
            'residualAcceptanceUseRiskOwner' => $instanceRisk->isResidualAcceptanceUseRiskOwner(),
            'residualAcceptanceApproverSupervisor' => $this->anrSupervisorService->prepareSupervisorReference(
                $instanceRisk->getResidualAcceptanceApproverSupervisor()
            ),
            'residualAcceptanceApproverSupervisorId' => $instanceRisk
                ->getResidualAcceptanceApproverSupervisor()?->getId(),
            'residualAcceptancePerformedByName' => $instanceRisk->getResidualAcceptancePerformedByName(),
            'residualAcceptancePerformedByEmail' => $instanceRisk->getResidualAcceptancePerformedByEmail(),
            'residualAcceptancePerformedOnBehalf' => $instanceRisk->isResidualAcceptancePerformedOnBehalf(),
            'residualRiskJustification' => $instanceRisk->getResidualRiskJustification(),
        ];
    }

    private function createLastReviewDateFromString(mixed $lastReviewDate): ?DateTime
    {
        return $this->createDateFromString($lastReviewDate, 'Invalid last review date format.');
    }

    private function createDateFromString(
        mixed $dateValue,
        string $exceptionMessage = 'Invalid date format.'
    ): ?DateTime {
        if ($dateValue === null || $dateValue === '') {
            return null;
        }

        $normalizedDate = DateTime::createFromFormat('Y-m-d', (string)$dateValue);
        if ($normalizedDate === false) {
            throw new Exception($exceptionMessage, 412);
        }

        return $normalizedDate;
    }

    private function applyResidualRiskAcceptanceData(Entity\InstanceRisk $instanceRisk, array $data): void
    {
        $hasResidualAcceptancePayload = false;
        foreach ([
            'residualAcceptanceUseRiskOwner',
            'residualAcceptanceApproverSupervisorId',
            'residualRiskDecision',
            'residualRiskDecidedAt',
            'residualRiskJustification',
            'residualAcceptancePerformedByName',
            'residualAcceptancePerformedByEmail',
            'residualAcceptancePerformedOnBehalf',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $hasResidualAcceptancePayload = true;
                break;
            }
        }

        if (!$hasResidualAcceptancePayload) {
            return;
        }

        $previousUseRiskOwner = $instanceRisk->isResidualAcceptanceUseRiskOwner();
        $previousApproverSupervisorId = $instanceRisk->getResidualAcceptanceApproverSupervisor()?->getId();
        $useRiskOwner = array_key_exists('residualAcceptanceUseRiskOwner', $data)
            ? (bool)$data['residualAcceptanceUseRiskOwner']
            : $instanceRisk->isResidualAcceptanceUseRiskOwner();
        $riskOwnerSupervisor = $instanceRisk->getRiskOwnerSupervisor();
        if ($riskOwnerSupervisor === null) {
            $this->resetResidualRiskAcceptanceData($instanceRisk);

            return;
        }

        if ($useRiskOwner) {
            if (!$riskOwnerSupervisor->isActive()
                || !$riskOwnerSupervisor->hasRole(AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER)
            ) {
                $this->resetResidualRiskAcceptanceData($instanceRisk);

                return;
            }

            $approverSupervisor = $riskOwnerSupervisor;
        } else {
            $approverSupervisor = array_key_exists('residualAcceptanceApproverSupervisorId', $data)
                ? $this->anrSupervisorService->getResidualRiskApproverSupervisor(
                    $instanceRisk->getAnr(),
                    $data['residualAcceptanceApproverSupervisorId']
                )
                : $instanceRisk->getResidualAcceptanceApproverSupervisor();
        }

        if ($approverSupervisor === null) {
            $this->resetResidualRiskAcceptanceData($instanceRisk);

            return;
        }

        $shouldResetDecisionOnApproverContextChange = (!$previousUseRiskOwner && $useRiskOwner)
            || $previousApproverSupervisorId !== $approverSupervisor->getId();

        $instanceRisk->setResidualAcceptanceUseRiskOwner($useRiskOwner)
            ->setResidualAcceptanceApproverSupervisor($approverSupervisor);

        $performedOnBehalf = false;
        $canCurrentUserDecide = false;
        if ($approverSupervisor->getLinkedUser() === null) {
            $performedOnBehalf = true;
            $canCurrentUserDecide = true;
        } elseif ($this->isCurrentUserAllowedToApprove($approverSupervisor->getLinkedUser())) {
            $canCurrentUserDecide = true;
        }

        $hasDecisionPayload = array_key_exists('residualRiskDecision', $data)
            || array_key_exists('residualRiskDecidedAt', $data)
            || array_key_exists('residualRiskJustification', $data)
            || array_key_exists('residualAcceptancePerformedByName', $data)
            || array_key_exists('residualAcceptancePerformedByEmail', $data)
            || array_key_exists('residualAcceptancePerformedOnBehalf', $data);
        if (!$hasDecisionPayload) {
            return;
        }

        if (!$canCurrentUserDecide) {
            if ($shouldResetDecisionOnApproverContextChange && $this->isResidualRiskDecisionResetPayload($data)) {
                $this->clearResidualRiskDecisionData($instanceRisk);

                return;
            }

            if (!$this->hasResidualRiskDecisionChanges($instanceRisk, $data)) {
                return;
            }

            throw new Exception('Residual risk acceptance decision is read-only for the current user.', 403);
        }

        if (array_key_exists('residualRiskDecision', $data)) {
            $instanceRisk->setResidualRiskDecision($data['residualRiskDecision']);
        }
        if (array_key_exists('residualRiskDecidedAt', $data)) {
            $instanceRisk->setResidualRiskDecidedAt($this->createDateFromString(
                $data['residualRiskDecidedAt'],
                'Invalid residual risk decision date format.'
            ));
        }
        if (array_key_exists('residualRiskJustification', $data)) {
            $instanceRisk->setResidualRiskJustification($data['residualRiskJustification']);
        }

        $instanceRisk
            ->setResidualAcceptancePerformedByName($this->getCurrentUserSnapshotName())
            ->setResidualAcceptancePerformedByEmail($this->connectedUser->getEmail())
            ->setResidualAcceptancePerformedOnBehalf($performedOnBehalf)
            ->setResidualRiskDecidedBySupervisor($approverSupervisor)
            ->setResidualRiskDecidedByUser($this->connectedUser);
    }

    private function resetResidualRiskAcceptanceData(Entity\InstanceRisk $instanceRisk): void
    {
        $instanceRisk
            ->setResidualAcceptanceUseRiskOwner(false)
            ->setResidualAcceptanceApproverSupervisor(null);

        $this->clearResidualRiskDecisionData($instanceRisk);
    }

    private function clearResidualRiskDecisionData(Entity\InstanceRisk $instanceRisk): void
    {
        $instanceRisk
            ->setResidualRiskDecision(null)
            ->setResidualRiskDecidedAt(null)
            ->setResidualRiskJustification(null)
            ->setResidualAcceptancePerformedByName(null)
            ->setResidualAcceptancePerformedByEmail(null)
            ->setResidualAcceptancePerformedOnBehalf(false)
            ->setResidualRiskDecidedBySupervisor(null)
            ->setResidualRiskDecidedByUser(null);
    }

    private function getCurrentUserSnapshotName(): string
    {
        $fullName = trim(sprintf(
            '%s %s',
            (string)$this->connectedUser->getFirstname(),
            (string)$this->connectedUser->getLastname()
        ));

        return $fullName !== '' ? $fullName : (string)$this->connectedUser->getEmail();
    }

    private function isCurrentUserAllowedToApprove(?Entity\User $linkedUser): bool
    {
        if ($linkedUser !== null && $linkedUser->getId() === $this->connectedUser->getId()) {
            return true;
        }

        return false;
    }

    private function hasResidualRiskDecisionChanges(Entity\InstanceRisk $instanceRisk, array $data): bool
    {
        if (array_key_exists('residualRiskDecision', $data)
            && $this->normalizeNullableLowercaseText($data['residualRiskDecision'])
                !== $this->normalizeNullableLowercaseText($instanceRisk->getResidualRiskDecision())
        ) {
            return true;
        }

        if (array_key_exists('residualRiskDecidedAt', $data)
            && $this->normalizeNullableDateValue($data['residualRiskDecidedAt'])
                !== $this->normalizeNullableDateValue($instanceRisk->getResidualRiskDecidedAt()?->format('Y-m-d'))
        ) {
            return true;
        }

        if (array_key_exists('residualRiskJustification', $data)
            && $this->normalizeNullableText($data['residualRiskJustification'])
                !== $this->normalizeNullableText($instanceRisk->getResidualRiskJustification())
        ) {
            return true;
        }

        if (array_key_exists('residualAcceptancePerformedByName', $data)
            && $this->normalizeNullableText($data['residualAcceptancePerformedByName'])
                !== $this->normalizeNullableText($instanceRisk->getResidualAcceptancePerformedByName())
        ) {
            return true;
        }

        if (array_key_exists('residualAcceptancePerformedByEmail', $data)
            && $this->normalizeNullableText($data['residualAcceptancePerformedByEmail'])
                !== $this->normalizeNullableText($instanceRisk->getResidualAcceptancePerformedByEmail())
        ) {
            return true;
        }

        return array_key_exists('residualAcceptancePerformedOnBehalf', $data)
            && (bool)$data['residualAcceptancePerformedOnBehalf']
                !== $instanceRisk->isResidualAcceptancePerformedOnBehalf();
    }

    private function isResidualRiskDecisionResetPayload(array $data): bool
    {
        return (!array_key_exists('residualRiskDecision', $data)
                || $this->normalizeNullableLowercaseText($data['residualRiskDecision']) === null)
            && (!array_key_exists('residualRiskDecidedAt', $data)
                || $this->normalizeNullableDateValue($data['residualRiskDecidedAt']) === null)
            && (!array_key_exists('residualRiskJustification', $data)
                || $this->normalizeNullableText($data['residualRiskJustification']) === null)
            && (!array_key_exists('residualAcceptancePerformedByName', $data)
                || $this->normalizeNullableText($data['residualAcceptancePerformedByName']) === null)
            && (!array_key_exists('residualAcceptancePerformedByEmail', $data)
                || $this->normalizeNullableText($data['residualAcceptancePerformedByEmail']) === null)
            && (!array_key_exists('residualAcceptancePerformedOnBehalf', $data)
                || (bool)$data['residualAcceptancePerformedOnBehalf'] === false);
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function normalizeNullableLowercaseText(mixed $value): ?string
    {
        $value = $this->normalizeNullableText($value);

        return $value === null ? null : mb_strtolower($value);
    }

    private function normalizeNullableDateValue(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function translateResidualRiskDecision(?string $decision, int $languageIndex): ?string
    {
        return match ($decision) {
            Entity\InstanceRisk::RESIDUAL_RISK_DECISION_ACCEPTED => $this->translateService->translate(
                'Accepted',
                $languageIndex
            ),
            Entity\InstanceRisk::RESIDUAL_RISK_DECISION_REJECTED,
            Entity\InstanceRisk::RESIDUAL_RISK_DECISION_NOT_ACCEPTED => $this->translateService->translate(
                'Not accepted',
                $languageIndex
            ),
            default => $decision,
        };
    }

    private function getRiskOwnerName(Entity\InstanceRisk $instanceRisk): string
    {
        return $instanceRisk->getRiskOwnerSupervisor()?->getName() ?? '';
    }

    private function getReviewFrequencyLabel(?string $reviewFrequency, int $languageIndex): string
    {
        if ($reviewFrequency === null || $reviewFrequency === '') {
            return '';
        }

        if (\in_array($reviewFrequency, Entity\InstanceRisk::getAvailableReviewFrequencies(), true)) {
            return $this->translateService->translate($reviewFrequency, $languageIndex);
        }

        return $reviewFrequency;
    }

    private function getDefaultNextReassessmentDate(?DateTime $lastReviewDate): ?DateTime
    {
        return $lastReviewDate === null ? null : (clone $lastReviewDate)->modify('+1 year');
    }

    /** @param Entity\ReassessmentTrigger[] $reassessmentTriggers */
    private function prepareReassessmentTriggers(iterable $reassessmentTriggers): array
    {
        $result = [];
        foreach ($reassessmentTriggers as $reassessmentTrigger) {
            $result[] = [
                'id' => $reassessmentTrigger->getId(),
                'triggerType' => $reassessmentTrigger->getTriggerType(),
                'description' => $reassessmentTrigger->getDescription(),
            ];
        }

        return $result;
    }

    private function captureHistoryState(Entity\InstanceRisk $instanceRisk): array
    {
        return [
            'riskOwner' => $this->getRiskOwnerName($instanceRisk),
            'riskSource' => $instanceRisk->getRiskSource()?->getLabel(),
            'context' => $instanceRisk->getContext(),
            'lastReviewDate' => $instanceRisk->getLastReviewDate()?->format('Y-m-d'),
            'reviewFrequency' => $instanceRisk->getReviewFrequency(),
            'threatRate' => $this->normalizeHistoryScaleValue($instanceRisk->getThreatRate()),
            'vulnerabilityRate' => $this->normalizeHistoryScaleValue($instanceRisk->getVulnerabilityRate()),
            'currentRisk' => [
                'c' => $this->normalizeHistoryScaleValue($instanceRisk->getRiskConfidentiality()),
                'i' => $this->normalizeHistoryScaleValue($instanceRisk->getRiskIntegrity()),
                'a' => $this->normalizeHistoryScaleValue($instanceRisk->getRiskAvailability()),
                'max' => $this->normalizeHistoryScaleValue($instanceRisk->getCacheMaxRisk()),
            ],
            'residualRisk' => $this->normalizeHistoryScaleValue($instanceRisk->getCacheTargetedRisk()),
            'kindOfMeasure' => $instanceRisk->getKindOfMeasure(),
            'reductionAmount' => $instanceRisk->getReductionAmount(),
            'residualAcceptanceApprover' => $instanceRisk->getResidualAcceptanceApproverSupervisor()?->getName(),
            'residualAcceptanceDecision' => $instanceRisk->getResidualRiskDecision(),
            'residualAcceptanceDate' => $instanceRisk->getResidualRiskDecidedAt()?->format('Y-m-d'),
            'residualAcceptanceJustification' => $instanceRisk->getResidualRiskJustification(),
        ];
    }

    private function recordHistoryChanges(Entity\Anr $anr, Entity\InstanceRisk $instanceRisk, array $before): void
    {
        $after = $this->captureHistoryState($instanceRisk);
        $entries = [];

        $fieldMap = [
            AnrHistory::RISK_OWNER => ['riskOwner', AnrHistory::FIELD_UPDATED],
            AnrHistory::RISK_SOURCE => ['riskSource', AnrHistory::FIELD_UPDATED],
            AnrHistory::RISK_CONTEXT => ['context', AnrHistory::FIELD_UPDATED],
            AnrHistory::LAST_REVIEW_DATE => ['lastReviewDate', AnrHistory::FIELD_UPDATED],
            AnrHistory::REVIEW_FREQUENCY => ['reviewFrequency', AnrHistory::FIELD_UPDATED],
            AnrHistory::THREAT_PROBABILITY => ['threatRate', AnrHistory::FIELD_UPDATED],
            AnrHistory::VULNERABILITY_QUALIFICATION => [
                'vulnerabilityRate',
                AnrHistory::FIELD_UPDATED,
            ],
            AnrHistory::CURRENT_RISK => ['currentRisk', AnrHistory::FIELD_UPDATED],
            AnrHistory::RESIDUAL_RISK => ['residualRisk', AnrHistory::FIELD_UPDATED],
            AnrHistory::TREATMENT_TYPE => ['kindOfMeasure', AnrHistory::FIELD_UPDATED],
            AnrHistory::VULNERABILITY_REDUCTION => [
                'reductionAmount',
                AnrHistory::FIELD_UPDATED,
            ],
            AnrHistory::RESIDUAL_ACCEPTANCE_APPROVER => [
                'residualAcceptanceApprover',
                AnrHistory::RESIDUAL_ACCEPTANCE_UPDATED,
            ],
            AnrHistory::RESIDUAL_ACCEPTANCE_DECISION => [
                'residualAcceptanceDecision',
                AnrHistory::RESIDUAL_ACCEPTANCE_UPDATED,
            ],
            AnrHistory::RESIDUAL_ACCEPTANCE_DATE => [
                'residualAcceptanceDate',
                AnrHistory::RESIDUAL_ACCEPTANCE_UPDATED,
            ],
            AnrHistory::RESIDUAL_ACCEPTANCE_JUSTIFICATION => [
                'residualAcceptanceJustification',
                AnrHistory::RESIDUAL_ACCEPTANCE_UPDATED,
            ],
        ];

        foreach ($fieldMap as $fieldCode => [$stateKey, $changeType]) {
            $oldValue = $before[$stateKey];
            $newValue = $after[$stateKey];
            if ($oldValue !== $newValue) {
                if ($fieldCode === AnrHistory::TREATMENT_TYPE) {
                    $typesCodes = CoreEntity\InstanceRiskSuperClass::getAvailableMeasureTypes();
                    $oldValue = $typesCodes[$oldValue] ?? $oldValue;
                    $newValue = $typesCodes[$newValue] ?? $newValue;
                }
                $entries[] = [
                    'targetType' => AnrHistory::INFORMATION_RISK,
                    'targetId' => $instanceRisk->getId(),
                    'changeType' => $changeType,
                    'fieldCode' => $fieldCode,
                    'oldValue' => $oldValue,
                    'newValue' => $newValue,
                ];
            }
        }

        $this->anrHistoryService->createEntries($anr, $entries);
    }

    private function getConsequenceFieldCode(int $scaleImpactType): string
    {
        return match ($scaleImpactType) {
            CoreEntity\ScaleImpactTypeSuperClass::SCALE_TYPE_C => AnrHistory::CONSEQUENCE_CONFIDENTIALITY,
            CoreEntity\ScaleImpactTypeSuperClass::SCALE_TYPE_I => AnrHistory::CONSEQUENCE_INTEGRITY,
            CoreEntity\ScaleImpactTypeSuperClass::SCALE_TYPE_D => AnrHistory::CONSEQUENCE_AVAILABILITY,
            CoreEntity\ScaleImpactTypeSuperClass::SCALE_TYPE_R => AnrHistory::CONSEQUENCE_REPUTATION,
            CoreEntity\ScaleImpactTypeSuperClass::SCALE_TYPE_L => AnrHistory::CONSEQUENCE_LEGAL,
            CoreEntity\ScaleImpactTypeSuperClass::SCALE_TYPE_F => AnrHistory::CONSEQUENCE_FINANCIAL,
            default => AnrHistory::CONSEQUENCE_AVAILABILITY,
        };
    }

    private function normalizeHistoryScaleValue(int $value): int|string
    {
        return $value === -1 ? '-' : $value;
    }

    private function duplicateRecommendationRisks(
        Entity\InstanceRisk $fromInstanceRisk,
        Entity\InstanceRisk $newInstanceRisk
    ): void {
        /** @var Entity\Anr $anr */
        $anr = $newInstanceRisk->getAnr();
        /** @var Entity\Instance $instance */
        $instance = $newInstanceRisk->getInstance();
        foreach ($fromInstanceRisk->getRecommendationRisks() as $recommendationRiskToDuplicate) {
            $newRecommendationRisk = (new Entity\RecommendationRisk())
                ->setAnr($anr)
                ->setCommentAfter($recommendationRiskToDuplicate->getCommentAfter())
                ->setRecommendation($recommendationRiskToDuplicate->getRecommendation())
                ->setInstance($instance)
                ->setInstanceRisk($newInstanceRisk)
                ->setGlobalObject($recommendationRiskToDuplicate->getGlobalObject())
                ->setAsset($recommendationRiskToDuplicate->getAsset())
                ->setThreat($recommendationRiskToDuplicate->getThreat())
                ->setVulnerability($recommendationRiskToDuplicate->getVulnerability());

            $this->recommendationRiskTable->save($newRecommendationRisk, false);
        }
    }
}
