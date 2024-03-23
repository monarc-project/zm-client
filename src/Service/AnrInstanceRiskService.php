<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity as CoreEntity;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Traits\ImpactVerificationTrait;
use Monarc\Core\Service\Traits\RiskCalculationTrait;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskService
{
    use RiskCalculationTrait;
    use ImpactVerificationTrait;
    use RecommendationsPositionsUpdateTrait;

    private CoreEntity\UserSuperClass $connectedUser;

    private array $cacheData = [];

    public function __construct(
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\RecommendationTable $recommendationTable,
        private Table\RecommendationRiskTable $recommendationRiskTable,
        private Table\InstanceTable $instanceTable,
        private Table\AmvTable $amvTable,
        private Table\ThreatTable $threatTable,
        private Table\VulnerabilityTable $vulnerabilityTable,
        private Table\ScaleTable $scaleTable,
        private TranslateService $translateService,
        private InstanceRiskOwnerService $instanceRiskOwnerService,
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
                    't' => $instanceRisk->isTreated(),
                    'tid' => $threat->getUuid(),
                    'vid' => $vulnerability->getUuid(),
                    'context' => $instanceRisk->getContext(),
                    'owner' => $instanceRisk->getInstanceRiskOwner()
                        ? $instanceRisk->getInstanceRiskOwner()->getName()
                        : '',
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
        bool $saveInDb = false
    ): Entity\InstanceRisk {
        $instanceRisk = $fromInstanceRisk !== null
            ? Entity\InstanceRisk::constructFromObjectOfTheSameAnr($fromInstanceRisk)
            : new Entity\InstanceRisk();

        /** @var Entity\InstanceRisk $instanceRisk */
        $instanceRisk
            ->setInstance($instance)
            ->setCreator($this->connectedUser->getEmail());
        if ($fromInstanceRisk === null && $amv !== null) {
            $instanceRisk
                ->setAnr($instance->getAnr())
                ->setAmv($amv)
                ->setAsset($amv->getAsset())
                ->setThreat($amv->getThreat())
                ->setVulnerability($amv->getVulnerability());
        }

        $this->recalculateRiskRatesAndUpdateRecommendationsPositions($instanceRisk);

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
                $newInstanceRisk = $this
                    ->createInstanceRisk($instance, $siblingInstanceRisk->getAmv(), $siblingInstanceRisk);

                $this->duplicateRecommendationRisks($siblingInstanceRisk, $newInstanceRisk);
            }
        } else {
            foreach ($object->getAsset()->getAmvs() as $amv) {
                $instanceRisk = $this->createInstanceRisk($instance, $amv);

                /* Process risk owner and context in case of import. */
                if (!empty($params['risks'])) {
                    $riskKey = array_search($amv->getUuid(), array_column($params['risks'], 'amv'), true);
                    if ($riskKey !== false) {
                        $instanceRiskData = array_values($params['risks'])[$riskKey];
                        $instanceRisk->setContext($instanceRiskData['context'] ?? '');
                        if (!empty($instanceRiskData['riskOwner'])) {
                            /** @var Entity\Anr $anr */
                            $anr = $instance->getAnr();
                            $instanceRiskOwner = $this->instanceRiskOwnerService->getOrCreateInstanceRiskOwner(
                                $anr,
                                $anr,
                                $instanceRiskData['riskOwner']
                            );
                            $instanceRisk->setInstanceRiskOwner($instanceRiskOwner);
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
                        $this->updateInstanceRiskData($siblingInstanceRisk, $data);
                    }
                }
            }
        }

        $this->instanceRiskTable->save($instanceRisk);

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

    public function getInstanceRisksInCsv(Entity\Anr $anr, $instanceId = null, $params = []): string
    {
        $languageIndex = $anr->getLanguage();

        // Fill in the header
        $output = implode(';', [
            $this->translateService->translate('Asset', $languageIndex),
            $this->translateService->translate('C Impact', $languageIndex),
            $this->translateService->translate('I Impact', $languageIndex),
            $this->translateService->translate('A Impact', $languageIndex),
            $this->translateService->translate('Threat', $languageIndex),
            $this->translateService->translate('Prob.', $languageIndex),
            $this->translateService->translate('Vulnerability', $languageIndex),
            $this->translateService->translate('Existing controls', $languageIndex),
            $this->translateService->translate('Qualif.', $languageIndex),
            $this->translateService->translate('Current risk', $languageIndex). " C",
            $this->translateService->translate('Current risk', $languageIndex) . " I",
            $this->translateService->translate('Current risk', $languageIndex) . " "
                . $this->translateService->translate('A', $languageIndex),
            $this->translateService->translate('Treatment', $languageIndex),
            $this->translateService->translate('Residual risk', $languageIndex),
            $this->translateService->translate('Risk owner', $languageIndex),
            $this->translateService->translate('Risk context', $languageIndex),
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

        foreach ($instanceRisks as $instanceRisk) {
            $instance = $instanceRisk->getInstance();
            $recommendationData = [];
            foreach ($instanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $recommendationData[] = $recommendationRisk->getRecommendation()->getCode() . " - "
                    . $recommendationRisk->getRecommendation()->getDescription();
            }
            $measuresData = [];
            if ($instanceRisk->getAmv() !== null) {
                foreach ($instanceRisk->getAmv()->getMeasures() as $measure) {
                    $measuresData[] = "[" . $measure->getReferential()->getLabel($anr->getLanguage()) . "] "
                        . $measure->getCode() . " - " . $measure->getLabel($anr->getLanguage());
                }
            }

            $values = [
                $instance->getName($languageIndex),
                $instance->getConfidentiality() === -1 ? null : $instance->getConfidentiality(),
                $instance->getIntegrity() === -1 ? null : $instance->getIntegrity(),
                $instance->getAvailability() === -1 ? null : $instance->getAvailability(),
                $instanceRisk->getThreat()->getLabel($languageIndex),
                $instanceRisk->getThreatRate() === -1 ? null : $instanceRisk->getThreatRate(),
                $instanceRisk->getVulnerability()->getLabel($languageIndex),
                $instanceRisk->getComment(),
                $instanceRisk->getVulnerabilityRate() === -1 ? null : $instanceRisk->getVulnerabilityRate(),
                $instanceRisk->getThreat()->getConfidentiality() === 0 || $instanceRisk->getRiskConfidentiality() === -1
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
                $instanceRisk->getInstanceRiskOwner() ? $instanceRisk->getInstanceRiskOwner()->getName() : '',
                $instanceRisk->getContext(),
                implode("\n", $recommendationData),
                implode("\n", $measuresData),
            ];

            $output .= '"';
            $search = ['"'];
            $replace = ["'"];
            $output .= implode('";"', str_replace($search, $replace, $values));
            $output .= "\"\r\n";
        }

        return $output;
    }

    private function updateInstanceRiskData(Entity\InstanceRisk $instanceRisk, array $data): void
    {
        if (isset($data['owner'])) {
            $this->instanceRiskOwnerService->processRiskOwnerNameAndAssign((string)$data['owner'], $instanceRisk);
        }
        if (isset($data['context'])) {
            $instanceRisk->setContext($data['context']);
        }
        if (isset($data['reductionAmount'])) {
            $instanceRisk->setReductionAmount((int)$data['reductionAmount']);
        }
        if (isset($data['threatRate'])) {
            $instanceRisk->setThreatRate((int)$data['threatRate']);
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

    private function duplicateRecommendationRisks(
        Entity\InstanceRisk $fromInstanceRisk,
        Entity\InstanceRisk $newInstanceRisk
    ): void {
        foreach ($fromInstanceRisk->getRecommendationRisks() as $recommendationRiskToDuplicate) {
            $newRecommendationRisk = (new Entity\RecommendationRisk())
                ->setAnr($newInstanceRisk->getAnr())
                ->setCommentAfter($recommendationRiskToDuplicate->getCommentAfter())
                ->setRecommendation($recommendationRiskToDuplicate->getRecommendation())
                ->setInstance($newInstanceRisk->getInstance())
                ->setGlobalObject($recommendationRiskToDuplicate->getGlobalObject())
                ->setAsset($recommendationRiskToDuplicate->getAsset())
                ->setThreat($recommendationRiskToDuplicate->getThreat())
                ->setVulnerability($recommendationRiskToDuplicate->getVulnerability());
            if ($recommendationRiskToDuplicate->getInstanceRisk()) {
                $newRecommendationRisk->setInstanceRisk($recommendationRiskToDuplicate->getInstanceRisk());
            } elseif ($recommendationRiskToDuplicate->getInstanceRiskOp()) {
                $newRecommendationRisk->setInstanceRiskOp($recommendationRiskToDuplicate->getInstanceRiskOp());
            }

            $this->recommendationRiskTable->save($newRecommendationRisk, false);
        }
    }
}
