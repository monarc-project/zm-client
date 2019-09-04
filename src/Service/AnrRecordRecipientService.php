<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;

/**
 * AnrRecord Recipient Service
 *
 * Class AnrRecordRecipientService
 * @package Monarc\FrontOffice\Service
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
    * @throws \Monarc\Core\Exception\Exception If the recipient is not found
    */
    public function generateExportArray($id)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \Monarc\Core\Exception\Exception('Entity `id` not found.');
        }

        $return = [];
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
     * @param \Monarc\FrontOffice\Model\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr)
    {
        $data['anr'] = $anr;
        $data['label'] = $data['name'];
        unset($data['name']);
        if ($data['type'] == "internal") {
            $data['type'] = 0;
        } else {
            $data['type'] = 1;
        }
        $id = $data['id'];
        $data['description'] = (isset($data['description']) ? $data['description'] : '');
        try {
            $recipientEntity = $this->get('table')->getEntityByFields(['label' => $data['label'], 'type' => $data['type'], 'description' => $data['description'], 'anr' => $anr]);
            if (count($recipientEntity)) {
                $id = $recipientEntity[0]->get('id');
            } else {
                $id = $this->create($data);
            }
        } catch (\Monarc\Core\Exception\Exception $e) {
            $id = $this->create($data);
        }
        return $id;
    }
}
