<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Interfaces\PositionUpdatableServiceInterface;
use Monarc\Core\Service\Traits\PositionUpdateTrait;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table\ObjectCategoryTable;

class AnrObjectCategoryService
{
    use PositionUpdateTrait;

    private UserSuperClass $connectedUser;

    public function __construct(
        private ObjectCategoryTable $objectCategoryTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getObjectCategoryData(Entity\Anr $anr, int $id): array
    {
        /** @var Entity\ObjectCategory $objectCategory */
        $objectCategory = $this->objectCategoryTable->findByIdAndAnr($id, $anr);

        $objectCategoryData = [
            'id' => $objectCategory->getId(),
            'root' => $objectCategory->getRoot() !== null
                ? ['id' => $objectCategory->getRoot()->getId()]
                : null,
            'parent' => $objectCategory->hasParent()
                ? [
                    'id' => $objectCategory->getParent()->getId(),
                    'label' . $anr->getLanguage() => $objectCategory->getParent()->getLabel($anr->getLanguage()),
                ]
                : null,
            'label' . $anr->getLanguage() => $objectCategory->getLabel($anr->getLanguage()),
            'position' => $objectCategory->getPosition(),
            'previous' => null,
            'implicitPosition' => 1,
        ];

        if ($objectCategory->getPosition() > 1) {
            $maxPosition = $this->objectCategoryTable
                ->findMaxPosition($objectCategory->getImplicitPositionRelationsValues());
            if ($objectCategory->getPosition() >= $maxPosition) {
                $objectCategoryData['implicitPosition'] = PositionUpdatableServiceInterface::IMPLICIT_POSITION_END;
            } else {
                $objectCategoryData['implicitPosition'] = PositionUpdatableServiceInterface::IMPLICIT_POSITION_AFTER;
                $previousObjectCategory = $this->objectCategoryTable->findPreviousCategory($objectCategory);
                if ($previousObjectCategory !== null) {
                    $objectCategoryData['previous'] = $previousObjectCategory->getId();
                }
            }
        }

        return $objectCategoryData;
    }

    public function getList(FormattedInputParams $formattedInputParams)
    {
        $includeChildren = empty($formattedInputParams->getFilterFor('parentId')['value'])
            || empty($formattedInputParams->getFilterFor('lock')['value']);

        /* Fetch only root categories and populate their children in case if no filter by parentId or categoryId. */
        if ($includeChildren && empty($formattedInputParams->getFilterFor('catid')['value'])) {
            $formattedInputParams->setFilterValueFor('parent', null);
        }

        $categoriesData = [];
        /** @var Entity\ObjectCategory[] $objectCategories */
        $objectCategories = $this->objectCategoryTable->findByParams($formattedInputParams);
        foreach ($objectCategories as $objectCategory) {
            $categoriesData[] = $this->getPreparedObjectCategoryData($objectCategory, $includeChildren);
        }

        return $categoriesData;
    }

    public function getCount(FormattedInputParams $params): int
    {
        return $this->objectCategoryTable->countByParams($params);
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\ObjectCategory
    {
        $objectCategory = (new Entity\ObjectCategory())
            ->setAnr($anr)
            ->setLabels($data)
            ->setCreator($this->connectedUser->getEmail());

        if (!empty($data['parent'])) {
            /** @var Entity\ObjectCategory $parent */
            $parent = $data['parent'] instanceof Entity\ObjectCategory
                ? $data['parent']
                : $this->objectCategoryTable->findByIdAndAnr((int)$data['parent'], $anr);
            $objectCategory->setParent($parent);
            $objectCategory->setRoot($parent->getRootCategory());
        }

        $this->updatePositions($objectCategory, $this->objectCategoryTable, $data);

        $this->objectCategoryTable->save($objectCategory, $saveInDb);

        return $objectCategory;
    }

    public function update(Entity\Anr $anr, int $id, array $data): Entity\ObjectCategory
    {
        /** @var Entity\ObjectCategory $objectCategory */
        $objectCategory = $this->objectCategoryTable->findByIdAndAnr($id, $anr);

        $objectCategory->setLabels($data)->setUpdater($this->connectedUser->getEmail());

        /*
         * Perform operations to update the category's parent and root if necessary.
         * 1 condition. The case when the category's parent is changed. Before the category could be root or a child.
         * 2 condition. The case when the category becomes root (parent removed), and before it had a parent.
         */
        if (!empty($data['parent'])
            && (!$objectCategory->hasParent() || (int)$data['parent'] !== $objectCategory->getParent()?->getId())
        ) {
            /** @var Entity\ObjectCategory $parentCategory */
            $parentCategory = $this->objectCategoryTable->findByIdAndAnr((int)$data['parent'], $anr);

            /** @var Entity\ObjectCategory $previousRootCategory */
            $isRootCategoryBeforeUpdated = $objectCategory->isCategoryRoot();
            $hasRootCategoryChanged = $objectCategory->hasParent()
                && $parentCategory->getRootCategory()->getId() !== $objectCategory->getRootCategory()->getId();

            $objectCategory->setParent($parentCategory)->setRoot($parentCategory->getRootCategory());

            if ($isRootCategoryBeforeUpdated || $hasRootCategoryChanged) {
                /* Update the category children with the new root. */
                $this->updateRootOfChildrenTree($objectCategory);
            }
        } elseif (empty($data['parent']) && $objectCategory->hasParent()) {
            $objectCategory->setParent(null)->setRoot(null);

            /* Update the category children with the new root. */
            $this->updateRootOfChildrenTree($objectCategory);
        }

        $this->updatePositions(
            $objectCategory,
            $this->objectCategoryTable,
            array_merge($data, ['forcePositionUpdate' => true])
        );

        $this->objectCategoryTable->save($objectCategory);

        return $objectCategory;
    }

    public function delete(Entity\Anr $anr, int $id): void
    {
        /** @var Entity\ObjectCategory $objectCategory */
        $objectCategory = $this->objectCategoryTable->findByIdAndAnr($id, $anr);

        /* Remove all the relations with ANRs and adjust the overall positions. */
        $this->shiftPositionsForRemovingEntity($objectCategory, $this->objectCategoryTable);

        /* Set the removing category's parent for all its children */
        foreach ($objectCategory->getChildren() as $childCategory) {
            $childCategory->setParent($objectCategory->getParent())->setUpdater($this->connectedUser->getEmail());

            /* If the removing category is root, then all its direct children become root. */
            if ($objectCategory->isCategoryRoot()) {
                $childCategory->setRoot(null);
            }

            $this->updatePositions($childCategory, $this->objectCategoryTable, ['forcePositionUpdate' => true]);

            $this->objectCategoryTable->save($childCategory);
        }

        $this->objectCategoryTable->remove($objectCategory);
    }

    private function getPreparedObjectCategoryData(
        Entity\ObjectCategory $objectCategory,
        bool $includeChildren = true
    ): array {
        $result = [
            'id' => $objectCategory->getId(),
            'label1' => $objectCategory->getLabel(1),
            'label2' => $objectCategory->getLabel(2),
            'label3' => $objectCategory->getLabel(3),
            'label4' => $objectCategory->getLabel(4),
            'position' => $objectCategory->getPosition(),
        ];

        if ($includeChildren) {
            foreach ($objectCategory->getChildren() as $childCategory) {
                $result['child'][] = $this->getPreparedObjectCategoryData($childCategory);
            }
        }

        return $result;
    }

    private function updateRootOfChildrenTree(Entity\ObjectCategory $objectCategory): void
    {
        foreach ($objectCategory->getChildren() as $childCategory) {
            $childCategory->setRoot($objectCategory->getRootCategory());
            $this->objectCategoryTable->save($childCategory, false);

            $this->updateRootOfChildrenTree($childCategory);
        }
    }
}
