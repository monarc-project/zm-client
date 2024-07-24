<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\ScaleSuperClass;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Import\Traits\EvaluationConverterTrait;
use Monarc\FrontOffice\Service\AnrInstanceService;
use Monarc\FrontOffice\Table;

class InstanceImportProcessor
{
    use EvaluationConverterTrait;

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
        private OperationalInstanceRiskImportProcessor $operationalInstanceRiskImportProcessor,
        private InstanceRiskImportProcessor $instanceRiskImportProcessor,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /**
     * @return Entity\Instance[] List of the created root instances.
     */
    public function processInstancesData(
        Entity\Anr $anr,
        array $instancesData,
        ?Entity\Instance $parentInstance,
        string $importMode,
        bool $withEval
    ): array {
        $maxPositionInsideOfParentInstance = 0;
        /* The query to get the max position is executed only if the parent instances is set and stored in the DB. */
        if ($parentInstance !== null && $parentInstance->getId() !== null) {
            $maxPositionInsideOfParentInstance = $this->instanceTable->findMaxPosition(
                $parentInstance->getImplicitPositionRelationsValues()
            );
        }
        $instances = [];
        foreach ($instancesData as $instanceData) {
            $instanceData['position'] += $maxPositionInsideOfParentInstance++;
            $instanceData['setOnlyExactPosition'] = true;
            $instances[] = $this->processInstanceData($anr, $instanceData, $parentInstance, $importMode);
        }

        return $instances;
    }

    /** The method is called as a starting point of the root instances import and should not be called recursively. */
    public function processInstanceData(
        Entity\Anr $anr,
        array $instanceData,
        ?Entity\Instance $parentInstance,
        string $importMode
    ): Entity\Instance {
        $objectCategory = null;
        if (isset($instanceData['object']['category'])) {
            $objectCategory = $this->objectCategoryImportProcessor
                ->processObjectCategoryData($anr, $objectCategory, $importMode);
        }
        $instanceData['object'] = $this->objectImportProcessor
            ->processObjectData($anr, $objectCategory, $instanceData['object'], $importMode);
        $instanceData['parent'] = $parentInstance;

        /* For the instances import the values have to be converted to local scales. */
        if ($this->importCacheHelper->getValueFromArrayCache('with_eval') && $this->importCacheHelper
            ->getValueFromArrayCache('import_type') === InstanceImportService::IMPORT_TYPE_INSTANCE
        ) {
            $this->convertInstanceEvaluations($instanceData);
        }
        $instance = $this->instanceService->createInstance($anr, $instanceData, $parentInstance === null, false);
        $this->prepareAndProcessInstanceConsequencesData($instance, $instanceData['instancesConsequences']);
        $instance->updateImpactBasedOnConsequences();
        /* In case if there is a parent instance, the scales impacts could be adjusted, if they are not set directly. */
        if ($parentInstance !== null) {
            $instance->refreshInheritedImpact();
        }

        $siblingInstances = [];
        if (!$this->importCacheHelper->getValueFromArrayCache('with_eval') && $instance->getObject()->isScopeGlobal()) {
            $siblingInstances = $this->getGlobalObjectInstancesFromCache($anr, $instance->getObject()->getUuid());
        }
        $this->instanceRiskImportProcessor
            ->processInstanceRisksData($instance, $siblingInstances, $instanceData['instanceRisks']);

        $this->operationalInstanceRiskImportProcessor
            ->processOperationalInstanceRisksData($anr, $instance, $instanceData['operationalInstanceRisks']);

        $this->processInstanceMetadata($anr, $instance, $instanceData['instanceMetadata']);

        if (!empty($instanceData['children'])) {
            $this->processInstancesData($anr, $instanceData['children'], $instance, $importMode);
        }

        return $instance;
    }

