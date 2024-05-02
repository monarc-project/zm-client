<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\FrontOffice\Entity;

trait ObjectExportTrait
{
    use AssetExportTrait;
    use InformationRiskExportTrait;

    private function prepareObjectData(
        Entity\MonarcObject $object,
        int $languageIndex,
        bool $addAmvsToAssetData
    ): array {
        /** @var Entity\ObjectCategory $objectCategory */
        $objectCategory = $object->getCategory();
        /** @var Entity\RolfTag $rolfTag */
        $rolfTag = $object->getRolfTag();
        /** @var Entity\Asset $asset */
        $asset = $object->getAsset();
        $assetData = $this->prepareAssetData($asset, $languageIndex);
        if ($addAmvsToAssetData) {
            $assetData['informationRisks'] = [];
            foreach ($asset->getAmvs() as $amv) {
                $assetData['informationRisks'][$amv->getUuid()] = $this->prepareInformationRiskData($amv);
            }
        }

        return [
            'uuid' => $object->getUuid(),
            'name' => $object->getName($languageIndex),
            'label' => $object->getLabel($languageIndex),
            'mode' => $object->getMode(),
            'scope' => $object->getScope(),
            'position' => $object->getPosition(),
            'asset' => $assetData,
            'rolfTag' => $rolfTag !== null ? [
                'id' => $rolfTag->getId(),
                'code' => $rolfTag->getCode(),
                'label' => $rolfTag->getLabel($languageIndex),
                'rolfRisks' => $this->prepareRolfRisksData($rolfTag),
            ] : [],
            'category' => $objectCategory !== null ? $this->prepareCategoryAndParentsData($objectCategory) : null,
            'children' => $object->hasChildren()
                ? $this->prepareChildrenObjectsData($object, $languageIndex, $addAmvsToAssetData)
                : [],
        ];
    }

    private function prepareCategoryAndParentsData(Entity\ObjectCategory $objectCategory): array
    {
        $objectCategoryData[$objectCategory->getId()] = [
            'label' => $objectCategory->getLabel($objectCategory->getAnr()->getLanguage()),
            'position' => $objectCategory->getPosition(),
            'parent' => null,
        ];
        if ($objectCategory->getParent() !== null) {
            /** @var Entity\ObjectCategory $parentCategory */
            $parentCategory = $objectCategory->getParent();
            $objectCategoryData[$objectCategory->getId()]['parent'] = $this->prepareCategoryAndParentsData(
                $parentCategory
            );
        }

        return $objectCategoryData;
    }

    private function prepareChildrenObjectsData(
        Entity\MonarcObject $object,
        int $languageIndex,
        bool $addAmvsToAssetData
    ): array {
        $result = [];
        foreach ($object->getChildrenLinks() as $childLink) {
            /** @var Entity\MonarcObject $childObject */
            $childObject = $childLink->getChild();
            $result[$childObject->getUuid()] = $this
                ->prepareObjectData($childObject, $languageIndex, $addAmvsToAssetData);
        }

        return $result;
    }

    private function prepareRolfRisksData(Entity\RolfTag $rolfTag): array
    {
        $rolfRisksData = [];
        $languageIndex = $rolfTag->getAnr()->getLanguage();
        foreach ($rolfTag->getRisks() as $rolfRisk) {
            $rolfRisksData[$rolfRisk->getId()] = $this->prepareOperationalRiskData($rolfRisk, $languageIndex);
        }

        return $rolfRisksData;
    }
}
