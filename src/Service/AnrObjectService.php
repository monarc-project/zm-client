<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\NonUniqueResultException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Import\Service\ObjectImportService;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\MonarcObjectTable;
use Monarc\FrontOffice\Service\Export\ObjectExportService;

class AnrObjectService
{
    protected $selfCoreService;
    protected $userAnrTable;
    protected $objectImportService;

    private MonarcObjectTable $monarcObjectTable;

    public function __construct(MonarcObjectTable $monarcObjectTable)
    {
        $this->monarcObjectTable = $monarcObjectTable;
    }

    public function getObjectData(Anr $anr, string $uuid): array
    {
        // todo...
    }

    public function create(Anr $anr, array $data, bool $saveInDb = true): MonarcObject
    {
        $monarcObject = (new MonarcObject())
            ->setAnr($anr);
        // TODO: The $data['mode'] can't be modified. always generic.

        if (isset($data['uuid'])) {
            $monarcObject->setUuid($data['uuid']);
        }

        // TODO: import from MOSP.
//        if (!empty($data['mosp'])) {
//            $monarcObject = $this->importFromMosp($data, $anr);
//
//            return $monarcObject ? $monarcObject->getUuid() : null;
//        }

        // TODO: we should link it. There is a separate call to link.
        // $this->attachObjectToAnr($monarcObject, $anr, null, null, $context);

        $this->monarcObjectTable->save($monarcObject);

        return $monarcObject;
    }

    public function update(Anr $anr, string $uuid, array $data): MonarcObject
    {
        $monarcObject = $this->monarcObjectTable->findByUuidAndAnr($uuid, $anr);
        // TODO: The $data['mode'] can't be modified. always generic.

        $this->monarcObjectTable->save($monarcObject);

        // TODO: on BO we call instancesImpacts in update and patch (renamed to updateInstancesAndOperationalRisks)

        // TODO: when process object cat, we need to check for $monarcObject->getAnr(), if the root cat has link to anr.

        return $monarcObject;
    }

    public function duplicate(Anr $anr, array $data): MonarcObject
    {

    }

    public function attachObjectToAnr(
        string $objectUuid,
        AnrSuperClass $anr,
        $parent = null,
        $objectObjectPosition = null
    ): MonarcObject {
        /** @var MonarcObject $monarcObject */
        $monarcObject = $this->monarcObjectTable->findByUuidAndAnr($objectUuid, $anr);


    }

    public function detachObjectFromAnr(string $objectUuid, Anr $anr): void
    {
        /** @var MonarcObject $monarcObject */
        $monarcObject = $this->monarcObjectTable->findByUuidAndAnr($objectUuid, $anr);

        $monarcObject->removeAnr($anr);

        foreach ($monarcObject->getParents() as $objectParent) {
            $parentInstancesIds = [];
            foreach ($objectParent->getInstances() as $parentInstance) {
                $parentInstancesIds[] = $parentInstance->getId();
            }

            foreach ($monarcObject->getInstances() as $currentObjectInstance) {
                if ($currentObjectInstance->hasParent()
                    && \in_array($currentObjectInstance->getParent()->getId(), $parentInstancesIds, true)
                ) {
                    $this->instanceTable->deleteEntity($currentObjectInstance);
                }
            }

            // Removes from the library object composition of the anr.
            $objectParent->removeChild($monarcObject);
            $this->monarcObjectTable->save($objectParent, false);
        }

        /* If no more objects under its root category, the category need to be unlinked from the analysis. */
        if ($monarcObject->hasCategory()
            && !$this->monarcObjectTable->hasObjectsUnderRootCategoryExcludeObject(
                $monarcObject->getCategory()->getRootCategory(),
                $monarcObject
            )
        ) {
            $rootCategory = $monarcObject->getCategory()->getRootCategory();
            $this->objectCategoryTable->save($rootCategory->removeAnrLink($anr), false);
        }

        foreach ($monarcObject->getInstances() as $instance) {
            $this->instanceTable->deleteEntity($instance, false);
        }

        $this->monarcObjectTable->save($monarcObject);
    }

    public function getParentsInAnr(Anr $anr, string $uuid)
    {
        $object = $this->monarcObjectTable->findByUuidAndAnr($uuid, $anr);

        $directParents = [];
        foreach ($object->getParentsLinks() as $parentLink) {
            $directParents = [
                'uuid' => $parentLink->getParent()->getUuid(),
                'linkid' => $parentLink->getId(),
                'label1' => $parentLink->getParent()->getLabel(1),
                'label2' => $parentLink->getParent()->getLabel(2),
                'label3' => $parentLink->getParent()->getLabel(3),
                'label4' => $parentLink->getParent()->getLabel(4),
                'name1' => $parentLink->getParent()->getName(1),
                'name2' => $parentLink->getParent()->getName(2),
                'name3' => $parentLink->getParent()->getName(3),
                'name4' => $parentLink->getParent()->getName(4),
            ];
        }

        return $directParents;
    }


    // TODO: move to the export service and refactor.
    public function export(&$data)
    {
        if (empty($data['id'])) {
            throw new Exception('Object to export is required', 412);
        }

        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($data['anr']);

        /** @var ObjectExportService $objectExportService */
        $objectExportService = $this->get('objectExportService');

        $isForMosp = !empty($data['mosp']);

        $prepareObjectData = json_encode($isForMosp
            ? $objectExportService->generateExportMospArray($data['id'], $anr)
            : $objectExportService->generateExportArray($data['id'], $anr));

        $data['filename'] = $objectExportService->generateExportFileName($data['id'], $anr, $isForMosp);

        if (!empty($data['password'])) {
            $prepareObjectData = $this->encrypt($prepareObjectData, $data['password']);
        }

        return $prepareObjectData;
    }
}
