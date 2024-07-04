<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\FrontOffice\Entity;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Table;

class InstanceMetadataService
{
    protected UserSuperClass $connectedUser;

    public function __construct(
        private Table\InstanceMetadataTable $instanceMetadataTable,
        private Table\InstanceTable $instanceTable,
        private Table\AnrInstanceMetadataFieldTable $anrInstanceMetadataFieldTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getInstancesMetadata(Entity\Anr $anr, int $instanceId): array
    {
        $result = [];
        /** @var Entity\Instance $instance */
        $instance = $this->instanceTable->findByIdAndAnr($instanceId, $anr);

        $instanceMetadataByMetadataFieldId = [];
        foreach ($instance->getInstanceMetadata() as $instanceMetadata) {
            $metadataFieldId = $instanceMetadata->getAnrInstanceMetadataField()->getId();
            $instanceMetadataByMetadataFieldId[$metadataFieldId] = [
                'id' => $instanceMetadata->getId(),
                'metadataId' => $metadataFieldId,
                $anr->getLanguageCode() => $instanceMetadata->getComment(),
            ];
        }

        foreach ($anr->getAnrInstanceMetadataFields() as $metadataField) {
            $metadataFieldId = $metadataField->getId();
            $result[$metadataFieldId] = [
                'id' => $metadataFieldId,
                $anr->getLanguageCode() => $metadataField->getLabel(),
                'isDeletable' => $metadataField->isDeletable(),
                'instanceMetadata' => $instanceMetadataByMetadataFieldId[$metadataFieldId] ?? [],
            ];
        }

        return $result;
    }

    public function create(Entity\Anr $anr, int $instanceId, array $data): Entity\InstanceMetadata
    {
        /** @var Entity\Instance $instance */
        $instance = $this->instanceTable->findById($instanceId);
        $metadataFieldData = current($data['metadata']);
        $instanceMetadataComment = $metadataFieldData['instanceMetadata'][$anr->getLanguageCode()] ?? '';
        /** @var Entity\AnrInstanceMetadataField $metadataField */
        $metadataField = $this->anrInstanceMetadataFieldTable->findByIdAndAnr((int)$metadataFieldData['id'], $anr);
        $instanceMetadata = (new Entity\InstanceMetadata())
            ->setInstance($instance)
            ->setAnrInstanceMetadataField($metadataField)
            ->setComment($instanceMetadataComment)
            ->setCreator($this->connectedUser->getEmail());

        /* Create the same context instance metadata records for the global instance's siblings. */
        $siblingInstances = $this->instanceTable->findGlobalSiblingsByAnrAndInstance($anr, $instance);
        foreach ($siblingInstances as $siblingInstance) {
            $siblingInstanceMetadata = (new Entity\InstanceMetadata())
                ->setInstance($siblingInstance)
                ->setAnrInstanceMetadataField($metadataField)
                ->setComment($instanceMetadataComment)
                ->setCreator($this->connectedUser->getEmail());

            $this->instanceMetadataTable->save($siblingInstanceMetadata, false);
        }

        $this->instanceMetadataTable->save($instanceMetadata);

        return $instanceMetadata;
    }

    public function update(Entity\Anr $anr, int $id, array $data): Entity\InstanceMetadata
    {
        /** @var Entity\InstanceMetadata $instanceMetadata */
        $instanceMetadata = $this->instanceMetadataTable->findByIdAndAnr($id, $anr);

        $commentValue = $data[$anr->getLanguageCode()] ?? '';
        $instanceMetadata->setComment($commentValue)
            ->setUpdater($this->connectedUser->getEmail());

        /* Update the context instance metadata comment for the global instance's siblings. */
        $siblingInstances = $this->instanceTable->findGlobalSiblingsByAnrAndInstance(
            $anr,
            $instanceMetadata->getInstance()
        );
        foreach ($siblingInstances as $siblingInstance) {
            $siblingInstanceMetadata = $this->instanceMetadataTable->findByInstanceAndMetadataField(
                $siblingInstance,
                $instanceMetadata->getAnrInstanceMetadataField()
            );
            if ($siblingInstanceMetadata !== null) {
                $siblingInstanceMetadata->setComment($commentValue)
                    ->setUpdater($this->connectedUser->getEmail());

                $this->instanceMetadataTable->save($siblingInstanceMetadata, false);
            }
        }

        $this->instanceMetadataTable->save($instanceMetadata);

        return $instanceMetadata;
    }
}
