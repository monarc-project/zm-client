<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord International Transfer Service
 *
 * Class AnrRecordInternationalTransferService
 * @package MonarcFO\Service
 */
class AnrRecordInternationalTransferService extends AbstractService
{
    protected $dependencies = ['anr', 'record', 'processor'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $anrTable;

    /**
    * Generates the array to be exported into a file when calling {#exportInternationalTransfers}
    * @see #exportInternationalTransfers
    * @param int $id The international transfer's id
    * @return array The data array that should be saved
    * @throws \MonarcCore\Exception\Exception If the international transfer is not found
    */
    public function generateExportArray($id)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \MonarcCore\Exception\Exception('Entity `id` not found.');
        }
        $return = [];
        if($entity->organisation != "") {
            $return["organisation"] = $entity->organisation;
        }
        if($entity->description != "") {
            $return["description"] = $entity->description;
        }
        if($entity->country != "") {
            $return["country"] = $entity->country;
        }

        if($entity->documents) {
            $return["documents"] = $entity->documents;
        }
        return $return;
    }

    /**
     * Imports a record international transfer from a data array. This data is generally what has been exported into a file.
     * @param array $data The record data subject's data fields
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR id
     * @param int $parentId The id of the entity possessing this international transfer
     * @param bool $forRecord True if the entity possessing this international transfer is a record, false if processor
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr, $parentId, $forRecord)
    {
        $data['anr'] = $anr;
        if($forRecord) {
            $data['record'] = $parentId;
        } else {
            $data['processor'] = $parentId;
        }
        $id = $this->create($data);
        return $id;
    }
}
