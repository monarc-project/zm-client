<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Entity\InstanceRiskSuperClass;
use Monarc\Core\Entity\ObjectSuperClass;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Import\Service\ObjectImportService;
use Monarc\FrontOffice\Service;
use Monarc\FrontOffice\Table;

class ObjectImportProcessor
{
    public function __construct(
        private Table\MonarcObjectTable $monarcObjectTable,
        private Table\ObjectObjectTable $objectObjectTable,
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private Table\AmvTable $amvTable,
        private ImportCacheHelper $importCacheHelper,
        private Service\AnrObjectService $anrObjectService,
        private Service\AnrObjectObjectService $anrObjectObjectService,
        private Service\AnrInstanceRiskService $anrInstanceRiskService,
        private Service\AnrInstanceRiskOpService $anrInstanceRiskOpService,
        private AssetImportProcessor $assetImportProcessor,
        private RolfTagImportProcessor $rolfTagImportProcessor,
        private ObjectCategoryImportProcessor $objectCategoryImportProcessor,
        private InformationRiskImportProcessor $informationRiskImportProcessor
    ) {
    }

    public function processObjectsData(
        Entity\Anr $anr,
        Entity\ObjectCategory $objectCategory,
        array $objectsData,
        string $importMode
    ): void {
        foreach ($objectsData as $objectData) {
            $this->processObjectData($anr, $objectCategory, $objectData, $importMode);
        }
    }

    public function processObjectData(
        Entity\Anr $anr,
        ?Entity\ObjectCategory $objectCategory,
        array $objectData,
        string $importMode
    ): Entity\MonarcObject {
        /* Only processed objects are stored under the cache key 'processed_objects_by_old_uuids'. */
        if ($this->importCacheHelper->isItemInArrayCache('processed_objects_by_old_uuids', $objectData['uuid'])) {
            return $this->importCacheHelper
                ->getItemFromArrayCache('processed_objects_by_old_uuids', $objectData['uuid']);
        }

        $objectScope = (int)$objectData['scope'];
        $nameFiledKey = 'name' . $anr->getLanguage();
        $object = null;
        /* In the new data structure there is only "name" field set. */
        if (isset($objectData['name'])) {
            $objectData[$nameFiledKey] = $objectData['name'];
        }
        if ($objectScope === ObjectSuperClass::SCOPE_LOCAL || (
            $objectScope === ObjectSuperClass::SCOPE_GLOBAL && $importMode === ObjectImportService::IMPORT_MODE_MERGE
        )) {
            $object = $this->getObjectFromCacheByParams(
                $anr,
                $objectData[$nameFiledKey],
                $objectData['asset']['uuid'],
                $objectScope,
                $objectCategory?->getId()
            );
            if ($object !== null) {
                $this->objectObjectTable->deleteLinksByParentObject($object);
            }
        }

        $isImportTypeObject = $this->importCacheHelper
            ->getValueFromArrayCache('import_type') === InstanceImportService::IMPORT_TYPE_OBJECT;
        $currentObjectUuid = $objectData['uuid'];
        if ($object === null) {
            /* If IMPORT_TYPE_OBJECT then the process of informationRisks/amvs is done inside. */
            $asset = $this->assetImportProcessor->processAssetData($anr, $objectData['asset']);

            $rolfTag = null;
            if (!empty($objectData['rolfTag'])) {
                /* If IMPORT_TYPE_OBJECT then the process of $objectData['rolfTag']['rolfRisks'] is done inside. */
                $rolfTag = $this->rolfTagImportProcessor->processRolfTagData($anr, $objectData['rolfTag']);
            }

            /* Avoid the UUID duplication. */
            if ($this->importCacheHelper->isItemInArrayCache('objects_uuids', $objectData['uuid'])) {
                unset($objectData['uuid']);
            }
            /* In the new data structure there is only "label" field set. */
            if (isset($objectData['label'])) {
                $objectData['label' . $anr->getLanguage()] = $objectData['label'];
            }
            $objectData[$nameFiledKey] = $this->prepareUniqueObjectName($objectData[$nameFiledKey]);

            $object = $this->anrObjectService
                ->createMonarcObject($anr, $asset, $objectCategory, $rolfTag, $objectData, false);

            $this->importCacheHelper->addItemToArrayCache(
                'objects_by_name_asset_scope_category',
                $object,
                $objectData[$nameFiledKey] . $asset->getUuid() . $object->getScope() . $objectCategory?->getId()
            );
            $this->importCacheHelper
                ->addItemToArrayCache('objects_names', $objectData[$nameFiledKey], $objectData[$nameFiledKey]);
        } elseif ($isImportTypeObject) {
            /* If asset's amvs (information risks) are different, then update them. */
            if (!empty($objectData['asset']['informationRisks'])) {
                $this->mergeAssetInformationRisks($object, $objectData['asset']['informationRisks']);
            }
            /* Validate if the RolfTag is the same or/and the linked to it operational risks are equal. */
            $this->mergeRolfTagOperationalRisks($anr, $object, $objectData['rolfTag']);
        }

        $this->importCacheHelper->addItemToArrayCache('processed_objects_by_old_uuids', $object, $currentObjectUuid);

        /* Process objects links. */
        foreach ($objectData['children'] as $positionIndex => $childObjectData) {
            $objectCategory = $this->objectCategoryImportProcessor
                ->processObjectCategoryData($anr, $childObjectData['category'], $importMode);
            $childObject = $this->processObjectData($anr, $objectCategory, $childObjectData, $importMode);

            $linksCacheKey = $object->getUuid() . $childObject->getUuid();
            if (!$object->hasChild($childObject)
                && !$this->importCacheHelper->isItemInArrayCache('objects_links_uuids', $linksCacheKey)
            ) {
                $this->anrObjectObjectService->createObjectLink($object, $childObject, [
                    'position' => $positionIndex + 1,
                    'forcePositionUpdate' => true,
                ], false);
                $this->importCacheHelper->addItemToArrayCache('objects_links_uuids', $linksCacheKey, $linksCacheKey);
            }
        }

        return $object;
    }

