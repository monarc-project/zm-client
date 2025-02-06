<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Entity as CoreEntity;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Traits\PositionUpdateTrait;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;

class AnrObjectService
{
    use PositionUpdateTrait;

    private CoreEntity\UserSuperClass $connectedUser;

    public function __construct(
        private Table\MonarcObjectTable $monarcObjectTable,
        private Table\InstanceTable $instanceTable,
        private Table\AssetTable $assetTable,
        private Table\ObjectCategoryTable $objectCategoryTable,
        private Table\ObjectObjectTable $objectObjectTable,
        private Table\RolfTagTable $rolfTagTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $formattedInputParams): array
    {
        $result = [];
        /** @var Entity\MonarcObject $object */
        foreach ($this->monarcObjectTable->findByParams($formattedInputParams) as $object) {
            $result[] = $this->getPreparedObjectData($object);
        }

        return $result;
    }

    public function getCount(FormattedInputParams $formattedInputParams): int
    {
        return $this->monarcObjectTable->countByParams($formattedInputParams, 'uuid');
    }

    public function getObjectData(Entity\Anr $anr, string $uuid, FormattedInputParams $formattedInputParams): array
    {
        /** @var Entity\MonarcObject $object */
        $object = $this->monarcObjectTable->findByUuidAndAnr($uuid, $anr);

        $objectData = $this->getPreparedObjectData($object);
        /* Object edit dialog scenario. */
        if (!$this->isAnrObjectMode($formattedInputParams->getFilter())) {
            return $objectData;
        }

        $anrLanguage = $anr->getLanguage();
        $objectData['children'] = $this->getChildrenTreeList($object, $anrLanguage);
        $objectData['risks'] = $this->getRisks($object, $anrLanguage);
        $objectData['oprisks'] = $this->getRisksOp($object, $anrLanguage);
        $objectData['parents'] = $this->getDirectParents($object, $anrLanguage);

        $instances = $this->instanceTable->findByAnrAndObject($anr, $object);
        $objectData['replicas'] = [];
        foreach ($instances as $instance) {
            $instanceHierarchy = $instance->getHierarchyArray();

            $names = [
                'name' . $anrLanguage => $anr->getLabel(),
            ];
            foreach ($instanceHierarchy as $instanceData) {
                $names['name' . $anrLanguage] .= ' > ' . $instanceData['name' . $anrLanguage];
            }
            $names['id'] = $instance->getId();
            $objectData['replicas'][] = $names;
        }

        return $objectData;
    }