    public function processInstanceMetadata(
        Entity\Anr $anr,
        Entity\Instance $instance,
        array $instanceMetadataData
    ): void {
        foreach ($instanceMetadataData as $metadataDatum) {
            $metadataField = $this->anrInstanceMetadataFieldImportProcessor->processAnrInstanceMetadataField($anr, [
                'label' => $metadataDatum['anrInstanceMetadataField']['label'] ?? $metadataDatum['label'],
                'isDeletable' => true,
            ]);

            $instanceMetadata = $instance->getInstanceMetadataByMetadataFieldLink($metadataField);
            if ($instanceMetadata === null) {
                $instanceMetadata = (new Entity\InstanceMetadata())
                    ->setInstance($instance)
                    ->setAnrInstanceMetadataField($metadataField)
                    ->setComment($metadataDatum['comment'])
                    ->setCreator($this->connectedUser->getEmail());
                $this->instanceMetadataTable->save($instanceMetadata, false);

                $this->applyInstanceMetadataToSiblings($anr, $instance, $instanceMetadata);
            } elseif ($instanceMetadata->getComment() !== $metadataDatum['comment']) {
                $instanceMetadata->setComment($metadataDatum['comment'])->setUpdater($this->connectedUser->getEmail());
                $this->instanceMetadataTable->save($instanceMetadata, false);
            }
        }

        $this->updateInstanceMetadataFromSiblings($anr, $instance);
    }

    /** A wrapper method to help of processing the instance consequences data. */
    public function prepareAndProcessInstanceConsequencesData(
        Entity\Instance $instance,
        array $instanceConsequencesData
    ): void {
        /** @var Entity\Anr $anr */
        $anr = $instance->getAnr();
        /* When the importing data are without evaluation and the object is global
        the evaluations are taken from a sibling. */
        $siblingInstance = null;
        if (!$this->importCacheHelper->getValueFromArrayCache('with_eval') && $instance->getObject()->isScopeGlobal()) {
            $siblingInstances = $this->getGlobalObjectInstancesFromCache($anr, $instance->getObject()->getUuid());
            $siblingInstance = $siblingInstances[0] ?? null;
        }

        $this->instanceConsequenceImportProcessor
            ->processInstanceConsequencesData($instance, $instanceConsequencesData, $siblingInstance);
    }

    /**
     * Applies the newly created instance metadata to the others global sibling instances.
     */
    private function applyInstanceMetadataToSiblings(
        Entity\Anr $anr,
        Entity\Instance $instance,
        Entity\InstanceMetadata $instanceMetadata
    ): void {
        if ($instance->getObject()->isScopeGlobal()) {
            $instanceSiblings = $this->getGlobalObjectInstancesFromCache($anr, $instance->getObject()->getUuid());
            foreach ($instanceSiblings as $instanceSibling) {
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
    private function updateInstanceMetadataFromSiblings(Entity\Anr $anr, Entity\Instance $instance): void
    {
        if ($instance->getObject()->isScopeGlobal()) {
            $instanceSibling = current(
                $this->getGlobalObjectInstancesFromCache($anr, $instance->getObject()->getUuid())
            );
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

    /**
     * @return Entity\Instance[]
     */
    private function getGlobalObjectInstancesFromCache(Entity\Anr $anr, string $objectUuid): array
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_global_instances_cache_loaded')) {
            $this->importCacheHelper->setArrayCacheValue('is_global_instances_cache_loaded', true);
            foreach ($this->monarcObjectTable->findGlobalObjectsByAnr($anr) as $object) {
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

        return $this->importCacheHelper->getItemFromArrayCache('global_instances_by_object_uuids', $objectUuid) ?? [];
    }

    private function convertInstanceEvaluations(array &$instanceData): void
    {
        $currentScaleRange = $this->importCacheHelper
            ->getItemFromArrayCache('current_scales_data_by_type')[ScaleSuperClass::TYPE_IMPACT];
        $externalScaleRange = $this->importCacheHelper
            ->getItemFromArrayCache('external_scales_data_by_type')[ScaleSuperClass::TYPE_IMPACT];
        foreach (['confidentiality', 'integrity', 'availability'] as $propertyName) {
            $instanceData[$propertyName] = $this->convertValueWithinNewScalesRange(
                $instanceData[$propertyName],
                $externalScaleRange['min'],
                $externalScaleRange['max'],
                $currentScaleRange['min'],
                $currentScaleRange['max'],
            );
        }
    }
}
