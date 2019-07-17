<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Recipient Service
 *
 * Class AnrRecordRecipientService
 * @package MonarcFO\Service
 */
class AnrRecordRecipientService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $anrTable;
    protected $recordTable;

    public function orphanRecipient($recipientId, $anrId) {
        $records = $this->recordTable->getEntityByFields(['recipients' => $recipientId, 'anr' => $anrId]);
        if(count($records) > 0) {
            return false;
        }
        return true;
    }
    /**
    * Generates the array to be exported into a file when calling {#exportRecipient}
    * @see #exportRecipient
    * @param int $id The recipient's id
    * @return array The data array that should be saved
    * @throws \MonarcCore\Exception\Exception If the recipient is not found
    */
    public function generateExportArray($id)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \MonarcCore\Exception\Exception('Entity `id` not found.');
        }

        $return = [];
        $return['id'] = $entity->id;
        $return['name'] = $entity->label;
        if ($entity->type == 0) {
            $return["type"] = "internal";
        } else {
            $return["type"] = "external";
        }
        if($entity->description != "") {
            $return['description'] = $entity->description;
        }
        return $return;
    }
    /**
     * Imports a record recipient from a data array. This data is generally what has been exported into a file.
     * @param array $data The record recipient's data fields
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr)
    {
        $data['anr'] = $anr;
        $data['label'] = $data['name'];
        if ($data['type'] == "internal") {
            $data["type"] = 0;
        } else {
            $data["type"] = 1;
        }
        if(!isset($data['id'])){
            $data['id'] = -1;
        }
        $id = $data['id'];
        unset($data['name']);
        try {
            $recipientEntity = $this->get('table')->getEntity($data['id']);
            if ($recipientEntity->get('anr')->get('id') != $anr || $recipientEntity->get('label') != $data['label'] || $recipientEntity->get('description') != $data['description']) {
                unset($data['id']);
                $id = $this->create($data);
            }
        } catch (\MonarcCore\Exception\Exception $e) {
            unset($data['id']);
            $id = $this->create($data);
        }
        return $id;
    }
}
