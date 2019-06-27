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
    protected $dependencies = ['anr','representative', 'dpo', 'cascadedProcessors', 'internationalTransfers'];
    protected $recordActorService;
    protected $recordInternationalTransferService;
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
        foreach($entity->cascadedProcessors as $cp) {
            array_push($actorsToCheck, $cp->id);
        }

        foreach($entity->internationalTransfers as $it) {
            $this->recordInternationalTransferService->delete(['anr'=> $anrId, 'id' => $it->id]);
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
        $cascadedProcessors = array();
        foreach ($data['cascadedProcessors'] as $cp) {
            array_push($cascadedProcessors, $cp['id']);
        }
        $data['cascadedProcessors'] = $cascadedProcessors;
        $internationalTransfers = array();
        foreach ($data['internationalTransfers'] as $it) {
            array_push($internationalTransfers,$it['id']);
        }
        $data['internationalTransfers'] = $internationalTransfers;

        $oldActors = array();
        if($entity->representative && $entity->representative->id) {
            array_push($oldActors, $entity->representative->id);
        }
        if($entity->dpo && $entity->dpo->id) {
            array_push($oldActors, $entity->dpo->id);
        }
        foreach($entity->cascadedProcessors as $cp) {
            array_push($oldActors, $cp->id);
        }

        foreach($entity->internationalTransfers as $it) {
            if(!in_array($it->id, $internationalTransfers)) {
                $this->recordInternationalTransferService->delete(['anr'=> $data['anr'], 'id' => $it->id]);
            }
        }

        $result = $this->update($id, $data);
        foreach($oldActors as $a) {
            if(!in_array($a, $cascadedProcessors) && $a != $data['dpo'] && $a != $data['representative']
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
            'id' => $entity->id,
            'name' => $entity->label,
        ];
        if($entity->secMeasures != '') {
            $return['security_measures'] = $entity->secMeasures;
        }
        if($entity->activities) {
            $return['activities'] = $entity->activities;
        }
        if($entity->representative) {
            $return['representative'] = $this->recordActorService->generateExportArray($entity->representative->id);
        }
        if($entity->dpo) {
            $return['data_protection_officero'] = $this->recordActorService->generateExportArray($entity->dpo->id);
        }
        foreach ($entity->cascadedProcessors as $cp) {
            $return['cascaded_processors'][] = $this->recordActorService->generateExportArray($cp->id);
        }
        foreach ($entity->internationalTransfers as $it) {
            $return['international_transfers'][] = $this->recordInternationalTransferService->generateExportArray($it->id);
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
        $id = $data['id'];
        try {
            $processorEntity = $this->get('table')->getEntity($data['id']);
            if ($processorEntity->get('anr')->get('id') != $anr || $processorEntity->get('label') != $data['name']) {
                unset($data['id']);
                $id = $this->create($newData);
            }
            else {
                foreach($processorEntity->get('cascadedProcessors') as $cp) {
                    $newData['cascadedProcessors'][] = $cp->get('id');
                }
                foreach($processorEntity->get('personalData') as $pd) {
                    $newData['personalData'][] = $pd->get('id');
                }
                foreach($processorEntity->get('internationalTransfers') as $it) {
                    $newData['internationalTransfers'][] = $it->get('id');
                }
            }
        } catch (\MonarcCore\Exception\Exception $e) {
            unset($data['id']);
            $id = $this->create($newData);
        }
        $newData['activities'] = (isset($data['activities']) ? $data['activities'] : []);
        $newData['secMeasures'] = (isset($data['security_measures']) ? $data['security_measures'] : '');
        if(isset($data['representative'])) {
            $newData['representative']["id"] = $this->recordActorService->importFromArray($data['representative'], $anr);
        }
        if(isset($data['data_protection_officer'])) {
            $newData['dpo']["id"] = $this->recordActorService->importFromArray($data['data_protection_officer'], $anr);
        }
        if(isset($data['cascaded_processors'])) {
            foreach ($data['cascaded_processors'] as $jc) {
                $jointController = [];
                $jointController['id'] = $this->recordActorService->importFromArray($jc, $anr);
                $newData['cascadedProcessors'][] = $jointController;
            }
        }
        if(isset($data['personal_data'])) {
            foreach ($data['personal_data'] as $pd) {
                $personalData = [];
                $personalData['id'] = $this->recordPersonalDataService->importFromArray($pd, $anr);
                $newData['personalData'][] = $personalData;
            }
        }
        if(isset($data['international_transfers'])) {
            foreach ($data['international_transfers'] as $it) {
                $internationalTransfers = [];
                $internationalTransfers['id'] = $this->recordInternationalTransferService->importFromArray($it, $anr, $id, false);
                $newData['internationalTransfers'][] = $internationalTransfers;
            }
        }
        return $this->updateProcessor($id,$newData);
    }
}
