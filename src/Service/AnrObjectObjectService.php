<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Interfaces\PositionUpdatableServiceInterface;
use Monarc\Core\Service\Traits\PositionUpdateTrait;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table;

class AnrObjectObjectService
{
    use PositionUpdateTrait;

    public const MOVE_COMPOSITION_POSITION_UP = 'up';
    public const MOVE_COMPOSITION_POSITION_DOWN = 'down';

    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\ObjectObjectTable $objectObjectTable,
        private Table\MonarcObjectTable $monarcObjectTable,
        private Table\InstanceTable $instanceTable,
        private AnrInstanceService $anrInstanceService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\ObjectObject
    {
        if ($data['parent'] === $data['child']) {
            throw new Exception('It\'s not allowed to compose the same child object as parent.', 412);
        }

        /** @var Entity\MonarcObject $parentObject */
        $parentObject = $this->monarcObjectTable->findByUuidAndAnr($data['parent'], $anr);
        /** @var Entity\MonarcObject $childObject */
        $childObject = $this->monarcObjectTable->findByUuidAndAnr($data['child'], $anr);
        if ($parentObject->hasChild($childObject)) {
            throw new Exception('The object is already presented in the composition.', 412);
        }

        if ($parentObject->isModeGeneric() && $childObject->isModeSpecific()) {
            throw new Exception('It\'s not allowed to add a specific object to a generic parent', 412);
        }

        /* Validate if one of the parents is the current child or its children. */
        $this->validateIfObjectOrItsChildrenLinkedToOneOfParents($childObject, $parentObject);

        $objectObject = $this->createObjectLink($parentObject, $childObject, $data, $saveInDb);

        /* Create instances of child object if necessary. */
        if ($parentObject->hasInstances()) {
            $this->createInstances($parentObject, $childObject, $data);
        }

        return $objectObject;
    }

    public function createObjectLink(
        Entity\MonarcObject $parentObject,
        Entity\MonarcObject $childObject,
        array $positionData,
        bool $saveInDb
    ): Entity\ObjectObject {
        $objectObject = (new Entity\ObjectObject())
            ->setAnr($parentObject->getAnr())
            ->setParent($parentObject)
            ->setChild($childObject)
            ->setCreator($this->connectedUser->getEmail());

        $this->updatePositions($objectObject, $this->objectObjectTable, $positionData);

        $this->objectObjectTable->save($objectObject, $saveInDb);

        return $objectObject;
    }

    public function shiftPositionInComposition(Entity\Anr $anr, int $id, array $data): void
    {
        /** @var Entity\ObjectObject $objectObject */
        $objectObject = $this->objectObjectTable->findByIdAndAnr($id, $anr);

        /* Validate if the position is within the bounds of shift. */
        if (($data['move'] === static::MOVE_COMPOSITION_POSITION_UP && $objectObject->getPosition() <= 1)
            || (
                $data['move'] === static::MOVE_COMPOSITION_POSITION_DOWN
                && $objectObject->getPosition() >= $this->objectObjectTable->findMaxPosition(
                    $objectObject->getImplicitPositionRelationsValues()
                )
            )
        ) {
            return;
        }

        $positionToBeSet = $data['move'] === static::MOVE_COMPOSITION_POSITION_UP
            ? $objectObject->getPosition() - 1
            : $objectObject->getPosition() + 1;
        /** @var Entity\MonarcObject $parentObject */
        $parentObject = $objectObject->getParent();
        $previousObjectCompositionLink = $this->objectObjectTable->findByParentObjectAndPosition(
            $parentObject,
            $positionToBeSet
        );
        /* Some positions are not aligned in the DB, that's why we may have empty result. */
        if ($previousObjectCompositionLink !== null) {
            $this->objectObjectTable->save(
                $previousObjectCompositionLink->setPosition($objectObject->getPosition())->setUpdater(
                    $this->connectedUser->getEmail()
                ),
                false
            );
        }
        $this->objectObjectTable->save(
            $objectObject->setPosition($positionToBeSet)->setUpdater($this->connectedUser->getEmail())
        );
    }

    public function delete(Entity\Anr $anr, int $id): void
    {
        /** @var Entity\ObjectObject $objectObject */
        $objectObject = $this->objectObjectTable->findByIdAndAnr($id, $anr);

        /* Unlink the related instances of the compositions. */
        foreach ($objectObject->getChild()->getInstances() as $childObjectInstance) {
            foreach ($objectObject->getParent()->getInstances() as $parentObjectInstance) {
                if ($childObjectInstance->hasParent()
                    && $childObjectInstance->getParent()?->getId() === $parentObjectInstance->getId()
                ) {
                    $childObjectInstance->setParent(null);
                    $childObjectInstance->setRoot(null);
                    $this->instanceTable->remove($childObjectInstance, false);
                }
            }
        }

        /* Shift positions to fill in the gap of the object being removed. */
        $this->shiftPositionsForRemovingEntity($objectObject, $this->objectObjectTable);

        $this->objectObjectTable->remove($objectObject);
    }

    /**
     * New instance is created when the composition parent object is presented in the analysis.
     */
    private function createInstances(
        Entity\MonarcObject $parentObject,
        Entity\MonarcObject $childObject,
        array $data
    ): void {
        $previousObjectCompositionLink = null;
        if ($data['implicitPosition'] === PositionUpdatableServiceInterface::IMPLICIT_POSITION_AFTER) {
            /** @var Entity\ObjectObject $previousObjectCompositionLink */
            $previousObjectCompositionLink = $this->objectObjectTable->findByIdAndAnr(
                $data['previous'],
                $parentObject->getAnr()
            );
        }
        foreach ($parentObject->getInstances() as $parentObjectInstance) {
            $instanceData = [
                'object' => $childObject,
                'parent' => $parentObjectInstance,
                'implicitPosition' => $data['implicitPosition'],
            ];
            if ($previousObjectCompositionLink !== null) {
                foreach ($previousObjectCompositionLink->getChild()->getInstances() as $previousObjectInstance) {
                    if ($previousObjectInstance->hasParent()
                        && $previousObjectInstance->getParent()?->getId() === $parentObjectInstance->getId()
                    ) {
                        $instanceData['previous'] = $previousObjectInstance->getId();
                    }
                }
            }

            /** @var Entity\Anr $anr */
            $anr = $parentObjectInstance->getAnr();
            $this->anrInstanceService->instantiateObjectToAnr($anr, $instanceData);
        }
    }

    private function validateIfObjectOrItsChildrenLinkedToOneOfParents(
        Entity\MonarcObject $childObject,
        Entity\MonarcObject $parentObject
    ): void {
        if ($parentObject->isObjectOneOfParents($childObject)) {
            throw new Exception('It\'s not allowed to make a composition with circular dependency.', 412);
        }

        foreach ($childObject->getChildren() as $childOfChildObject) {
            $this->validateIfObjectOrItsChildrenLinkedToOneOfParents($childOfChildObject, $parentObject);
        }
    }
}
