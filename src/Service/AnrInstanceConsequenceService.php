<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity as CoreEntity;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Helper\ScalesCacheHelper;
use Monarc\Core\Service\Traits\ImpactVerificationTrait;
use Monarc\FrontOffice\Model\Entity;
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
            $instancesConsequences = $this->instanceConsequenceTable->findByAnrAndInstance($anr, $siblingInstance);
            foreach ($instancesConsequences as $instanceConsequence) {
                $this->createInstanceConsequence(
                    $instance,
                    $instanceConsequence->getScaleImpactType(),
                    $instanceConsequence->isHidden(),
                    [
                        'confidentiality' => $instanceConsequence->getConfidentiality(),
                        'integrity' => $instanceConsequence->getIntegrity(),
                        'availability' => $instanceConsequence->getAvailability(),
                    ]
                );
            }
        } else {
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
        /** @var Entity\InstanceConsequence $instanceConsequence */
        $instanceConsequence = (new Entity\InstanceConsequence())
            ->setAnr($instance->getAnr())
            ->setInstance($instance)
            ->setScaleImpactType($scaleImpactType)
            ->setIsHidden($isHidden)
            ->setCreator($this->connectedUser->getEmail());
        if (isset($evaluationCriteria['confidentiality'])) {
            $instanceConsequence->setConfidentiality($evaluationCriteria['confidentiality']);
        }
        if (isset($evaluationCriteria['integrity'])) {
            $instanceConsequence->setIntegrity($evaluationCriteria['integrity']);
        }
        if (isset($evaluationCriteria['availability'])) {
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
}
