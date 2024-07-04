<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\ObjectSuperClass;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Service\ObjectImportService;
use Monarc\FrontOffice\Service\AnrObjectObjectService;
use Monarc\FrontOffice\Service\AnrObjectService;
use Monarc\FrontOffice\Table\MonarcObjectTable;
use Monarc\FrontOffice\Table\ObjectObjectTable;

class ObjectImportProcessor
{
    public function __construct(
        private MonarcObjectTable $monarcObjectTable,
        private ObjectObjectTable $objectObjectTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrObjectService $anrObjectService,
        private AnrObjectObjectService $anrObjectObjectService,
        private AssetImportProcessor $assetImportProcessor,
        private RolfTagImportProcessor $rolfTagImportProcessor,
        private ObjectCategoryImportProcessor $objectCategoryImportProcessor
    ) {
    }

    public function processObjectsData(
        Entity\Anr $anr,
        Entity\ObjectCategory $objectCategory,
        array $objectsData,
        string $importMode
    ): void {
        $this->prepareObjectsCache($anr);
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
        /* Only newly created objects are stored under the cache key 'created_objects_by_old_uuids'. */
        if ($this->importCacheHelper->isItemInArrayCache('created_objects_by_old_uuids', $objectData['uuid'])) {
            return $this->importCacheHelper->getItemFromArrayCache('created_objects_by_old_uuids', $objectData['uuid']);
        }

        $assetData = $objectData['asset']['asset'] ?? $objectData['asset'];
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
                $objectData[$nameFiledKey],
                $assetData['uuid'],
                $objectScope,
                $objectCategory?->getId()
            );
            if ($object !== null) {
                $this->objectObjectTable->deleteLinksByParentObject($object);
            }
        }

        if ($object === null) {
            $asset = $this->assetImportProcessor->processAssetData($anr, $assetData);
            $rolfTag = null;
            if (!empty($objectData['rolfTag'])) {
                if (isset($objectData['rolfTag']['code'])) {
                    $rolfTag = $this->rolfTagImportProcessor->processRolfTagData($anr, $objectData['rolfTag']);
                } elseif (isset($objectData['rolfTags'][$objectData['rolfTag']])) {
                    /* Handles the structure prior the version 2.13.1 */
                    $rolfTag = $this->rolfTagImportProcessor
                        ->processRolfTagData($anr, $objectData['rolfTags'][$objectData['rolfTag']]);
                }
            }

            /* Avoid the UUID duplication. */
            $currentObjectUuid = $objectData['uuid'];
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
            $this->importCacheHelper->addItemToArrayCache(
                'objects_names',
                $objectData[$nameFiledKey],
                $objectData[$nameFiledKey]
            );
            $this->importCacheHelper->addItemToArrayCache('created_objects_by_old_uuids', $object, $currentObjectUuid);
        }

        /* Process objects links. */
        foreach ($objectData['children'] as $positionIndex => $childObjectData) {
            $objectCategory = $this->objectCategoryImportProcessor->processObjectCategoryData(
                $anr,
                $childObjectData['category'],
                $importMode
            );
            $childObject = $this->processObjectData($anr, $objectCategory, $childObjectData, $importMode);

            if (!$object->hasChild($childObject) && !$this->importCacheHelper->isItemInArrayCache(
                'objects_links_uuids',
                $object->getUuid() . $childObject->getUuid()
            )) {
                $this->anrObjectObjectService->createObjectLink($object, $childObject, [
                    'position' => $positionIndex + 1,
                    'forcePositionUpdate' => true,
                ], false);
                $this->importCacheHelper->addItemToArrayCache(
                    'objects_links_uuids',
                    $object->getUuid() . $childObject->getUuid(),
                    $object->getUuid() . $childObject->getUuid()
                );
            }
        }

        return $object;
    }

    public function getObjectFromCacheByParams(
        string $name,
        string $assetUuid,
        int $scope,
        ?int $categoryId
    ): ?Entity\MonarcObject {
        return $this->importCacheHelper->getItemFromArrayCache(
            'objects_by_name_asset_scope_category',
            $name . $assetUuid . $scope . $categoryId
        );
    }

    private function prepareObjectsCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('objects')) {
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
}
