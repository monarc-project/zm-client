<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Processor Service
 *
 * Class AnrRecordProcessorService
 * @package MonarcFO\Service
 */
class AnrRecordProcessorService extends AbstractService
{
    protected $dependencies = ['anr','representative', 'dpo'];
    protected $recordActorService;
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $anrTable;
    protected $recordTable;

    public function deleteProcessor($id)
    {
        $entity = $this->get('table')->getEntity($id);
        $anrId = $entity->anr->id;
        $actorsToCheck = array();
        if($entity->dpo) {
            array_push($actorsToCheck, $entity->dpo->id);
        }
        if($entity->representative) {
            array_push($actorsToCheck, $entity->representative->id);
        }
        $result = $this->get('table')->delete($id);
        foreach($actorsToCheck as $a) {
            if($this->recordActorService->orphanActor($a, $anrId)) {
                $this->recordActorService->delete(['anr'=> $anrId, 'id' => $a]);
            }
        }

        return $result;
    }
    /**
     * Updates a processor of processing activity
     * @param array $data The processor details fields
     * @return object The resulting created processor object (entity)
     */
    public function updateProcessor($id, $data)
    {
        $entity = $this->get('table')->getEntity($id);
        if(isset($data['dpo']['id'])) {
            $data['dpo'] = $data['dpo']['id'];
        } else {
            $data['dpo'] = null;
        }
        if(isset($data['representative']['id'])) {
            $data['representative'] = $data['representative']['id'];
        } else {
            $data['representative'] = null;
        }

        $oldActors = array();
        if($entity->representative && $entity->representative->id) {
            array_push($oldActors, $entity->representative->id);
        }
        if($entity->dpo && $entity->dpo->id) {
            array_push($oldActors, $entity->dpo->id);
        }

        $result = $this->update($id, $data);
        foreach($oldActors as $a) {
            if($a != $data['dpo'] && $a != $data['representative']
               && $this->recordActorService->orphanActor($a, $data['anr'])) {
                $this->recordActorService->delete(['anr'=> $data['anr'], 'id' => $a]);
            }
        }
        return $result;
    }

    /**
    * Generates the array to be exported into a file when calling {#exportProcessor}
    * @see #exportProcessor
    * @param int $id The processor's id
    * @param string $filename The output filename
    * @return array The data array that should be saved
    * @throws \MonarcCore\Exception\Exception If the processor is not found
    */
    public function generateExportArray($id)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \MonarcCore\Exception\Exception('Entity `id` not found.');
        }
        $return = [
            'name' => $entity->label,
        ];
        if($entity->secMeasures != '') {
            $return['security_measures'] = $entity->secMeasures;
        }
        if($entity->contact != '') {
            $return['contact'] = $entity->contact;
        }
        if($entity->activities != '') {
            $return['activities'] = $entity->activities;
        }
        if($entity->representative) {
            $return['representative'] = $this->recordActorService->generateExportArray($entity->representative->id);
        }
        if($entity->dpo) {
            $return['data_protection_officer'] = $this->recordActorService->generateExportArray($entity->dpo->id);
        }
        return $return;
    }

    public function orphanProcessor($processorId, $anrId) {
        $records = $this->recordTable->getEntityByFields(['processors' => $processorId, 'anr' => $anrId]);
        if(count($records) > 0) {
            return false;
        }
        return true;
    }

    /**
     * Imports a record processor from a data array. This data is generally what has been exported into a file.
     * @param array $data The processor's data fields
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr)
    {
        $newData = []; //new data to be updated
        $newData['anr'] = $anr;
        $newData['label'] = $data['name'];
        $newData['contact'] = (isset($data['contact']) ? $data['contact'] : '');
        unset($data['name']);
        try {
            $processorEntity = $this->get('table')->getEntityByFields(['label' => $newData['label'], 'contact' => $newData['contact'], 'anr' => $anr]);
            if (count($processorEntity)) {
                $id = $processorEntity[0]->get('id');
            } else {
                $id = $this->create($newData);
            }
        } catch (\MonarcCore\Exception\Exception $e) {
            $id = $this->create($newData);
        }
        $newData['activities'] = (isset($data['activities']) ? $data['activities'] : '');
        $newData['secMeasures'] = (isset($data['security_measures']) ? $data['security_measures'] : '');
        if(isset($data['representative'])) {
            $newData['representative']["id"] = $this->recordActorService->importFromArray($data['representative'], $anr);
        }
        if(isset($data['data_protection_officer'])) {
            $newData['dpo']["id"] = $this->recordActorService->importFromArray($data['data_protection_officer'], $anr);
        }
        return $this->updateProcessor($id,$newData);
    }
}
