<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\AnrInstanceMetadataField;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceMetadata;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Table;

class InstanceMetadataService
{
    protected Table\InstanceMetadataTable $instanceMetadataTable;

    protected Table\InstanceTable $instanceTable;

    protected Table\AnrInstanceMetadataFieldTable $anrInstanceMetadataFieldTable;

    protected UserSuperClass $connectedUser;

    public function __construct(
        Table\InstanceMetadataTable $instanceMetadataTable,
        Table\InstanceTable $instanceTable,
        Table\AnrInstanceMetadataFieldTable $anrInstanceMetadataFieldTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->instanceMetadataTable = $instanceMetadataTable;
        $this->instanceTable = $instanceTable;
        $this->anrInstanceMetadataFieldTable = $anrInstanceMetadataFieldTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getInstancesMetadata(Anr $anr, int $instanceId): array
    {
        $result = [];
        /** @var Instance $instance */
        $instance = $this->instanceTable->findByIdAndAnr($instanceId, $anr);

        foreach ($instance->getInstanceMetadata() as $instanceMetadata) {
            $metadataField = $instanceMetadata->getAnrInstanceMetadataField();
            $metadataFieldId = $metadataField->getId();
            $result[$metadataFieldId] = [
                'id' => $metadataFieldId,
                $anr->getLanguage() => $metadataField->getLabel(),
                'isDeletable' => $metadataField->isDeletable(),
                'instanceMetadata' => [
                    'id' => $instanceMetadata->getId(),
                    'metadataId' => $metadataFieldId,
                    $anr->getLanguage() => $instanceMetadata->getComment(),
                ],
            ];
        }

        return $result;
    }

    public function create(Anr $anr, int $instanceId, array $data): InstanceMetadata
    {
        $instance = $this->instanceTable->findById($instanceId);
        $metadataFieldData = current($data['metadata']);
        $instanceMetadataComment = $metadataFieldData['instanceMetadata'][$anr->getLanguageCode()] ?? '';
        /** @var AnrInstanceMetadataField $metadataField */
        $metadataField = $this->anrInstanceMetadataFieldTable->findByIdAndAnr((int)$metadataFieldData['id'], $anr);
        $instanceMetadata = (new InstanceMetadata())
            ->setInstance($instance)
            ->setAnrInstanceMetadataField($metadataField)
            ->setComment($instanceMetadataComment)
            ->setCreator($this->connectedUser->getEmail());

        /* Create the same context instance metadata records for the global instance's siblings. */
        $siblingInstances = $this->instanceTable->findGlobalSiblingsByAnrAndInstance($anr, $instance);
        foreach ($siblingInstances as $siblingInstance) {
            $siblingInstanceMetadata = (new InstanceMetadata())
                ->setInstance($siblingInstance)
                ->setAnrInstanceMetadataField($metadataField)
                ->setComment($instanceMetadataComment)
                ->setCreator($this->connectedUser->getEmail());

            $this->instanceMetadataTable->save($siblingInstanceMetadata, false);
        }

        $this->instanceMetadataTable->save($instanceMetadata);

        return $instanceMetadata;
    }

    public function update(Anr $anr, int $id, array $data): InstanceMetadata
    {
        /** @var InstanceMetadata $instanceMetadata */
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
