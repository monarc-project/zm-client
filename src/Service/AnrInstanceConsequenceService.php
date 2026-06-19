<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity as CoreEntity;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Helper\ScalesCacheHelper;
use Monarc\Core\Service\Traits\ImpactVerificationTrait;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Entity\AnrHistory;
use Monarc\FrontOffice\Table;

class AnrInstanceConsequenceService
{
    use ImpactVerificationTrait;

    private CoreEntity\UserSuperClass $connectedUser;

    public function __construct(
        private Table\InstanceConsequenceTable $instanceConsequenceTable,
        private Table\InstanceTable $instanceTable,
        private AnrInstanceService $anrInstanceService,
        private ScalesCacheHelper $scalesCacheHelper,
        private AnrHistoryService $anrHistoryService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getConsequencesData(Entity\Instance $instance, bool $includeScaleComments = false): array
    {
        $result = [];
        /** @var Entity\Anr $anr */
        $anr = $instance->getAnr();
        foreach ($instance->getInstanceConsequences() as $instanceConsequence) {
            $scaleImpactType = $instanceConsequence->getScaleImpactType();
            if (!$scaleImpactType->isHidden()) {
                $consequenceData = [
                    'id' => $instanceConsequence->getId(),
                    'scaleImpactTypeId' => $scaleImpactType->getId(),
                    'scaleImpactType' => $scaleImpactType->getType(),
                    'scaleImpactTypeDescription1' => $scaleImpactType->getLabel(1),
                    'scaleImpactTypeDescription2' => $scaleImpactType->getLabel(2),
                    'scaleImpactTypeDescription3' => $scaleImpactType->getLabel(3),
                    'scaleImpactTypeDescription4' => $scaleImpactType->getLabel(4),
                    'c_risk' => $instanceConsequence->getConfidentiality(),
                    'i_risk' => $instanceConsequence->getIntegrity(),
                    'd_risk' => $instanceConsequence->getAvailability(),
                    'isHidden' => $instanceConsequence->isHidden(),
                ];
                if ($includeScaleComments) {
                    $consequenceData['comments'] = [];
                    foreach ($scaleImpactType->getScaleComments() as $scaleComment) {
                        $consequenceData['comments'][$scaleComment->getScaleValue()] = $scaleComment
                            ->getComment($anr->getLanguage());
                    }
                }

                $result[] = $consequenceData;
            }
        }

        return $result;
    }

    /**
     * Creates the instance consequences based on a sibling instance's consequences or available scale impact types.
     */
    public function createInstanceConsequences(
        Entity\Instance $instance,
        Entity\Anr $anr,
        Entity\MonarcObject $object,
        bool $saveInDb = true
    ): void {
        $siblingInstance = null;
        if ($object->isScopeGlobal()) {
            $siblingInstance = $this->instanceTable->findOneByAnrAndObjectExcludeInstance($anr, $object, $instance);
        }

        if ($siblingInstance !== null) {
            foreach ($siblingInstance->getInstanceConsequences() as $instanceConsequence) {
                /** @var Entity\ScaleImpactType $scalesImpactType */
                $scalesImpactType = $instanceConsequence->getScaleImpactType();
                $this->createInstanceConsequence(
                    $instance,
                    $scalesImpactType,
                    $instanceConsequence->isHidden(),
                    [
                        'confidentiality' => $instanceConsequence->getConfidentiality(),
                        'integrity' => $instanceConsequence->getIntegrity(),
                        'availability' => $instanceConsequence->getAvailability(),
                    ]
                );
            }
        } else {
            /** @var Entity\ScaleImpactType $scalesImpactType */
            foreach ($this->scalesCacheHelper->getCachedScaleImpactTypes($anr) as $scalesImpactType) {
                if (!\in_array(
                    $scalesImpactType->getType(),
                    CoreEntity\ScaleImpactTypeSuperClass::getScaleImpactTypesCid(),
                    true
                )) {
                    $this->createInstanceConsequence($instance, $scalesImpactType, $scalesImpactType->isHidden());
                }
            }
        }

        if ($saveInDb) {
            $this->instanceConsequenceTable->flush();
        }
    }

    public function createInstanceConsequence(
        Entity\Instance $instance,
        Entity\ScaleImpactType $scaleImpactType,
        bool $isHidden = false,
        array $evaluationCriteria = [],
        bool $saveInTheDb = false
    ): Entity\InstanceConsequence {
        $instanceConsequence = (new Entity\InstanceConsequence())
            ->setAnr($instance->getAnr())
            ->setInstance($instance)
            ->setScaleImpactType($scaleImpactType)
            ->setIsHidden($isHidden)
            ->setCreator($this->connectedUser->getEmail());
        if (!$isHidden && isset($evaluationCriteria['confidentiality'])) {
            $instanceConsequence->setConfidentiality($evaluationCriteria['confidentiality']);
        }
        if (!$isHidden && isset($evaluationCriteria['integrity'])) {
            $instanceConsequence->setIntegrity($evaluationCriteria['integrity']);
        }
        if (!$isHidden && isset($evaluationCriteria['availability'])) {
            $instanceConsequence->setAvailability($evaluationCriteria['availability']);
        }

        $this->instanceConsequenceTable->save($instanceConsequence, $saveInTheDb);

        return $instanceConsequence;
    }

    /**
     * This method is called from controllers to hide / show a specific consequence only linked to a specific instance.
     * The other place is AnrInstanceService, to update an instance impacts.
     */
    public function patchConsequence(Entity\Anr $anr, int $id, array $data): Entity\InstanceConsequence
    {
        /** @var Entity\InstanceConsequence $instanceConsequence */
        $instanceConsequence = $this->instanceConsequenceTable->findByIdAndAnr($id, $anr);
        $beforeState = $this->prepareConsequenceHistoryState($instanceConsequence);
        $beforeValue = $this->prepareConsequenceHistoryValue($instanceConsequence);
        $affectedInstances = [$instanceConsequence->getInstance()->getId() => $instanceConsequence->getInstance()];
        if ($instanceConsequence->getInstance()->getObject()->isScopeGlobal()) {
            foreach ($this->instanceTable->findByAnrAndObject($anr, $instanceConsequence->getInstance()->getObject()) as $instance) {
                $affectedInstances[$instance->getId()] = $instance;
            }
        }

        $this->verifyImpacts(
            $this->scalesCacheHelper->getCachedScaleByType($anr, CoreEntity\ScaleSuperClass::TYPE_IMPACT),
            $data
        );

        $updateInstance = $instanceConsequence->isHidden() !== (bool)$data['isHidden'];

        $instanceConsequence
            ->setIsHidden((bool)$data['isHidden'])
            ->setUpdater($this->connectedUser->getEmail());
        if (isset($data['confidentiality'])) {
            $updateInstance = $updateInstance
                || $instanceConsequence->getConfidentiality() !== $data['confidentiality'];
            $instanceConsequence->setConfidentiality($data['confidentiality']);
        }
        if (isset($data['integrity'])) {
            $updateInstance = $updateInstance || $instanceConsequence->getIntegrity() !== $data['integrity'];
            $instanceConsequence->setIntegrity($data['integrity']);
        }
        if (isset($data['availability'])) {
            $updateInstance = $updateInstance || $instanceConsequence->getAvailability() !== $data['availability'];
            $instanceConsequence->setAvailability($data['availability']);
        }

        if ($updateInstance) {
            /** @var Entity\Instance $instance */
            $instance = $instanceConsequence->getInstance();
            $this->anrInstanceService->refreshInstanceImpactAndUpdateRisks($instance);
        }

        $this->updateSiblingsConsequences($instanceConsequence, $updateInstance);

        $this->instanceConsequenceTable->save($instanceConsequence);
        $this->recordConsequenceHistory(
            $anr,
            $affectedInstances,
            $instanceConsequence,
            $beforeState,
            $this->prepareConsequenceHistoryState($instanceConsequence),
            $beforeValue,
            $this->prepareConsequenceHistoryValue($instanceConsequence)
        );

        return $instanceConsequence;
    }

    /** Updated the consequences visibility based on the scales impact types visibility update. */
    public function updateConsequencesByScaleImpactType(Entity\ScaleImpactType $scaleImpactType, bool $hide): void
    {
        $instancesConsequences = $this->instanceConsequenceTable->findByScaleImpactType($scaleImpactType);
        foreach ($instancesConsequences as $instanceConsequence) {
            $instanceConsequence->setIsHidden($hide)->setUpdater($this->connectedUser->getEmail());
            $this->instanceConsequenceTable->save($instanceConsequence, false);
        }
        $this->instanceConsequenceTable->flush();
    }

    /**
     * Updates the consequences of the instances at the same level.
     */
    private function updateSiblingsConsequences(
        Entity\InstanceConsequence $instanceConsequence,
        bool $updateInstance
    ): void {
        $object = $instanceConsequence->getInstance()->getObject();
        if ($object->isScopeGlobal()) {
            $anr = $instanceConsequence->getInstance()->getAnr();
            $siblingInstances = $this->instanceTable->findByAnrAndObject($anr, $object);

            foreach ($siblingInstances as $siblingInstance) {
                $siblingInstanceConsequences = $this->instanceConsequenceTable->findByAnrInstanceAndScaleImpactType(
                    $anr,
                    $siblingInstance,
                    $instanceConsequence->getScaleImpactType()
                );

                foreach ($siblingInstanceConsequences as $siblingInstanceConsequence) {
                    $siblingInstanceConsequence
                        ->setIsHidden($instanceConsequence->isHidden())
                        ->setConfidentiality($instanceConsequence->getConfidentiality())
                        ->setIntegrity($instanceConsequence->getIntegrity())
                        ->setAvailability($instanceConsequence->getAvailability());

                    $this->instanceConsequenceTable->save($siblingInstanceConsequence, false);
                }

                if ($updateInstance) {
                    $this->anrInstanceService->refreshInstanceImpactAndUpdateRisks($siblingInstance);
                }
            }
        }
    }

    /**
     * @param array<int, Entity\Instance> $affectedInstances
     */
    private function recordConsequenceHistory(
        Entity\Anr $anr,
        array $affectedInstances,
        Entity\InstanceConsequence $instanceConsequence,
        array $beforeState,
        array $afterState,
        array $beforeValue,
        array $afterValue
    ): void {
        if ($beforeState === $afterState) {
            return;
        }

        $entries = [];
        foreach ($affectedInstances as $instance) {
            foreach ($instance->getInstanceRisks() as $instanceRisk) {
                if ($beforeValue !== $afterValue) {
                    $entries[] = [
                        'targetType' => AnrHistory::INFORMATION_RISK,
                        'targetId' => $instanceRisk->getId(),
                        'changeType' => AnrHistory::CONSEQUENCE_UPDATED,
                        'fieldCode' => $this->getConsequenceFieldCode($instanceConsequence->getScaleImpactType()->getType()),
                        'oldValue' => $beforeValue,
                        'newValue' => $afterValue,
                    ];
                }

                if ($beforeState['hidden'] !== $afterState['hidden']) {
                    $entries[] = [
                        'targetType' => AnrHistory::INFORMATION_RISK,
                        'targetId' => $instanceRisk->getId(),
                        'changeType' => AnrHistory::IMPACT_SCALE_UPDATED,
                        'fieldCode' => AnrHistory::IMPACT_SCALE_UPDATE,
                        'oldValue' => null,
                        'newValue' => $afterState['hidden'] ? 'hidden' : 'visible',
                    ];
                }
            }
        }

        $this->anrHistoryService->createEntries($anr, $entries);
    }

    private function prepareConsequenceHistoryState(Entity\InstanceConsequence $instanceConsequence): array
    {
        return [
            'c' => $instanceConsequence->getConfidentiality(),
            'i' => $instanceConsequence->getIntegrity(),
            'a' => $instanceConsequence->getAvailability(),
            'hidden' => $instanceConsequence->isHidden(),
        ];
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

    private function prepareConsequenceHistoryValue(Entity\InstanceConsequence $instanceConsequence): array
    {
        return [
            'c' => $this->normalizeConsequenceHistoryValue($instanceConsequence->getConfidentiality()),
            'i' => $this->normalizeConsequenceHistoryValue($instanceConsequence->getIntegrity()),
            'a' => $this->normalizeConsequenceHistoryValue($instanceConsequence->getAvailability()),
        ];
    }

    private function normalizeConsequenceHistoryValue(int $value): int|string
    {
        return $value === -1 ? '-' : $value;
    }
}
