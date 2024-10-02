<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
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
    * @throws Exception If the recipient is not found
    */
    public function generateExportArray($id)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new Exception('Entity `id` not found.');
        }

        $return = [];
        $return['name'] = $entity->label;
        $return['type'] = $entity->type === 0 ? 'internal' : 'external';
        if (empty($entity->description)) {
            $return['description'] = $entity->description;
        }

        return $return;
    }
    /**
     * Imports a record recipient from a data array. This data is generally what has been exported into a file.
     * @param array $data The record recipient's data fields
     * @param \Monarc\FrontOffice\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr)
    {
        $data['anr'] = $anr;
        $data['label'] = $data['name'];
        unset($data['name']);
        $data['type'] = $data['type'] === 'internal' ? 0 : 1;
        $data['description'] = $data['description'] ?? '';
        try {
            $recipientEntity = $this->get('table')->getEntityByFields([
                'label' => $data['label'],
                'type' => $data['type'],
                'description' => $data['description'],
                'anr' => $anr
            ]);
            if (count($recipientEntity)) {
                $id = $recipientEntity[0]->getId();
            } else {
                $id = $this->create($data);
            }
        } catch (Exception $e) {
            $id = $this->create($data);
        }

        return $id;
    }
}
