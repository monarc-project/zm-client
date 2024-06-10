<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrObjectCategoryService;
use Monarc\FrontOffice\Table\ObjectCategoryTable;

class ObjectCategoryImportProcessor
{
    private array $maxPositionPerCategory = [];

    public function __construct(
        private ObjectCategoryTable $objectCategoryTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrObjectCategoryService $anrObjectCategoryService,
        private ObjectImportProcessor $objectImportProcessor
    ) {
    }

    /**
     * @param string $importMode 'merge'|'duplicate' Is used for objects creation.
     */
    public function processObjectCategoriesData(Entity\Anr $anr, array $objectCategoriesData, string $importMode): void
    {
        $this->prepareObjectCategoriesCache($anr);
        foreach ($objectCategoriesData as $categoryData) {
            $this->processObjectCategoryData($anr, $categoryData, $importMode);
        }
    }

    public function processObjectCategoryData(
        Entity\Anr $anr,
        array $objectCategoryData,
        string $importMode
    ): Entity\ObjectCategory {
        $parentCategory = isset(
            $objectCategoryData['parent']
        ) && $objectCategoryData['parent'] instanceof Entity\ObjectCategory ? $objectCategoryData['parent'] : null;

        // TODO: process the categories data when the request comes NOT from the library:
        // - parent and it's parents' data are inside so this method have to be recursively called.
        // - support he old structure format:
        // -> the categories data are under the objects structure and they are inside of the "instances" !!!

        $parentCategoryLabel = '';
        if ($parentCategory !== null) {
            $parentCategoryLabel = $parentCategory->getLabel($anr->getLanguage());
        }

        /* If parents are different, a new category is created anyway. */
        /** @var ?Entity\ObjectCategory $objectCategory */
        $objectCategory = $this->importCacheHelper->getItemFromArrayCache(
            'object_categories_by_label',
            $objectCategoryData['label'] . $parentCategoryLabel
        );

        if ($objectCategory === null) {
            /* In the new data structure there is only "label" field set. */
            if (isset($objectCategoryData['label'])) {
                $objectCategoryData['label' . $anr->getLanguage()] = $objectCategoryData['label'];
            }
            /* Prepare the position and cache it. */
            if (!isset($this->maxPositionPerCategory[$parentCategoryLabel])) {
                /* If the parent category in new, there is no need to fetch it from the DB. */
                $this->maxPositionPerCategory[$parentCategoryLabel] = 0;
                if ($parentCategory === null || $parentCategory->getId() !== null) {
                    $this->maxPositionPerCategory[$parentCategoryLabel] = $this->objectCategoryTable
                        ->findMaxPositionByAnrAndParent($anr, $parentCategory);
                }
            }
            $objectCategoryData['position'] = ++$this->maxPositionPerCategory[$parentCategoryLabel];
            $objectCategoryData['setOnlyExactPosition'] = true;

            $objectCategory = $this->anrObjectCategoryService->create($anr, $objectCategoryData, false);
            /* Adds the parent category label to the item key to avoid its comparison in the children's process. */
            $this->importCacheHelper->addItemToArrayCache(
                'object_categories_by_label',
                $objectCategory,
                $objectCategory->getLabel($anr->getLanguage()) . $parentCategoryLabel
            );
        }

        if (!empty($objectCategoryData['objects'])) {
            $this->objectImportProcessor
                ->processObjectsData($anr, $objectCategory, $objectCategoryData['objects'], $importMode);
        }
        if (!empty($objectCategoryData['children'])) {
            foreach ($objectCategoryData['children'] as $childObjectCategoryData) {
                $childObjectCategoryData['parent'] = $objectCategory;
                $this->processObjectCategoryData($anr, $childObjectCategoryData, $importMode);
            }
        }

        return $objectCategory;
    }

    /* The categories cache's items keys are based on their labels + parent's labels if not root. */
    private function prepareObjectCategoriesCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('object_categories_by_label')) {
            $languageIndex = $anr->getLanguage();
            /** @var Entity\ObjectCategory $objectCategory */
            foreach ($this->objectCategoryTable->findByAnr($anr) as $objectCategory) {
                $parentCategoryLabel = '';
                if ($objectCategory->getParent() !== null) {
                    $parentCategoryLabel = $objectCategory->getParent()->getLabel($languageIndex);
                }
                $this->importCacheHelper->addItemToArrayCache(
                    'object_categories_by_label',
                    $objectCategory,
                    $objectCategory->getLabel($languageIndex) . $parentCategoryLabel
                );
            }
        }
    }
}
