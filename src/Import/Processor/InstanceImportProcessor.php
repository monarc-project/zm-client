<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Service\ObjectImportService;
use Monarc\FrontOffice\Service\AnrInstanceService;
use Monarc\FrontOffice\Table;

class InstanceImportProcessor
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\InstanceTable $instanceTable,
        private Table\InstanceMetadataTable $instanceMetadataTable,
        private Table\MonarcObjectTable $monarcObjectTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrInstanceService $instanceService,
        private ObjectImportProcessor $objectImportProcessor,
        private ObjectCategoryImportProcessor $objectCategoryImportProcessor,
        private InstanceConsequenceImportProcessor $instanceConsequenceImportProcessor,
        private AnrInstanceMetadataFieldImportProcessor $anrInstanceMetadataFieldImportProcessor,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /**
     * @return int[] IDs of the created root instances.
     */
    public function processInstancesData(
        Entity\Anr $anr,
        array $instancesData,
        ?Entity\Instance $parentInstance,
        string $importMode,
        bool $withEval
    ): array {
        $this->prepareGlobalInstancesCache($anr);
        $maxPositionInsideOfParentInstance = 0;
        if ($parentInstance !== null) {
            $maxPositionInsideOfParentInstance = $this->instanceTable->findMaxPosition(
                $parentInstance->getImplicitPositionRelationsValues()
            );
        }
        $instancesIds = [];
        foreach ($instancesData as $instanceData) {
            if ($maxPositionInsideOfParentInstance > 0) {
                $instanceData['position'] += ++$maxPositionInsideOfParentInstance;
            }
            $instancesIds[] = $this
                ->processInstanceData($anr, $instanceData, $parentInstance, $importMode, $withEval)
                ->getId();
        }

        return $instancesIds;
    }

    /** The method is called as a starting point of the root instances import and should not be called recursively. */
    public function processInstanceData(
        Entity\Anr $anr,
        array $instanceData,
        ?Entity\Instance $parentInstance,
        string $importMode,
        bool $withEval
    ): Entity\Instance {
        // TODO support the old format -> category is on the same level as object and object is inside of 'object'.
        $objectCategory = null;
        if (isset($instanceData['object']['category'])) {
            $objectCategory = $this->objectCategoryImportProcessor
                ->processObjectCategoryData($anr, $objectCategory, $importMode);
        }
        $instanceData['object'] = $this->objectImportProcessor
            ->processObjectData($anr, $objectCategory, $instanceData['object'], $importMode);
        $instanceData['parent'] = $parentInstance;
        $instanceData['setOnlyExactPosition'] = true;

        $instance = $this->instanceService->createInstance($anr, $instanceData, $parentInstance === null, false);
        /* In case if the import is into an instance the scales impacts could be adjusted ih not set directly. */
        if ($parentInstance !== null) {
            $instance->refreshInheritedImpact();
        }

        $this->instanceConsequenceImportProcessor->processInstanceConsequencesData(
            $instance,
            $instanceData['instancesConsequences'],
            $withEval
        );

        // TODO: process instanceRisks here or after the brothers update ???

        // TODO: 1. set impacts based on the importing data,
        // 2. If parent is set initially and it has impacts set (inherited or not) they have to be applied
        //      to the root instances and as the result to their parents. Only when !$withEval or impacts are not set.
        // 3. If !$withEval than try to get global object's impact to set for the importing instances, conseq, trheats, vulns.
        //    But only from the DB, not created during the import (fetch from the DB or if possible check if object is now or not).
        //    In general: if object, linked to the instance is not new (from DB), is global and has instances with evaluations we apply to the newly created.

        /* If import is without eval and the object is global, then sibling object's 1st instance eval is applied. */
        if ($importMode === ObjectImportService::IMPORT_MODE_MERGE && $instance->getObject()->isScopeGlobal()) {
            // TODO: consider a cache of the DB objects and created ones separately.
            if ($withEval) {
                // siblings only in the DB.
                // TODO: updateInstanceEvaluationsFromSiblings
            } else {
                // siblings only newly created.
                // TODO: applyInstanceEvaluationsToSiblings
            }
        }

        // TODO: ...
        $this->processInstanceMetadata(
            $anr,
            $instance,
            $instanceData['instancesMetadatas'] ?? $instanceData['instanceMetadata']
        );

        if (!empty($instanceData['children'])) {
            $this->processChildrenInstancesData($anr, $instanceData['children']);
        }

        return $instance;
    }

    public function processInstanceMetadata(
        Entity\Anr $anr,
        Entity\Instance $instance,
        array $instanceMetadataData
    ): void {
        foreach ($instanceMetadataData as $metadataData) {
            $metadataField = $this->anrInstanceMetadataFieldImportProcessor->processAnrInstanceMetadataField($anr, [
                'label' => $instanceMetadataData['anrInstanceMetadataField']['label'] ?? $instanceMetadataData['label'],
                'isDeletable' => true,
            ]);

            $instanceMetadata = $instance->getInstanceMetadataByMetadataFieldLink($metadataField);
            if ($instanceMetadata === null) {
                $instanceMetadata = (new Entity\InstanceMetadata())
                    ->setInstance($instance)
                    ->setAnrInstanceMetadataField($metadataField)
                    ->setComment($metadataData['comment'])
                    ->setCreator($this->connectedUser->getEmail());
                $this->instanceMetadataTable->save($instanceMetadata, false);

                $this->applyInstanceMetadataToSiblings($instance, $instanceMetadata);
            } elseif ($instanceMetadata->getComment() !== $metadataData['comment']) {
                $instanceMetadata->setComment($metadataData['comment'])->setUpdater($this->connectedUser->getEmail());
                $this->instanceMetadataTable->save($instanceMetadata, false);
            }
        }

        $this->updateInstanceMetadataFromSiblings($instance);
    }

    /**
     * Applies the newly created instance metadata to the others global sibling instances.
     */
    private function applyInstanceMetadataToSiblings(
        Entity\Instance $instance,
        Entity\InstanceMetadata $instanceMetadata
    ): void {
        if ($instance->getObject()->isScopeGlobal()) {
            foreach ($this->getGlobalObjectInstancesFromCache($instance->getObject()->getUuid()) as $instanceSibling) {
                $instanceMetadataOfSibling = $instanceSibling->getInstanceMetadataByMetadataFieldLink(
                    $instanceMetadata->getAnrInstanceMetadataField()
                );
                if ($instanceMetadataOfSibling === null) {
                    $instanceMetadataOfSibling = (new Entity\InstanceMetadata())
                        ->setInstance($instanceSibling)
                        ->setAnrInstanceMetadataField($instanceMetadata->getAnrInstanceMetadataField())
                        ->setComment($instanceMetadata->getComment())
                        ->setCreator($this->connectedUser->getEmail());
                    $this->instanceMetadataTable->save($instanceMetadataOfSibling, false);
                }
            }
        }
    }

    /**
     * Updates the instance metadata from the others global sibling instances.
     */
    private function updateInstanceMetadataFromSiblings(Entity\Instance $instance): void
    {
        if ($instance->getObject()->isScopeGlobal()) {
            $instanceSibling = current($this->getGlobalObjectInstancesFromCache($instance->getObject()->getUuid()));
            if ($instanceSibling !== false) {
                foreach ($instanceSibling->getInstanceMetadata() as $instanceMetadataOfSibling) {
                    $instanceMetadata = $instance->getInstanceMetadataByMetadataFieldLink(
                        $instanceMetadataOfSibling->getAnrInstanceMetadataField()
                    );
                    if ($instanceMetadata === null) {
                        $instanceMetadata = (new Entity\InstanceMetadata())
                            ->setInstance($instance)
                            ->setAnrInstanceMetadataField($instanceMetadataOfSibling->getAnrInstanceMetadataField())
                            ->setComment($instanceMetadataOfSibling->getComment())
                            ->setCreator($this->connectedUser->getEmail());
                        $this->instanceMetadataTable->save($instanceMetadata, false);
                    }
                }
            }
        }
    }

    private function processChildrenInstancesData(Entity\Anr $anr, array $childrenInstanceData): void
    {
        // TODO
    }

    /**
     * @return Entity\Instance[]
     */
    private function getGlobalObjectInstancesFromCache(string $objectUuid): array
    {
        return $this->importCacheHelper->getItemFromArrayCache('global_instances_by_object_uuids', $objectUuid) ?? [];
    }

    private function prepareGlobalInstancesCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('global_instances_by_object_uuids')) {
            foreach ($this->monarcObjectTable->findGlobalObjectsByAnr($anr) as $object) {
                if ($object->hasInstances()) {
                    $instances = [];
                    foreach ($object->getInstances() as $instance) {
                        $instances[] = $instance;
                    }
                    $this->importCacheHelper->addItemToArrayCache(
                        'global_instances_by_object_uuids',
                        $instances,
                        $object->getUuid()
                    );
                }
            }
        }
    }
}
