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
    protected $filterColumns = [ 'label' ];
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
            $this->recordPersonalDataService->deletePersonalData(['anr'=> $anrId, 'id' => $pd->id]);
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
            if(!in_array($processor, $processors) && $this->recordProcessorService->orphanProcessor($processor, $data['anr'])) {
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

        $filename = preg_replace("/[^a-z0-9\._-]+/i", '', $entity->label);

        $return = [
            'type' => 'record',
            'id' => $entity->id,
            'name' => $entity->label,
        ];
        if($entity->controller) {
            $return['controller'] = $this->recordActorService->generateExportArray($entity->controller->id);
        }
        if($entity->representative) {
            $return['representative'] = $this->recordActorService->generateExportArray($entity->representative->id);
        }
        if($entity->dpo) {
            $return['data_protection_officer'] = $this->recordActorService->generateExportArray($entity->dpo->id);
        }
        if($entity->purposes != '') {
            $return['purposes'] = $entity->purposes;
        }
        if($entity->secMeasures != '') {
            $return['security_measures'] = $entity->secMeasures;
        }
        foreach ($entity->jointControllers as $jc) {
            $return['joint_controllers'][] = $this->recordActorService->generateExportArray($jc->id);
        }
        foreach ($entity->personalData as $pd) {
            $return['personal_data'][] = $this->recordPersonalDataService->generateExportArray($pd->id);
        }
        foreach ($entity->recipients as $r) {
            $return['recipients'][] = $this->recordRecipientService->generateExportArray($r->id);
        }
        foreach ($entity->internationalTransfers as $it) {
            $return['international_transfers'][] = $this->recordInternationalTransferService->generateExportArray($it->id);
        }
        foreach ($entity->processors as $p) {
            $return['processors'][] = $this->recordProcessorService->generateExportArray($p->id);
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
     * @param array $data The record's data fields
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr, &$actorMap = array(), &$recipientMap = array(), &$processorMap = array(), &$dataCategoryMap = array())
    {
        if(isset($data['type']) && $data['type'] == 'record')
        {
            $newData = [];
            $newData['anr'] = $anr;
            $newData['label'] = $data['name'];
            $id = $this->create($newData);
            $newData['purposes'] = (isset($data['purposes']) ? $data['purposes'] : '');
            $newData['secMeasures'] = (isset($data['security_measures']) ? $data['security_measures'] : '');
            if(isset($data['controller'])) {
                if(isset($actorMap[$data['controller']['id']])) {
                    $newData['controller']["id"] = $actorMap[$data['controller']['id']];
                } else {
                    $newData['controller']["id"] = $this->recordActorService->importFromArray($data['controller'], $anr);
                    $actorMap[$data['controller']['id']] = $newData['controller']["id"];
                }
            }
            if(isset($data['representative'])) {
                if(isset($actorMap[$data['representative']['id']])) {
                    $newData['representative']["id"] = $actorMap[$data['representative']['id']];
                } else {
                    $newData['representative']["id"] = $this->recordActorService->importFromArray($data['representative'], $anr);
                    $actorMap[$data['representative']['id']] = $newData['representative']["id"];
                }
            }
            if(isset($data['data_protection_officer'])) {
                if(isset($actorMap[$data['data_protection_officer']['id']])) {
                    $newData['dpo']["id"] = $actorMap[$data['data_protection_officer']['id']];
                } else {
                    $newData['dpo']["id"] = $this->recordActorService->importFromArray($data['data_protection_officer'], $anr);
                    $actorMap[$data['data_protection_officer']['id']] = $newData['dpo']["id"];
                }
            }
            if(isset($data['joint_controllers'])) {
                foreach ($data['joint_controllers'] as $jc) {
                    $jointController = [];
                    if(isset($actorMap[$jc['id']])) {
                        $jointController["id"] = $actorMap[$jc['id']];
                    } else {
                        $jointController["id"] = $this->recordActorService->importFromArray($jc, $anr);
                        $actorMap[$jc['id']] = $jointController["id"];
                    }
                    $newData['jointControllers'][] = $jointController;
                }
            }
            if(isset($data['personal_data'])) {
                foreach ($data['personal_data'] as $pd) {
                    $personalData = [];
                    $personalData['id'] = $this->recordPersonalDataService->importFromArray($pd, $anr, $id, $dataCategoryMap);
                    $newData['personalData'][] = $personalData;
                }
            }
            if(isset($data['recipients'])) {
                foreach ($data['recipients'] as $r) {
                    $recipient = [];
                    if(isset($recipientMap[$r['id']])) {
                        $recipient["id"] = $recipientMap[$r['id']];
                    } else {
                        $recipient["id"] = $this->recordRecipientService->importFromArray($r, $anr);
                        $recipientMap[$r['id']] = $recipient["id"];
                    }
                    $newData['recipients'][] = $recipient;
                }
            }
            if(isset($data['international_transfers'])) {
                foreach ($data['international_transfers'] as $it) {
                    $internationalTransfers = [];
                    $internationalTransfers['id'] = $this->recordInternationalTransferService->importFromArray($it, $anr, $id);
                    $newData['internationalTransfers'][] = $internationalTransfers;
                }
            }
            if(isset($data['processors'])) {
                foreach ($data['processors'] as $p) {
                    $processor = [];
                    if(isset($processorMap[$p['id']])) {
                        $processor["id"] = $processorMap[$p['id']];
                    } else {
                        $processor["id"] = $this->recordProcessorService->importFromArray($p, $anr, $actorMap);
                        $processorMap[$p['id']] = $processor["id"];
                    }
                    $newData['processors'][] = $processor;
                }
            }
            return $this->updateRecord($id,$newData);
        }
        return false;
    }

}
