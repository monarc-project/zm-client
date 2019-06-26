<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Service
 *
 * Class AnrRecordService
 * @package MonarcFO\Service
 */
class AnrRecordService extends AbstractService
{
    protected $dependencies = [ 'anr', 'controller', 'representative', 'dpo', 'jointControllers',
                                'personalData', 'internationalTransfers', 'processors', 'recipients'];
    protected $recordActorService;
    protected $recordProcessorService;
    protected $recordRecipientService;
    protected $recordPersonalDataService;
    protected $recordInternationalTransferService;
    protected $userAnrTable;
    protected $anrTable;
    protected $actorTable;
    protected $processorTable;
    protected $recipientTable;


    public function deleteRecord($id)
    {
        $entity = $this->get('table')->getEntity($id);
        $anrId = $entity->anr->id;
        $actorsToCheck = array();
        $processorsToCheck = array();
        $recipientsToCheck = array();
        if($entity->controller) {
            array_push($actorsToCheck, $entity->controller->id);
        }
        if($entity->dpo) {
            array_push($actorsToCheck, $entity->dpo->id);
        }
        if($entity->representative) {
            array_push($actorsToCheck, $entity->representative->id);
        }
        foreach($entity->jointControllers as $jc) {
            array_push($actorsToCheck, $jc->id);
        }
        foreach($entity->processors as $p) {
            array_push($processorsToCheck, $p->id);
        }
        foreach($entity->recipients as $r) {
            array_push($recipientsToCheck, $r->id);
        }
        foreach($entity->personalData as $pd) {
            $this->recordPersonalDataService->delete(['anr'=> $anrId, 'id' => $pd->id]);
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
        foreach($processorsToCheck as $p) {
            if($this->recordProcessorService->orphanProcessor($p, $anrId)) {
                $this->recordProcessorService->deleteProcessor($p);
            }
        }
        foreach($recipientsToCheck as $r) {
            if($this->recordRecipientService->orphanRecipient($r, $anrId)) {
                $this->recordRecipientService->delete(['anr'=> $anrId, 'id' => $r]);
            }
        }

        return $result;
    }

    /**
     * Updates a record of processing activity
     * @param array $data The record details fields
     * @return object The resulting created record object (entity)
     */
    public function updateRecord($id, $data)
    {
        $entity = $this->get('table')->getEntity($id);
        if(isset($data['controller']['id'])) {
            $data['controller'] = $data['controller']['id'];
        } else {
            $data['controller'] = null;
        }
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
        $jointControllers = array();
        foreach ($data['jointControllers'] as $jc) {
            array_push($jointControllers, $jc['id']);
        }
        $data['jointControllers'] = $jointControllers;
        $personalData = array();
        foreach ($data['personalData'] as $pd) {
            array_push($personalData,$pd['id']);
        }
        $data['personalData'] = $personalData;
        $recipients = array();
        foreach ($data['recipients'] as $recipient) {
            array_push($recipients,$recipient['id']);
        }
        $data['recipients'] = $recipients;
        $internationalTransfers = array();
        foreach ($data['internationalTransfers'] as $it) {
            array_push($internationalTransfers,$it['id']);
        }
        $data['internationalTransfers'] = $internationalTransfers;
        $processors = array();
        foreach ($data['processors'] as $processor) {
            array_push($processors,$processor['id']);
        }
        $data['processors'] = $processors;


        // keep entities on old object to delete orphans
        $oldActors = array();
        if($entity->controller && $entity->controller->id) {
            array_push($oldActors, $entity->controller->id);
        }
        if($entity->representative && $entity->representative->id) {
            array_push($oldActors, $entity->representative->id);
        }
        if($entity->dpo && $entity->dpo->id) {
            array_push($oldActors, $entity->dpo->id);
        }
        foreach($entity->jointControllers as $js) {
            array_push($oldActors, $js->id);
        }
        $oldRecipients = array();
        foreach( $entity->recipients as $r) {
            array_push($oldRecipients, $r->id);
        }
        $oldProcessors = array();
        foreach( $entity->processors as $p) {
            array_push($oldProcessors, $p->id);
        }

        foreach($entity->personalData as $pd) {
            if(!in_array($pd->id, $personalData)) {
                $this->recordPersonalDataService->deletePersonalData(['anr'=> $data['anr'], 'id' => $pd->id]);
            }
        }
        foreach($entity->internationalTransfers as $it) {
            if(!in_array($it->id, $internationalTransfers)) {
                $this->recordInternationalTransferService->delete(['anr'=> $data['anr'], 'id' => $it->id]);
            }
        }

        $result = $this->update($id, $data);

        foreach($oldActors as $a) {
            if(!in_array($a, $jointControllers) && $a != $data['controller'] && $a != $data['dpo'] && $a != $data['representative']
               && $this->recordActorService->orphanActor($a, $data['anr'])) {
                $this->recordActorService->delete(['anr'=> $data['anr'], 'id' => $a]);
            }
        }
        foreach($oldRecipients as $rc) {
            if(!in_array($rc, $recipients) && $this->recordRecipientService->orphanRecipient($rc, $data['anr'])) {
                $this->recordRecipientService->delete(['anr'=> $data['anr'], 'id' => $rc]);
            }
        }
        foreach($oldProcessors as $processor) {
            if(!in_array($processor, $processors) && $this->recordProcessorService->orphanProcessor($processor, $id, $data['anr'])) {
                $this->recordProcessorService->deleteProcessor($processor);
            }
        }
        return $result;
    }

    /**
    * Exports a Record of processing activities, optionaly encrypted, for later re-import
    * @param array $data An array with the Record 'id' and 'password' for encryption
    * @return string JSON file, optionally encrypted
    */
    public function export(&$data)
    {
        $filename = "";
        $exportedRecord = json_encode($this->generateExportArray($data['id'], $filename));
        $data['filename'] = $filename;

        if (! empty($data['password'])) {
            $exportedRecord = $this->encrypt($exportedRecord, $data['password']);
        }

        return $exportedRecord;
    }

    public function exportAll($data) {
        $recordEntities = $this->get('table')->getEntityByFields(['anr' => $data["anr"]]);
        $exportedRecords = [];
        foreach($recordEntities as $entity) {
            $exportedRecords[] = $this->generateExportArray($entity->get("id"));
        }
        $exportedRecords = json_encode($exportedRecords);
        if (! empty($data['password'])) {
            $exportedRecords = $this->encrypt($exportedRecords, $data['password']);
        }

        return $exportedRecords;
    }

    /**
    * Generates the array to be exported into a file when calling {#exportRecord}
    * @see #exportRecord
    * @param int $id The record's id
    * @param string $filename The output filename
    * @return array The data array that should be saved
    * @throws \MonarcCore\Exception\Exception If the record is not found
    */
    public function generateExportArray($id, &$filename = "")
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \MonarcCore\Exception\Exception('Entity `id` not found.');
        }

