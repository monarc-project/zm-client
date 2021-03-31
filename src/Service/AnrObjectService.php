<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Table\AnrTable;

/**
 * This class is the service that handles objects in use within an ANR. Inherits its behavior from its Monarc\Core
 * parent class ObjectService
 * @see \Monarc\Core\Service\ObjectService
 * @package Monarc\FrontOffice\Service
 */
class AnrObjectService extends \Monarc\Core\Service\ObjectService
{
    protected $selfCoreService;
    protected $userAnrTable;

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
                        $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), '')), true);
                    }
                } else {
                    // Decrypt the file and store the JSON data as an array in memory
                    $key = $data['password'];
                    $file = json_decode(trim($this->decrypt(file_get_contents($f['tmp_name']), $key)), true);
                    if ($file === false) { // support legacy export which were base64 encoded
                        $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), $key)), true);
                    }
                }

                /** @var ObjectExportService $objectExportService */
                $objectExportService = $this->get('objectExportService');
                $monarcObject = null;
                if ($file !== false) {
                    $monarcObject = $objectExportService->importFromArray($file, $anr, $mode);
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
        $objects = $this->get('selfCoreService')->getAnrObjects(1, -1, 'name' . $anr->get('language'), $filter, null, $anr->get('model'), null, \Monarc\Core\Model\Entity\AbstractEntity::FRONT_OFFICE);

        // List of fields we want to keep
        $fields = [
            'uuid',
            'mode',
            'scope',
            'name' . $anr->get('language'),
            'label' . $anr->get('language'),
            'disponibility',
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
        $object = current($this->get('selfCoreService')->getAnrObjects(1, -1, 'name' . $anr->get('language'), [], ['uuid' => $id], $anr->get('model'), null, \Monarc\Core\Model\Entity\AbstractEntity::FRONT_OFFICE));
        if (!empty($object)) {
            return $this->get('selfCoreService')->getCompleteEntity($id);
        } else {
            throw new Exception('Object not found', 412);
        }
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
        $object = current($this->get('selfCoreService')->getAnrObjects(1, -1, 'name' . $anr->get('language'), [], ['uuid' => $id], $anr->get('model'), null, \Monarc\Core\Model\Entity\AbstractEntity::FRONT_OFFICE));
        if (!empty($object)) {
            // Export
            $json = $this->get('selfCoreService')->get('objectExportService')->generateExportArray($id);
            if ($json) {
                /** @var ObjectExportService $objectExportService */
                $objectExportService = $this->get('objectExportService');

                return $objectExportService->importFromArray($json, $anr, isset($data['mode']) ? $data['mode'] : 'merge');
            }
        } else {
            throw new Exception('Object not found', 412);
        }
    }
}
