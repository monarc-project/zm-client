<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\FrontOffice\Entity;

trait InstanceExportTrait
{
    use ObjectExportTrait;
    use AssetExportTrait;
    use InformationInstanceRiskExportTrait;
    use OperationalInstanceRiskExportTrait;
    use InstanceConsequenceExportTrait;

    private function prepareInstanceData(
        Entity\Instance $instance,
        int $languageIndex,
        bool $includeCompleteObjectData,
        bool $includeCompleteInformationRisksData,
        bool $withEval,
        bool $withControls,
        bool $withRecommendations
    ): array {
        /** @var Entity\MonarcObject $object */
        $object = $instance->getObject();
        /** @var Entity\Asset $asset */
        $asset = $instance->getAsset();

        return [
            'name' => $instance->getName($languageIndex),
            'label' => $instance->getLabel($languageIndex),
            'level' => $instance->getLevel(),
            'position' => $instance->getPosition(),
            'confidentiality' => $withEval ? $instance->getConfidentiality() : -1,
            'integrity' => $withEval ? $instance->getIntegrity() : -1,
            'availability' => $withEval ? $instance->getAvailability() : -1,
            'isConfidentialityInherited' => $withEval ? (int)$instance->isConfidentialityInherited() : 1,
            'isIntegrityInherited' => $withEval ? (int)$instance->isIntegrityInherited() : 1,
            'isAvailabilityInherited' => $withEval ? (int)$instance->isAvailabilityInherited() : 1,
            'asset' => $this->prepareAssetData($asset, $languageIndex),
            /* For Anr and Instance export instanceRisks are added to the instance, so not needed in AMVs in asset. */
            'object' => $includeCompleteObjectData
                ? $this->prepareObjectData($object, $languageIndex, false)
                : ['uuid' => $instance->getObject()->getUuid()],
            'instanceMetadata' => $withEval ? $this->prepareInstanceMetadataData($instance) : [],
            'instanceRisks' => $this->prepareInformationInstanceRisksData(
                $instance,
                $languageIndex,
                $includeCompleteInformationRisksData,
                $withEval,
                $withControls,
                $withRecommendations
            ),
            'operationalInstanceRisks' => $this->prepareOperationalInstanceRisksData(
                $instance,
                $languageIndex,
                $withEval,
                $withControls,
                $withRecommendations
            ),
            'instancesConsequences' => $this->prepareInstanceConsequencesData($instance, $languageIndex),
            'children' => $this->prepareChildrenInstancesData(
                $instance,
                $languageIndex,
                $includeCompleteObjectData,
                $includeCompleteInformationRisksData,
                $withEval,
                $withControls,
                $withRecommendations
            ),
        ];
    }

    private function prepareInstanceMetadataData(Entity\Instance $instance): array
    {
        $result = [];
        foreach ($instance->getInstanceMetadata() as $instanceMetadata) {
            $result[] = [
                'anrInstanceMetadataField' => [
                    'label' => $instanceMetadata->getAnrInstanceMetadataField()->getLbel(),
                ],
                'comment' => $instanceMetadata->getComment(),
            ];
        }

        return $result;
    }

    private function prepareChildrenInstancesData(
        Entity\Instance $instance,
        int $languageIndex,
        bool $includeCompleteObjectData,
        bool $includeCompleteInformationRisksData,
        bool $withEval,
        bool $withControls,
        bool $withRecommendations
    ): array {
        $result = [];
        /** @var Entity\Instance $childInstance */
        foreach ($instance->getChildren() as $childInstance) {
            $result[] = $this->prepareInstanceData(
                $childInstance,
                $languageIndex,
                $includeCompleteObjectData,
                $includeCompleteInformationRisksData,
                $withEval,
                $withControls,
                $withRecommendations
            );
        }

        return $result;
    }

    private function prepareInformationInstanceRisksData(
        Entity\Instance $instance,
        int $languageIndex,
        bool $includeCompleteInformationRisksData,
        bool $withEval,
        bool $withControls,
        bool $withRecommendations
    ): array {
        $result = [];
        /** @var Entity\InstanceRisk $operationalInstanceRisk */
        foreach ($instance->getInstanceRisks() as $instanceRisk) {
            $result[] = $this->prepareInformationInstanceRiskData(
                $instanceRisk,
                $languageIndex,
                $includeCompleteInformationRisksData,
                $withEval,
                $withControls,
                $withRecommendations
            );
        }

        return $result;
    }

    private function prepareOperationalInstanceRisksData(
        Entity\Instance $instance,
        int $languageIndex,
        bool $withEval,
        bool $withControls,
        bool $withRecommendations
    ): array {
        $result = [];
        /** @var Entity\InstanceRiskOp $operationalInstanceRisk */
        foreach ($instance->getOperationalInstanceRisks() as $operationalInstanceRisk) {
            $result[] = $this->prepareOperationalInstanceRiskData(
                $operationalInstanceRisk,
                $languageIndex,
                $withEval,
                $withControls,
                $withRecommendations
            );
        }

        return $result;
    }

    private function prepareInstanceConsequencesData(Entity\Instance $instance, int $languageIndex): array
    {
        $result = [];
        /** @var Entity\InstanceConsequence $instanceConsequence */
        foreach ($instance->getInstanceConsequences() as $instanceConsequence) {
            $result[] = $this->prepareInstanceConsequenceData(
                $instanceConsequence,
                $languageIndex
            );
        }

        return $result;
    }
}