    public function getLibraryTreeStructure(Entity\Anr $anr): array
    {
        $result = [];
        foreach ($this->objectCategoryTable->findRootCategoriesByAnrOrderedByPosition($anr) as $rootObjectCategory) {
            $objectsCategoriesData = $this->getCategoriesAndObjectsTreeList($rootObjectCategory);
            if (!empty($objectsCategoriesData)) {
                $result[] = $objectsCategoriesData;
            }
        }

        /* Places uncategorized objects. */
        $uncategorizedObjectsData = [];
        foreach ($anr->getObjects() as $object) {
            if (!$object->hasCategory()) {
                $uncategorizedObjectsData[] = $this->getPreparedObjectData($object, true);
            }
        }
        if (!empty($uncategorizedObjectsData)) {
            $result[] = CoreEntity\ObjectCategorySuperClass::getUndefinedCategoryData($uncategorizedObjectsData);
        }

        return $result;
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\MonarcObject
    {
        /** @var Entity\Asset $asset */
        $asset = $this->assetTable->findByUuidAndAnr($data['asset'], $anr);
        $this->validateAssetAndDataOnCreate($asset, $data);
        /** @var Entity\ObjectCategory $objectCategory */
        $objectCategory = $this->objectCategoryTable->findByIdAndAnr($data['category'], $anr);
        /** @var ?Entity\RolfTag $rolfTag */
        $rolfTag = !empty($data['rolfTag']) && $asset->isPrimary()
            ? $this->rolfTagTable->findByIdAndAnr($data['rolfTag'], $anr)
            : null;

        return $this->createMonarcObject($anr, $asset, $objectCategory, $rolfTag, $data, $saveInDb);
    }

    public function createMonarcObject(
        Entity\Anr $anr,
        Entity\Asset $asset,
        ?Entity\ObjectCategory $objectCategory,
        ?Entity\RolfTag $rolfTag,
        array $data,
        bool $saveInDb
    ): Entity\MonarcObject {
        $monarcObject = (new Entity\MonarcObject())
            ->setAnr($anr)
            ->setLabels($data)
            ->setNames($data)
            ->setAsset($asset)
            ->setCategory($objectCategory)
            ->setScope($data['scope'])
            ->setRolfTag($rolfTag)
            ->setCreator($this->connectedUser->getEmail());
        if (!empty($data['uuid'])) {
            $monarcObject->setUuid($data['uuid']);
        }

        $this->monarcObjectTable->save($monarcObject, $saveInDb);

        return $monarcObject;
    }

    /**
     * @return string[]
     */
    public function createList(Entity\Anr $anr, array $data): array
    {
        $createdObjectsUuids = [];
        foreach ($data as $objectData) {
            $object = $this->create($anr, $objectData, false);
            $createdObjectsUuids[] = $object->getUuid();
        }
        $this->monarcObjectTable->flush();

        return $createdObjectsUuids;
    }

    public function update(Entity\Anr $anr, string $uuid, array $data): Entity\MonarcObject
    {
        /** @var Entity\MonarcObject $monarcObject */
        $monarcObject = $this->monarcObjectTable->findByUuidAndAnr($uuid, $anr);
        $monarcObject
            ->setLabels($data)
            ->setNames($data)
            ->setUpdater($this->connectedUser->getEmail());

        if (!$monarcObject->hasCategory() || $monarcObject->getCategory()->getId() !== $data['category']) {
            /** @var Entity\ObjectCategory $category */
            $category = $this->objectCategoryTable->findById((int)$data['category']);
            $monarcObject->setCategory($category);
        }

        $this->adjustInstancesValidateAndSetRolfTag($monarcObject, $data);

        $this->monarcObjectTable->save($monarcObject);

        return $monarcObject;
    }

    public function duplicate(Entity\Anr $anr, array $data): Entity\MonarcObject
    {
        /** @var Entity\MonarcObject $monarcObjectToCopy */
        $monarcObjectToCopy = $this->monarcObjectTable->findByUuidAndAnr($data['id'], $anr);

        $newMonarcObject = $this->getObjectCopy($monarcObjectToCopy, $anr);

        $this->duplicateObjectChildren($monarcObjectToCopy, $newMonarcObject, $anr);

        $this->monarcObjectTable->save($newMonarcObject);

        return $newMonarcObject;
    }

    public function delete(Entity\Anr $anr, string $uuid): void
    {
        /** @var Entity\MonarcObject $monarcObject */
        $monarcObject = $this->monarcObjectTable->findByUuidAndAnr($uuid, $anr);

        /* Remove from the library object composition (affects all the linked analysis), shift positions. */
        foreach ($monarcObject->getParentsLinks() as $objectParentLink) {
            $this->shiftPositionsForRemovingEntity($objectParentLink, $this->objectObjectTable);
            $this->objectObjectTable->remove($objectParentLink, false);
        }

        /* Remove the directly linked instances and shift their positions. */
        foreach ($monarcObject->getInstances() as $instance) {
            $this->shiftPositionsForRemovingEntity($instance, $this->instanceTable);
            $instance->removeAllInstanceRisks()->removeAllOperationalInstanceRisks();
            $monarcObject->removeInstance($instance);
            $this->instanceTable->remove($instance, false);
        }

        /* Manage the positions shift for the objects and objects_objects tables. */
        foreach ($monarcObject->getParentsLinks() as $linkWhereTheObjectIsChild) {
            $this->shiftPositionsForRemovingEntity($linkWhereTheObjectIsChild, $this->objectObjectTable);
        }

        $this->monarcObjectTable->remove($monarcObject);
    }

    public function getParentsInAnr(Entity\Anr $anr, string $uuid)
    {
        /** @var Entity\MonarcObject $object */
        $object = $this->monarcObjectTable->findByUuidAndAnr($uuid, $anr);

        $anrLanguage = $anr->getLanguage();
        $directParents = [];
        foreach ($object->getParentsLinks() as $parentLink) {
            $directParents[] = [
                'uuid' => $parentLink->getParent()->getUuid(),
                'linkid' => $parentLink->getId(),
                'label' . $anrLanguage => $parentLink->getParent()->getLabel($anrLanguage),
                'name' . $anrLanguage => $parentLink->getParent()->getName($anrLanguage),
            ];
        }

        return $directParents;
    }

    private function validateAssetAndDataOnCreate(Entity\Asset $asset, array $data): void
    {
        if ($data['scope'] === CoreEntity\ObjectSuperClass::SCOPE_GLOBAL && $asset->isPrimary()) {
            throw new Exception('It is forbidden to create a global object linked to a primary asset', 412);
        }
    }

    private function getPreparedObjectData(Entity\MonarcObject $object, bool $objectOnly = false): array
    {
        $anr = $object->getAnr();
        $result = [
            'uuid' => $object->getUuid(),
            'label' . $anr->getLanguage() => $object->getLabel($anr->getLanguage()),
            'name' . $anr->getLanguage() => $object->getName($anr->getLanguage()),
            'scope' => $object->getScope(),
        ];

        if (!$objectOnly) {
            $result['category'] = $object->getCategory() !== null
                ? [
                    'id' => $object->getCategory()->getId(),
                    'position' => $object->getCategory()->getPosition(),
                    'label' . $anr->getLanguage() => $object->getCategory()->getLabel($anr->getLanguage()),
                ]
                : CoreEntity\ObjectCategorySuperClass::getUndefinedCategoryData([]);
            $result['asset'] = [
                'uuid' => $object->getAsset()->getUuid(),
                'label' . $anr->getLanguage() => $object->getAsset()->getLabel($anr->getLanguage()),
                'code' => $object->getAsset()->getCode(),
                'type' => $object->getAsset()->getType(),
                'mode' => $object->getAsset()->getMode(),
            ];
            $result['rolfTag'] = $object->getRolfTag() === null ? null : [
                'id' => $object->getRolfTag()->getId(),
                'code' => $object->getRolfTag()->getCode(),
                'label' . $anr->getLanguage() => $object->getRolfTag()->getLabel($anr->getLanguage()),
            ];
        }

        return $result;
    }

    private function isAnrObjectMode(array $filteredData): bool
    {
        return isset($filteredData['mode']['value']) && $filteredData['mode']['value'] === 'anr';
    }

    private function getChildrenTreeList(Entity\MonarcObject $object, int $anrLanguage): array
    {
        $result = [];
        foreach ($object->getChildrenLinks() as $childLinkObject) {
            /** @var Entity\MonarcObject $childMonarcObject */
            $childMonarcObject = $childLinkObject->getChild();
            $result[] = [
                'uuid' => $childMonarcObject->getUuid(),
                'label' . $anrLanguage => $childMonarcObject->getLabel($anrLanguage),
                'name' . $anrLanguage => $childMonarcObject->getName($anrLanguage),
                'component_link_id' => $childLinkObject->getId(),
                'mode' => $childMonarcObject->getMode(),
                'scope' => $childMonarcObject->getScope(),
                'children' => !$childMonarcObject->hasChildren()
                    ? []
                    : $this->getChildrenTreeList($childMonarcObject, $anrLanguage),
            ];
        }

        return $result;
    }

    private function getRisks(Entity\MonarcObject $object, int $anrLanguage): array
    {
        $risks = [];
        foreach ($object->getAsset()->getAmvs() as $amv) {
            $risks[] = [
                'id' => $amv->getUuid(),
                'threatLabel' . $anrLanguage => $amv->getThreat()->getLabel($anrLanguage),
                'threatDescription' . $anrLanguage => $amv->getThreat()->getDescription($anrLanguage),
                'threatRate' => '-',
                'vulnLabel' . $anrLanguage => $amv->getVulnerability()->getLabel($anrLanguage),
                'vulnDescription' . $anrLanguage => $amv->getVulnerability()->getDescription($anrLanguage),
                'vulnerabilityRate' => '-',
                'c_risk' => '-',
                'c_risk_enabled' => $amv->getThreat()->getConfidentiality(),
                'i_risk' => '-',
                'i_risk_enabled' => $amv->getThreat()->getIntegrity(),
                'd_risk' => '-',
                'd_risk_enabled' => $amv->getThreat()->getAvailability(),
                'comment' => '',
            ];
        }

        return $risks;
    }

    private function getRisksOp(Entity\MonarcObject $object, int $anrLanguage): array
    {
        $riskOps = [];
        if ($object->getRolfTag() !== null && $object->getAsset()->isPrimary()) {
            foreach ($object->getRolfTag()->getRisks() as $rolfRisk) {
                $riskOps[] = [
                    'label' . $anrLanguage => $rolfRisk->getLabel($anrLanguage),
                    'description' . $anrLanguage => $rolfRisk->getDescription($anrLanguage),
                ];
            }
        }

        return $riskOps;
    }

    private function getDirectParents(Entity\MonarcObject $object, int $anrLanguage): array
    {
        $parents = [];
        foreach ($object->getParents() as $parentObject) {
            $parents[] = [
                'name' . $anrLanguage => $parentObject->getName($anrLanguage),
                'label' . $anrLanguage => $parentObject->getLabel($anrLanguage),
            ];
        }

        return $parents;
    }

    private function adjustInstancesValidateAndSetRolfTag(Entity\MonarcObject $monarcObject, array $data): void
    {
        /* Set operational risks to specific only when RolfTag was set before, and another RolfTag or null is set. */
        $isRolfTagUpdated = false;
        if (!empty($data['rolfTag']) && (
            $monarcObject->getRolfTag() === null || (int)$data['rolfTag'] !== $monarcObject->getRolfTag()->getId()
        )) {
            /** @var Entity\RolfTag $rolfTag */
            $rolfTag = $this->rolfTagTable->findByIdAndAnr((int)$data['rolfTag'], $monarcObject->getAnr());
            $monarcObject->setRolfTag($rolfTag);

            /* A new RolfTag is linked, set all linked operational risks to specific, new risks should be created. */
            $isRolfTagUpdated = true;
        } elseif (empty($data['rolfTag']) && $monarcObject->getRolfTag() !== null) {
            $monarcObject->setRolfTag(null);

            /* Set all linked operational risks to specific, no new risks to create. */
            $isRolfTagUpdated = true;
        }

        $this->updateInstancesAndOperationalRisks($monarcObject, $isRolfTagUpdated);
    }

    private function updateInstancesAndOperationalRisks(Entity\MonarcObject $monarcObject, bool $isRolfTagUpdated): void
    {
        foreach ($monarcObject->getInstances() as $instance) {
            $instance->setNames($monarcObject->getNames())
                ->setLabels($monarcObject->getLabels());
            $this->instanceTable->save($instance, false);

            if (!$monarcObject->getAsset()->isPrimary()) {
                continue;
            }

            $rolfRisksIdsToOperationalInstanceRisks = [];
            foreach ($instance->getOperationalInstanceRisks() as $operationalInstanceRisk) {
                $rolfRiskId = $operationalInstanceRisk->getRolfRisk()->getId();
                $rolfRisksIdsToOperationalInstanceRisks[$rolfRiskId] = $operationalInstanceRisk;
                if ($isRolfTagUpdated) {
                    /* If the tag is updated, the existing operational risks have to become specific. */
                    $operationalInstanceRisk->setIsSpecific(true);
                    $this->instanceRiskOpTable->save($operationalInstanceRisk, false);
                }
            }

            if ($isRolfTagUpdated && $monarcObject->getRolfTag() !== null) {
                foreach ($monarcObject->getRolfTag()->getRisks() as $rolfRisk) {
                    if (isset($rolfRisksIdsToOperationalInstanceRisks[$rolfRisk->getId()])) {
                        /* Restore the specific to false if the operational risk is linked to another risk. */
                        $rolfRisksIdsToOperationalInstanceRisks[$rolfRisk->getId()]->setIsSpecific(false);
                        $this->instanceRiskOpTable->save(
                            $rolfRisksIdsToOperationalInstanceRisks[$rolfRisk->getId()],
                            false
                        );
                    } else {
                        /* Recreate the risk with scales if it did not exist before. */
                        $this->anrInstanceRiskOpService->createInstanceRiskOpWithScales(
                            $instance,
                            $monarcObject,
                            $rolfRisk
                        );
                    }
                }
            }
        }
    }

    private function getObjectCopy(Entity\MonarcObject $monarcObjectToCopy, Entity\Anr $anr): Entity\MonarcObject
    {
        $labelsNamesSuffix = ' copy #' . time();
        /** @var Entity\MonarcObject $newMonarcObject */
        $newMonarcObject = (new Entity\MonarcObject())
            ->setAnr($anr)
            ->setCategory($monarcObjectToCopy->getCategory())
            ->setAsset($monarcObjectToCopy->getAsset())
            ->setLabels([
                'label1' => $monarcObjectToCopy->getLabelCleanedFromCopy(1) . $labelsNamesSuffix,
                'label2' => $monarcObjectToCopy->getLabelCleanedFromCopy(2) . $labelsNamesSuffix,
                'label3' => $monarcObjectToCopy->getLabelCleanedFromCopy(3) . $labelsNamesSuffix,
                'label4' => $monarcObjectToCopy->getLabelCleanedFromCopy(4) . $labelsNamesSuffix,
            ])
            ->setNames([
                'name1' => $monarcObjectToCopy->getNameCleanedFromCopy(1) . $labelsNamesSuffix,
                'name2' => $monarcObjectToCopy->getNameCleanedFromCopy(2) . $labelsNamesSuffix,
                'name3' => $monarcObjectToCopy->getNameCleanedFromCopy(3) . $labelsNamesSuffix,
                'name4' => $monarcObjectToCopy->getNameCleanedFromCopy(4) . $labelsNamesSuffix,
            ])
            ->setScope($monarcObjectToCopy->getScope())
            ->setCreator($this->connectedUser->getEmail());
        if ($monarcObjectToCopy->hasRolfTag()) {
            $newMonarcObject->setRolfTag($monarcObjectToCopy->getRolfTag());
        }

        return $newMonarcObject;
    }

    private function duplicateObjectChildren(
        Entity\MonarcObject $objectToCopy,
        Entity\MonarcObject $parentObject,
        Entity\Anr $anr
    ): void {
        foreach ($objectToCopy->getChildren() as $childObject) {
            $newChildObject = $this->getObjectCopy($childObject, $anr);

            /* Only to keep the same positions in the duplicated object composition. */
            foreach ($childObject->getParentsLinks() as $parentLink) {
                /* The child object could be presented in different compositions, so validate if the parent is right. */
                if ($parentLink->getParent()->isEqualTo($objectToCopy)) {
                    $newParentLink = (new Entity\ObjectObject())
                        ->setAnr($anr)
                        ->setParent($parentObject)
                        ->setChild($newChildObject)
                        ->setPosition($parentLink->getPosition())
                        ->setCreator($this->connectedUser->getEmail());
                    $this->objectObjectTable->save($newParentLink, false);

                    $newChildObject->addParentLink($newParentLink);
                }
            }

            $this->monarcObjectTable->save($newChildObject, false);

            if ($childObject->hasChildren()) {
                $this->duplicateObjectChildren($childObject, $newChildObject, $anr);
            }
        }
    }

    private function getPreparedObjectCategoryData(Entity\ObjectCategory $category, array $objectsData): array
    {
        $result = [
            'id' => $category->getId(),
            'label' . $category->getAnr()->getLanguage() => $category->getLabel($category->getAnr()->getLanguage()),
            'position' => $category->getPosition(),
            'child' => !$category->hasChildren() ? [] : $this->getCategoriesWithObjectsChildrenTreeList($category),
            'objects' => $objectsData,
        ];
        if (empty($objectsData) && empty($result['child'])) {
            return [];
        }

        return $result;
    }

    private function getCategoriesAndObjectsTreeList(Entity\ObjectCategory $objectCategory): array
    {
        $result = [];
        $objectsData = $this->getObjectsDataOfCategory($objectCategory);
        if (!empty($objectsData) || $objectCategory->hasChildren()) {
            $objectCategoryData = $this->getPreparedObjectCategoryData($objectCategory, $objectsData);
            if (!empty($objectsData) || !empty($objectCategoryData)) {
                $result = $objectCategoryData;
            }
        }

        return $result;
    }

    private function getCategoriesWithObjectsChildrenTreeList(Entity\ObjectCategory $objectCategory): array
    {
        $result = [];
        foreach ($objectCategory->getChildren() as $childCategory) {
            $categoryData = $this->getCategoriesAndObjectsTreeList($childCategory);
            if (!empty($categoryData)) {
                $result[] = $categoryData;
            }
        }

        return $result;
    }

    private function getObjectsDataOfCategory(Entity\ObjectCategory $objectCategory): array
    {
        $objectsData = [];
        /** @var Entity\MonarcObject $object */
        foreach ($objectCategory->getObjects() as $object) {
            $objectsData[] = $this->getPreparedObjectData($object, true);
        }

        return $objectsData;
    }
}