    private function getObjectFromCacheByParams(
        Entity\Anr $anr,
        string $name,
        string $assetUuid,
        int $scope,
        ?int $categoryId
    ): ?Entity\MonarcObject {
        if (!$this->importCacheHelper->isCacheKeySet('is_objects_cache_loaded')) {
            $this->importCacheHelper->setArrayCacheValue('is_objects_cache_loaded', true);
            $languageIndex = $anr->getLanguage();
            /** @var Entity\MonarcObject $object */
            foreach ($this->monarcObjectTable->findByAnr($anr) as $object) {
                $this->importCacheHelper->addItemToArrayCache('objects_uuids', $object->getUuid(), $object->getUuid());
                $this->importCacheHelper->addItemToArrayCache(
                    'objects_by_name_asset_scope_category',
                    $object,
                    $object->getName($languageIndex) . $object->getAsset()->getUuid() . $object->getScope()
                    . $object->getCategory()?->getId()
                );
                $this->importCacheHelper->addItemToArrayCache(
                    'objects_names',
                    $object->getName($languageIndex),
                    $object->getName($languageIndex)
                );
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache(
            'objects_by_name_asset_scope_category',
            $name . $assetUuid . $scope . $categoryId
        );
    }

    private function prepareUniqueObjectName(string $objectName, int $index = 1): string
    {
        if ($this->importCacheHelper->isItemInArrayCache('objects_names', $objectName)) {
            if (str_contains($objectName, ' - Imp. #')) {
                $objectName = preg_replace('/#\d+/', '#' . $index, $objectName);
            } else {
                $objectName .= ' - Imp. #' . $index;
            }

            return $this->prepareUniqueObjectName($objectName, $index + 1);
        }

        return $objectName;
    }

    /** Merges the amvs (information risks) of the existing object. */
    private function mergeAssetInformationRisks(Entity\MonarcObject $object, array $informationRisksData): void
    {
        $existingAmvs = [];
        /** @var Entity\Amv $amv */
        foreach ($object->getAsset()->getAmvs() as $amv) {
            $existingAmvs[$amv->getUuid()] = $amv;
        }
        $importingAmvsData = [];
        foreach ($informationRisksData as $informationRiskData) {
            $importingAmvsData[$informationRiskData['uuid']] = $informationRiskData;
        }
        foreach (array_diff_key($importingAmvsData, $existingAmvs) as $newImportingAmvData) {
            $amv = $this->informationRiskImportProcessor
                ->processInformationRiskData($object->getAnr(), $newImportingAmvData);
            /* Recreated instance risks if the object is instantiated. */
            foreach ($object->getInstances() as $instance) {
                $this->anrInstanceRiskService->createInstanceRisk($instance, $amv, null, null, null, false);
            }
        }
        /** @var Entity\Amv $existingAmvToRemove */
        foreach (array_diff_key($existingAmvs, $importingAmvsData) as $existingAmvToRemove) {
            /* Recreated instance risks if the object is instantiated. */
            foreach ($existingAmvToRemove->getInstanceRisks() as $instanceRiskToSetSpecific) {
                $instanceRiskToSetSpecific->setSpecific(InstanceRiskSuperClass::TYPE_SPECIFIC)->setAmv(null);
                $this->instanceRiskTable->save($instanceRiskToSetSpecific, false);
            }
            $this->amvTable->remove($existingAmvToRemove, false);
        }
    }

    /** Merges the rolfRisks (operational risks) of the existing object. */
    private function mergeRolfTagOperationalRisks(
        Entity\Anr $anr,
        Entity\MonarcObject $object,
        ?array $rolfTagData
    ): void {
        /* NOTE. If rolfTag stays the same, then the rolf risks could be validated and updated if different. */
        if (!empty($rolfTagData) && $object->getRolfTag() === null) {
            /* if there was no rolfTag and a new one is set. */
            $rolfTag = $this->rolfTagImportProcessor->processRolfTagData($anr, $rolfTagData);
            $object->setRolfTag($rolfTag);
            $this->monarcObjectTable->save($object, false);
        } elseif (empty($rolfTagData) && $object->getRolfTag() !== null) {
            /* if there was a rolfTag and now removed, then all the InstanceRiskOp have to be set as specific. */
            $this->setOperationalRisksSpecific($object);
            $object->setRolfTag(null);
            $this->monarcObjectTable->save($object, false);
        } elseif ($object->getRolfTag() !== null && $object->getRolfTag()->getCode() !== $rolfTagData['code']) {
            /* If rolfTag is changed, then all the op risks have to be updated. */
            $this->setOperationalRisksSpecific($object);
            $rolfTag = $this->rolfTagImportProcessor->processRolfTagData($anr, $rolfTagData);
            $object->setRolfTag($rolfTag);
            foreach ($object->getInstances() as $instance) {
                foreach ($rolfTag->getRisks() as $rolfRisk) {
                    $this->anrInstanceRiskOpService->createInstanceRiskOpWithScales($instance, $object, $rolfRisk);
                }
            }
            $this->monarcObjectTable->save($object, false);
        }
    }

    private function setOperationalRisksSpecific(Entity\MonarcObject $object): void
    {
        if ($object->getRolfTag() !== null) {
            foreach ($object->getRolfTag()->getRisks() as $rolfRisk) {
                foreach ($rolfRisk->getOperationalInstanceRisks() as $operationalInstanceRisk) {
                    $operationalInstanceRisk->setSpecific(InstanceRiskOpSuperClass::TYPE_SPECIFIC)->setRolfRisk(null);
                    $this->instanceRiskOpTable->save($operationalInstanceRisk, false);
                }
            }
        }
    }
}
