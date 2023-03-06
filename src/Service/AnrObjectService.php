<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\NonUniqueResultException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Helper\EncryptDecryptHelperTrait;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Import\Service\ObjectImportService;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;

// TODO: don't forget to remove its factory class.
class AnrObjectService
{
    use EncryptDecryptHelperTrait;

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
        $monarcObject = $this->monarcObjectTable->findByAnrAndUuid($anr, $uuid);
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
        $monarcObject = $this->monarcObjectTable->findByAnrAndUuid($anr, $objectUuid);


    }

    public function detachObjectFromAnr(string $objectUuid, Anr $anr): void
    {
        /** @var MonarcObject $monarcObject */
        $monarcObject = $this->monarcObjectTable->findByAnrAndUuid($anr, $objectUuid);

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
        $object = $this->monarcObjectTable->findByAnrAndUuid($anr, $uuid);

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

    // TODO: move all the import functionality to the ObjectImportService.
    /**
     * Imports a previously exported object from an uploaded file into the current ANR. It may be imported using two
     * different modes: 'merge', which will update the existing objects using the file's data, or 'duplicate' which
     * will create a new object using the data.
     *
     * @param int $anrId The ANR ID
     * @param array $data The data that has been posted to the API
     *
     * @return array An array where the first key is the generated IDs, and the second are import errors
     * @throws Exception If the uploaded data is invalid, or the ANR invalid
     */
    public function importFromFile($anrId, $data)
    {
        // Mode may either be 'merge' or 'duplicate'
        $mode = empty($data['mode']) ? 'merge' : $data['mode'];

        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($data['file'])) {
            throw new Exception('File missing', 412);
        }

        $ids = [];
        $errors = [];
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anr = $anrTable->findById($anrId);

        foreach ($data['file'] as $f) {
            // Ensure the file has been uploaded properly, silently skip the files that are erroneous
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])) {
                if (empty($data['password'])) {
                    $file = json_decode(trim(file_get_contents($f['tmp_name'])), true);
                    if ($file === false) { // support legacy export which were base64 encoded
                        $file = json_decode(
                            trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), '')),
                            true
                        );
                    }
                } else {
                    // Decrypt the file and store the JSON data as an array in memory
                    $key = $data['password'];
                    $file = json_decode(trim($this->decrypt(file_get_contents($f['tmp_name']), $key)), true);
                    if ($file === false) { // support legacy export which were base64 encoded
                        $file = json_decode(
                            trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), $key)),
                            true
                        );
                    }
                }

                /** @var ObjectImportService $objectImportService */
                $objectImportService = $this->get('objectImportService');
                $monarcObject = null;
                if ($file !== false) {
                    $monarcObject = $objectImportService->importFromArray($file, $anr, $mode);
                    if ($monarcObject !== null) {
                        $ids[] = $monarcObject->getUuid();
                    }
                }
                if ($monarcObject === null) {
                    $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                }
            }
        }

        return [$ids, $errors];
    }

    /**
     * Fetches and returns the list of objects from the common database. This will only return a limited set of fields,
     * use {#getCommonEntity} to get the entire object definition.
     * @see #getCommonEntity
     *
     * @param int $anrId The target ANR ID, $filter Keywords to search
     *
     * @return array An array of available objects from the common database (knowledge base)
     * @throws Exception If the ANR ID is not set or invalid
     */
    public function getCommonObjects($anrId, $filter = null)
    {
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $anr = $this->get('anrTable')->getEntity($anrId); // throws an Monarc\Core\Exception\Exception if unknown

        // Fetch the objects from the common database

        // TODO: the method doesn't exist anymore, we need to use getListSpecific or create a new custom one.
        // 'name' . $anr->get('language') - order by, $filter - search by name, label, $anr->getModel() - we get by the id and it will exclude all the attached to the model objects.

        $objects = $this->get('selfCoreService')->getAnrObjects(
            1,
            -1,
            'name' . $anr->get('language'),
            $filter,
            null,
            $anr->getModel(),
            null,
            AbstractEntity::FRONT_OFFICE
        );

        // List of fields we want to keep
        $fields = [
            'uuid',
            'mode',
            'scope',
            'name' . $anr->get('language'),
            'label' . $anr->get('language'),
            'position',
        ];
        $fields = array_combine($fields, $fields);

        foreach ($objects as $k => $o) {
            // Filter out the fields we don't want
            foreach ($o as $k2 => $v2) {
                if (!isset($fields[$k2])) {
                    unset($objects[$k][$k2]);
                }
            }

            // Fetch the category details, if one is set
            if ($o['category']) {
                $objects[$k]['category'] = $o['category']->getJsonArray([
                    'id',
                    'root',
                    'parent',
                    'label' . $anr->get('language'),
                    'position',
                ]);
            }

            // Append the object to our array
            $objects[$k]['asset'] = $o['asset']->getJsonArray([
                'uuid',
                'label' . $anr->get('language'),
                'description' . $anr->get('language'),
                'mode',
                'type',
                'status',
            ]);
        }

        return $objects;
    }

    /**
     * Fetches and returns the details of a specific object from the common database.
     *
     * @param int $anrId The target ANR ID
     * @param int $id The common object ID
     *
     * @return Object The fetched object
     * @throws Exception If the ANR is invalid, or the object ID is not found
     */
    public function getCommonEntity($anrId, $id)
    {
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $anr = $this->get('anrTable')->getEntity($anrId); // on a une erreur si inconnue

        // TODO: the method doesn't exist anymore we have to replace to simply find by UUID with use core table.

        $object = current($this->get('selfCoreService')->getAnrObjects(
            1,
            -1,
            'name' . $anr->get('language'),
            [],
            ['uuid' => $id],
            $anr->getModel(),
            null,
            AbstractEntity::FRONT_OFFICE
        ));
        if (empty($object)) {
            throw new Exception('Object not found', 412);
        }

        return $this->get('selfCoreService')->getObjectData($id);
    }

    /**
     * Imports an object from the common database into the specified ANR. The ANR id must be set in $data['anr'].
     *
     * @param int $id The common object ID
     * @param array $data An array with ['anr' => 'The anr id', 'mode' => 'merge or duplicate']
     *
     * @throws Exception If the ANR is invalid, or the object ID is not found
     */
    public function importFromCommon($id, $data): ?MonarcObject
    {
        if (empty($data['anr'])) {
            throw new Exception('Anr id missing', 412);
        }
        $anr = $this->get('anrTable')->getEntity($data['anr']); // on a une erreur si inconnue

        // TODO: the method doesn't exist anymore we have to replace to simply find by UUID with use core table.

        $object = current($this->get('selfCoreService')->getAnrObjects(
            1,
            -1,
            'name' . $anr->get('language'),
            [],
            ['uuid' => $id],
            $anr->getModel(),
            null,
            AbstractEntity::FRONT_OFFICE
        ));
        if (empty($object)) {
            throw new Exception('Object not found', 412);
        }

        // Export
        $json = $this->get('selfCoreService')->get('objectExportService')->generateExportArray($id);
        if ($json) {
            /** @var ObjectImportService $objectImportService */
            $objectImportService = $this->get('objectImportService');

            return $objectImportService->importFromArray($json, $anr, isset($data['mode']) ? $data['mode'] : 'merge');
        }

        return null;
    }

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

    /**
     * @param array $data
     * @param AnrSuperClass|null $anr The nullable value possibility is to comply with the core definition.
     *
     * @return ObjectSuperClass|null
     *
     * @throws NonUniqueResultException
     */
    protected function importFromMosp(array $data, ?AnrSuperClass $anr): ?ObjectSuperClass
    {
        if ($anr === null) {
            return null;
        }

        /** @var ObjectImportService $objectImportService */
        $objectImportService = $this->get('objectImportService');

        return $objectImportService->importFromArray($data, $anr, $data['mode'] ?? 'merge');
    }
}
