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

        $currentObjectUuid = $objectData['uuid'];
        if ($object === null) {
            if (empty($objectData['asset'])) {
                $a = 1;
            }
            $asset = $this->assetImportProcessor->processAssetData($anr, $objectData['asset']);

            $rolfTag = null;
            if (!empty($objectData['rolfTag'])) {
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
        } else {
            /* If asset's amvs (information risks) are different, then update them. */
            if (isset($objectData['asset']['informationRisks'])) {
                $this->mergeAssetInformationRisks($object, $objectData['asset']['informationRisks']);
            }
            /* Validate if the RolfTag is the same or/and the linked to it operational risks are equal. */
            $this->mergeRolfTagOperationalRisks($object, $objectData['rolfTag']);
        }

        $this->importCacheHelper->addItemToArrayCache('processed_objects_by_old_uuids', $object, $currentObjectUuid);

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

    private function getObjectFromCacheByParams(
        Entity\Anr $anr,
        string $name,
        string $assetUuid,
        int $scope,
        ?int $categoryId
    ): ?Entity\MonarcObject {
        if (!$this->importCacheHelper->isCacheKeySet('is_objects_cache_loaded')) {
            $this->importCacheHelper->addItemToArrayCache('is_objects_cache_loaded', true);
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

    private function mergeAssetInformationRisks(Entity\MonarcObject $object, array $informationRisksData): void
    {
        foreach ($informationRisksData as $informationRiskData) {
            $asset = $object->getAsset();
            $informationRiskData['uuid'];
            // TODO: !!! Instance risks are supposed to be processed in the InstanceRiskImportProcessor, so here we correct only amvs. !!!
            // the same is for op risks.
            // TODO: + figure out (based on AssetImportService) if the asset's data have to be reprocessed if informationRisks are set.
            // TODO: 1. match the difference and update them
            // -> when amv is added : add new instance risks
            // -> when removed: turn existing instance risks to specific and drop the amv.
        }
    }

    private function mergeRolfTagOperationalRisks(Entity\MonarcObject $object, ?array $rolfTagData): void
    {
        if (empty($rolfTagData) && $object->getRolfTag() !== null) {
            // if ($object->getRolfTag()->getCode() !== $rolfTagData['code']) {}
            // TODO: 1. if there was no rolfTag and we set, then also create the rolf risks if different and add/remove instance risks op.
            // TODO: 2. was a rolf tag and now removed, then remove all the instance risks op have to be removed.
            // TODO: 3. if a new rolf is changed, then all the op risks have to be updated.
            // TODO: 4. rolfTag stays the same (set) then we have to validate the difference of rolf risks.
        }
    }
}
