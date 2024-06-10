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
    private array $linksMaxPositions = [];

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
        Entity\ObjectCategory $objectCategory,
        array $objectData,
        string $importMode
    ): Entity\MonarcObject {
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
            $object = $this->getObjectFromCache(
                $objectData[$nameFiledKey],
                $assetData['uuid'],
                $objectScope,
                $objectCategory->getId()
            );
            if ($object !== null) {
                foreach ($object->getChildren() as $linkedObject) {
                    $object->removeChild($linkedObject);
                }
                $this->monarcObjectTable->save($object, false);
            }
        }

        if ($object === null) {
            $asset = $this->assetImportProcessor->processAssetData($anr, $assetData);
            $rolfTag = null;
            if (!empty($objectData['rofTag'])) {
                if (isset($objectData['rofTag']['code'])) {
                    $rolfTag = $this->rolfTagImportProcessor->processRolfTagData($anr, $objectData['rofTag']);
                } elseif (isset($objectData['rofTags'][$objectData['rofTag']])) {
                    /* Handles the structure prior the version 2.13.1 */
                    $rolfTag = $this->rolfTagImportProcessor
                        ->processRolfTagData($anr, $objectData['rofTags'][$objectData['rofTag']]);
                }
            }

            /* Avoid the UUID duplication. */
            if ($this->importCacheHelper->isItemInArrayCache('objects_uuid', $objectData['uuid'])) {
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
                $objectData[$nameFiledKey] . $asset->getUuid() . $object->getScope() . $objectCategory->getId()
            );
            $this->importCacheHelper->addItemToArrayCache('objects_names', $objectData[$nameFiledKey]);
        }

        /* Process objects links. */
        foreach ($objectData['children'] as $childObjectData) {
            $objectCategory = $this->objectCategoryImportProcessor->processObjectCategoryData(
                $anr,
                $childObjectData['category'],
                $importMode
            );
            $childObject = $this->processObjectData($anr, $objectCategory, $childObjectData, $importMode);
            /* Determine the max position of the link and store in the cache property. */
            if (!isset($this->linksMaxPositions[$object->getUuid()])) {
                $this->linksMaxPositions[$object->getUuid()] = $this->objectObjectTable->findMaxPosition([
                    'anr' => $anr,
                    'parent' => $object,
                ]);
            }
            $positionData = [
                'position' => ++$this->linksMaxPositions[$object->getUuid()],
                'forcePositionUpdate' => true,
            ];
            $this->anrObjectObjectService->createObjectLink($object, $childObject, $positionData, false);
        }

        return $object;
    }

    public function getObjectFromCache(
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
                $this->importCacheHelper->addItemToArrayCache('objects_names', $object->getName($languageIndex));
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
