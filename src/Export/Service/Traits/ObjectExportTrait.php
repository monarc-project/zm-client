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
    use OperationalRiskExportTrait;

    private function prepareObjectData(
        Entity\MonarcObject $object,
        int $languageIndex,
        bool $addAmvsToAssetData,
        bool $includeCategory = true,
        bool $addRolfRisksInObjects = true
    ): array {
        /** @var Entity\ObjectCategory $objectCategory */
        $objectCategory = $object->getCategory();
        /** @var Entity\Asset $asset */
        $asset = $object->getAsset();
        $assetData = $this->prepareAssetData($asset, $languageIndex);
        if ($addAmvsToAssetData) {
            $assetData['informationRisks'] = [];
            foreach ($asset->getAmvs() as $amv) {
                $assetData['informationRisks'][] = $this->prepareInformationRiskData($amv);
            }
        }
        $rolfTagData = null;
        if ($object->getRolfTag() !== null) {
            /** @var Entity\RolfTag $rolfTag */
            $rolfTag = $object->getRolfTag();
            $rolfTagData = [
                'id' => $rolfTag->getId(),
                'code' => $rolfTag->getCode(),
                'label' => $rolfTag->getLabel($languageIndex),
            ];
            if ($addRolfRisksInObjects) {
                $rolfTagData['rolfRisks'] = $this->prepareRolfRisksData($rolfTag);
            }
        }

        $result = [
            'uuid' => $object->getUuid(),
            'name' => $object->getName($languageIndex),
            'label' => $object->getLabel($languageIndex),
            'mode' => $object->getMode(),
            'scope' => $object->getScope(),
            'asset' => $assetData,
            'rolfTag' => $rolfTagData,
            'children' => $object->hasChildren() ? $this->prepareChildrenObjectsData(
                $object,
                $languageIndex,
                $addAmvsToAssetData,
                $addRolfRisksInObjects
            ) : [],
        ];
        if ($includeCategory) {
            $result['category'] = $objectCategory !== null
                ? $this->prepareCategoryAndParentsData($objectCategory)
                : null;
        }

        return $result;
    }

    private function prepareCategoryAndParentsData(Entity\ObjectCategory $objectCategory): array
    {
        /** @var ?Entity\ObjectCategory $parentCategory */
        $parentCategory = $objectCategory->getParent();

        return [
            'id' => $objectCategory->getId(),
            'label' => $objectCategory->getLabel($objectCategory->getAnr()->getLanguage()),
            'position' => $objectCategory->getPosition(),
            'parent' => $parentCategory !== null ? $this->prepareCategoryAndParentsData($parentCategory) : null,
        ];
    }

    private function prepareChildrenObjectsData(
        Entity\MonarcObject $object,
        int $languageIndex,
        bool $addAmvsToAssetData,
        bool $addRolfRisksInObjects
    ): array {
        $result = [];
        foreach ($object->getChildrenLinks() as $childLink) {
            /** @var Entity\MonarcObject $childObject */
            $childObject = $childLink->getChild();
            $result[] = $this
                ->prepareObjectData($childObject, $languageIndex, $addAmvsToAssetData, true, $addRolfRisksInObjects);
        }

        return $result;
    }

    private function prepareRolfRisksData(Entity\RolfTag $rolfTag): array
    {
        $rolfRisksData = [];
        $languageIndex = $rolfTag->getAnr()->getLanguage();
        foreach ($rolfTag->getRisks() as $rolfRisk) {
            $rolfRisksData[] = $this->prepareOperationalRiskData($rolfRisk, $languageIndex);
        }

        return $rolfRisksData;
    }
}
