<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;

/**
 * AnrRecord Actor Service
 *
 * Class AnrRecordActorService
 * @package Monarc\FrontOffice\Service
 */
class AnrRecordActorService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $recordTable;
    protected $processorTable;

    public function orphanActor($actorId, $anrId) {
        $records = $this->recordTable->getEntityByFields(['controller' => $actorId, 'anr' => $anrId]);
        if(count($records)> 0) {
            return false;
        }
        $records = $this->recordTable->getEntityByFields(['dpo' => $actorId, 'anr' => $anrId]);
        if(count($records)> 0) {
            return false;
        }
        $records = $this->recordTable->getEntityByFields(['representative' => $actorId, 'anr' => $anrId]);
        if(count($records)> 0) {
            return false;
        }
        $records = $this->recordTable->getEntityByFields(['jointControllers' => $actorId, 'anr' => $anrId]);
        if(count($records)> 0) {
            return false;
        }
        $processors = $this->processorTable->getEntityByFields(['representative' => $actorId, 'anr' => $anrId]);
        if(count($processors)> 0) {
            return false;
        }
        $processors = $this->processorTable->getEntityByFields(['dpo' => $actorId, 'anr' => $anrId]);
        if(count($processors)> 0) {
            return false;
        }
        return true;
    }

    /**
    * Generates the array to be exported into a file when calling {#exportActor}
    * @see #exportActor
    * @param int $id The actor's id
    * @return array The data array that should be saved
    * @throws \Monarc\Core\Exception\Exception If the actor is not found
    */
    public function generateExportArray($id)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \Monarc\Core\Exception\Exception('Entity `id` not found.');
        }
        $return['name'] = $entity->label;
        if($entity->contact != "") {
            $return['contact'] = $entity->contact;
        }
        return $return;
    }

    /**
     * Imports a record actor from a data array. This data is generally what has been exported into a file.
     * @param array $data The record actor's data fields
     * @param \Monarc\FrontOffice\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr)
    {
        $data['anr'] = $anr;
        $data['label'] = $data['name'];
        $data['contact'] = (isset($data['contact']) ? $data['contact'] : '');
        unset($data['name']);
        try {
            $actorEntity = $this->get('table')->getEntityByFields(['label' => $data['label'], 'anr' => $anr]);
            if (count($actorEntity)) {
                $id = $actorEntity[0]->get('id');
            } else {
                $id = $this->create($data);
            }
        } catch (\Monarc\Core\Exception\Exception $e) {
            $id = $this->create($data);
        }
        return $id;
    }
}