        $filename = preg_replace("/[^a-z0-9\._-]+/i", '', $entity->get('label' . $this->getLanguage()));

        $return = [
            'type' => 'record',
            'id' => $entity->id,
            'name' => $entity->get('label' . $this->getLanguage()),
            'controller' => [
                                'id' => $entity->controller->id,
                                'name' => $entity->controller->label,
                                'contact' => $entity->controller->contact,
                            ],
            'erasure' => strftime("%d-%m-%Y", $entity->erasure->getTimeStamp()),
        ];
        if($entity->purposes != '') {
            $return['purposes'] = $entity->purposes;
        }
        if($entity->description != '') {
            $return['description'] = $entity->description;
        }
        if($entity->representative != '') {
            $return['representative'] = $entity->representative;
        }
        if($entity->dpo != '') {
            $return['data_processor'] = $entity->dpo;
        }
        if($entity->secMeasures != '') {
            $return['security_measures'] = $entity->secMeasures;
        }
        if($entity->dataSubjects) {
            $return['data_subjects'] = $entity->dataSubjects;
        }
        if($entity->personalData != '') {
            $return['personal_data'] = $entity->personalData;
        }
        if($entity->idThirdCountry) {
            $return['international_transfer'] = [
                                                    'transfer' => true,
                                                    'identifier_third_country' => $entity->idThirdCountry,
                                                    'data_processor_third_country' => $entity->dpoThirdCountry,
                                                ];
        }
        else {
            $return['international_transfer'] = ['transfer' => false,];
        }
        foreach ($entity->jointControllers as $jc) {
            $return['joint_controllers'][] =[
                                                'id' => $jc->id,
                                                'name' => $jc->label,
                                                'contact' => $jc->contact,
                                            ];
        }
        foreach ($entity->recipients as $rc) {
            $return['recipients'][] =   [
                                            'id' => $rc->id,
                                            'name' => $rc->label,
                                        ];
        }
        $processorService = $this->get('recordProcessorService');
        foreach ($entity->processors as $p) {
            $return['processors'][] = $processorService->generateExportArray($p->id);
        }
        return $return;
    }

    /**
     * Imports a Record that has been exported into a file.
     * @param int $anrId The target ANR ID
     * @param array $data The data that has been posted to the API (file)
     * @return array An array where the first key is an array of generated records' ID, and the second the eventual errors
     * @throws \MonarcCore\Exception\Exception If the posted data is invalid, or ANR ID is invalid
     */
    public function importFromFile($anrId, $data)
    {
        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($data['file'])) {
            throw new \MonarcCore\Exception\Exception('File missing', 412);
        }

        $ids = $errors = [];
        $anr = $this->get('anrTable')->getEntity($anrId); // throws MonarcCore\Exception\Exception if invalid

        foreach ($data['file'] as $f) {
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])) {
                $file = [];
                if (empty($data['password'])) {
                    $file = json_decode(trim(file_get_contents($f['tmp_name'])), true);
                    if ($file == false) { // support legacy export which were base64 encoded
                      $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), '')), true);
                    }
                } else {
                    // Decrypt the file and store the JSON data as an array in memory
                    $key = $data['password'];
                    $file = json_decode(trim($this->decrypt(file_get_contents($f['tmp_name']), $key)), true);
                    if ($file == false) { // support legacy export which were base64 encoded
                      $file = json_decode(trim($this->decrypt(base64_decode(file_get_contents($f['tmp_name'])), $key)), true);
                    }
                }

                if ($file !== false && ($id = $this->importFromArray($file, $anrId)) !== false) {
                    $ids[] = $id;
                } else {
                    $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                }
            }
        }

        return [$ids, $errors];
    }


    /**
     * Imports a record from a data array. This data is generally what has been exported into a file.
     * @param array $data The asset's data fields
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr)
    {
        if(isset($data['type']) && $data['type'] == 'record')
        {
            $newData = [];
            $newData['anr'] =  $this->anrTable->getEntity($anr);
            $newData['label1'] = $data['name'];
            $newData['label2'] = $data['name'];
            $newData['label3'] = $data['name'];
            $newData['label4'] = $data['name'];
            $newData['purposes'] = (isset($data['purposes']) ? $data['purposes'] : '');
            $newData['description'] = (isset($data['description']) ? $data['description'] : '');
            $newData['representative'] = (isset($data['representative']) ? $data['representative'] : '');
            $newData['dpo'] = (isset($data['data_processor']) ? $data['data_processor'] : '');
            $newData['secMeasures'] = (isset($data['security_measures']) ? $data['security_measures'] : '');
            $newData['erasure'] = (new \DateTime('@' .strtotime($data['erasure'])))->format('Y-m-d\TH:i:s.u\Z');
            $newData['dataSubjects'] = (isset($data['data_subjects']) ? $data['data_subjects'] : []);
            $newData['personalData'] = (isset($data['personal_data']) ? $data['personal_data'] : '');
            if($data['international_transfer']['transfer'] == true) {
                $newData['idThirdCountry'] = $data['international_transfer']['identifier_third_country'];
                $newData['dpoThirdCountry'] = $data['international_transfer']['data_processor_third_country'];
            } else {
                $newData['idThirdCountry'] = '';
                $newData['dpoThirdCountry'] = '';
            }
            unset($data['international_transfer']);
            $data['controller']['label'] = $data['controller']['name'];
            unset($data['controller']['name']);
            try {
                $controllerEntity = $this->actorTable->getEntity($data['controller']['id']);
                if ($controllerEntity->get('anr')->get('id') != $anr || $controllerEntity->get('label') != $data['controller']['label'] || $controllerEntity->get('contact') != $data['controller']['contact']) {
                    unset($data['controller']['id']);
                }
            } catch (\MonarcCore\Exception\Exception $e) {
                unset($data['controller']['id']);
            }
            $newData['controller'] = $data['controller'];
            $newData['jointControllers'] = [];
            foreach ($data['joint_controllers'] as $jc) {
                $jc['label'] = $jc['name'];
                unset($jc['name']);
                try {
                    $controllerEntity = $this->actorTable->getEntity($jc['id']);
                    if ($controllerEntity->get('anr')->get('id') != $anr || $controllerEntity->get('label') != $jc['label'] || $controllerEntity->get('contact') != $jc['contact']) {
                        unset($jc['id']);
                    }
                } catch (\MonarcCore\Exception\Exception $e) {
                    unset($jc['id']);
                }
                $newData['jointControllers'][] = $jc;
            }
            $newData['recipients'] = [];
            foreach ($data['recipients'] as $r) {
                $r['label'] = $r['name'];
                unset($r['name']);
                try {
                    $recipientEntity = $this->recipientTable->getEntity($r['id']);
                    if (!$recipientEntity || $recipientEntity->get('anr')->get('id') != $anr || $recipientEntity->get('label') != $r['label']) {
                        unset($r['id']);
                    }
                } catch (\MonarcCore\Exception\Exception $e) {
                    unset($r['id']);
                }
                $newData['recipients'][] = $r;
            }
            $newData['processors'] = [];
            foreach ($data['processors'] as $processor) {
                $processor['label'] = $processor['name'];
                $processor['controllers'] = $processor['controllers_behalf'];
                unset($processor['name']);
                unset($processor['controllers_behalf']);
                if($processor['international_transfer']['transfer'] == true) {
                    $processor['idThirdCountry'] = $processor['international_transfer']['identifier_third_country'];
                    $processor['dpoThirdCountry'] = $processor['international_transfer']['data_processor_third_country'];
                } else {
                    $processor['idThirdCountry'] = '';
                    $processor['dpoThirdCountry'] = '';
                }
                unset($processor['international_transfer']);
                try {
                    $processorEntity = $this->processorTable->getEntity($processor['id']);
                    if ($processorEntity->get('anr')->get('id') != $anr || $processorEntity->get('label') != $processor['label'] || $processorEntity->get('contact') != $processor['contact']) {
                        unset($processor['id']);
                    }
                } catch (\MonarcCore\Exception\Exception $e) {
                    unset($processor['id']);
                }
                $bcs = array();
                foreach ($processor['controllers'] as $bc) {
                    $bc['label'] = $bc['name'];
                    unset($bc['name']);
                    try {
                        $controllerEntity = $this->actorTable->getEntity($bc['id']);
                        if ($controllerEntity->get('anr')->get('id') != $anr || $controllerEntity->get('label') != $bc['label'] || $controllerEntity->get('contact') != $bc['contact']) {
                            unset($bc['id']);
                        }
                    } catch (\MonarcCore\Exception\Exception $e) {
                        unset($bc['id']);
                    }
                    array_push($bcs, $bc);
                }
                $processor['controllers'] = $bcs;
                $newData['processors'][] = $processor;
            }
            return $this->createRecord($newData);
        }
        return false;
    }

}
