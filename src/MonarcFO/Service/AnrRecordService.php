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
    protected $personalDataTable;
    protected $internationalTransferTable;


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
        $actorsToCheck = array_unique($actorsToCheck);
        foreach($actorsToCheck as $a) {
            if($this->recordActorService->orphanActor($a, $anrId)) {
                $this->recordActorService->delete(['anr'=> $anrId, 'id' => $a]);
            }
        }
        $processorsToCheck = array_unique($processorsToCheck);
        foreach($processorsToCheck as $p) {
            if($this->recordProcessorService->orphanProcessor($p, $anrId)) {
                $this->recordProcessorService->deleteProcessor($p);
            }
        }
        $recipientsToCheck = array_unique($recipientsToCheck);
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
     * Duplicates an existing record in the anr
     * @param int $recordId The id of record to clone, either its ID or the object
     * @return int The newly created record id
     * @throws \MonarcCore\Exception\Exception
     */
    public function duplicateRecord($recordId, $newLabel)
    {
        $entity = $this->get('table')->getEntity($recordId);
        $newRecord = new \MonarcFO\Model\Entity\Record($entity);
        $newRecord->setId(null);
        $newRecord->setUpdatedAt(null);
        $newRecord->setLabel($newLabel);
        $id = $this->get('table')->save($newRecord);
        if($entity->getPersonalData()) {
            foreach ($entity->getPersonalData() as $pd) {
                $newPersonalData = new \MonarcFO\Model\Entity\RecordPersonalData($pd);
                $newPersonalData->setId(null);
                $newPersonalData->setRecord($newRecord);
                $this->get('personalDataTable')->save($newPersonalData);
            }
        }
        if($entity->getInternationalTransfers()) {
            foreach ($entity->getInternationalTransfers() as $it) {
                $newInternationalTransfer = new \MonarcFO\Model\Entity\RecordInternationalTransfer($it);
                $newInternationalTransfer->setId(null);
                $newInternationalTransfer->setRecord($newRecord);
                $this->get('internationalTransferTable')->save($newInternationalTransfer);
            }
        }
        return $id;
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
        $exportedRecords = ['type' => 'records'];
        foreach($recordEntities as $entity) {
            $exportedRecords['records'][] = $this->generateExportArray($entity->get("id"));
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

        $f = $data['file'];
        if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && file_exists($f['tmp_name'])) {
            $file = [];
            if($data['fileType'] == 'json' || $data['fileType'] == 'bin'){
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
                if($file['type'] == 'records') {
                    foreach($file['records'] as $record) {
                        if ($record !== false && ($id = $this->importFromArray($record, $anrId)) !== false) {
                            $ids[] = $id;
                        } else {
                            $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                        }
                    }
                }
                else {
                    if ($file !== false && ($id = $this->importFromArray($file, $anrId)) !== false) {
                        $ids[] = $id;
                    } else {
                        $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                    }
                }
            }
            else {
                $rows = explode("\n",trim(file_get_contents($f['tmp_name'])));
                $file['type'] = 'record';
                for($j = 1; $j < count($rows); ++$j) {
                    $fields = explode("\",\"",$rows[$j]);
                    $fields[0] = substr($fields[0], 1);
                    $fields[count($fields)-1] = substr($fields[count($fields)-1], 0, -3);
                    if($j != 1 && trim($fields[0])) {
                        if ($file !== false && ($id = $this->importFromArray($file, $anrId)) !== false) {
                            $ids[] = $id;
                        } else {
                            $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                        }
                        $file = [];
                        $file['type'] = 'record';
                    }
                    if(trim($fields[0])) {
                        $file['name'] = $fields[0];
                    }
                    if(trim($fields[3])) {
                        $file['purposes'] = $fields[3];
                    }
                    if(trim($fields[4])) {
                        $file['security_measures'] = $fields[4];
                    }
                    if(trim($fields[6])) {
                        $file['controller'] = [];
                        if(trim($fields[5])) {
                            $file['controller']['id'] = $fields[5];
                        }
                        $file['controller']['name'] = $fields[6];
                        if(trim($fields[7])) {
                            $file['controller']['contact'] = $fields[7];
                        }
                    }
                    if(trim($fields[9])) {
                        $file['representative'] = [];
                        if(trim($fields[8])) {
                            $file['representative']['id'] = $fields[8];
                        }
                        $file['representative']['name'] = $fields[9];
                        if(trim($fields[10])) {
                            $file['representative']['contact'] = $fields[10];
                        }
                    }
                    if(trim($fields[12])) {
                        $file['data_protection_officer'] = [];
                        if(trim($fields[11])) {
                            $file['data_protection_officer']['id'] = $fields[11];
                        }
                        $file['data_protection_officer']['name'] = $fields[12];
                        if(trim($fields[13])) {
                            $file['data_protection_officer']['contact'] = $fields[13];
                        }
                    }
                    if(trim($fields[15])) {
                        if( !isset($file['joint_controllers'])) {
                            $file['joint_controllers'] = [];
                        }
                        $jc = [];
                        if(trim($fields[14])) {
                            $jc['id'] = $fields[14];
                        }
                        $jc['name'] = $fields[15];
                        if(trim($fields[16])) {
                            $jc['contact'] = $fields[16];
                        }
                        $file['joint_controllers'][] = $jc;
                    }
                    if(trim($fields[21])) {
                        if( !isset($file['personal_data'])) {
                            $file['personal_data'] = [];
                        }
                        $pd = [];
                        if(trim($fields[17])) {
                            $pd['data_subject'] = $fields[17];
                        }
                        if(trim($fields[18])) {
                            foreach(explode(", ", $fields[18]) as $dc) {
                                $dataCategory = [];
                                $dataCategory['name'] = $dc;
                                $pd['data_categories'][] = $dataCategory;
                            }
                        }
                        if(trim($fields[19])) {
                            $pd['description'] = $fields[19];
                        }
                        if(trim($fields[20]) != "") {
                            $pd['retention_period'] = $fields[20];
                        }
                        if(trim($fields[21])) {
                            $pd['retention_period_mode'] = $fields[21];
                        }
                        if(trim($fields[22])) {
                            $pd['retention_period_description'] = $fields[22];
                        }
                        $file['personal_data'][] = $pd;
                    }
                    if(trim($fields[23]) || trim($fields[24]) || trim($fields[25]) || trim($fields[26])) {
                        if( !isset($file['recipients'])) {
                            $file['recipients'] = [];
                        }
                        $r = [];
                        if(trim($fields[23])) {
                            $r['id'] = $fields[23];
                        }
                        if(trim($fields[24])) {
                            $r['name'] = $fields[24];
                        }
                        if(trim($fields[25])) {
                            $r['type'] = $fields[25];
                        }
                        if(trim($fields[26])) {
                            $r['description'] = $fields[26];
                        }
                        $file['recipients'][] = $r;
                    }
                    if(trim($fields[27]) || trim($fields[28]) || trim($fields[29]) || trim($fields[30])) {
                        if( !isset($file['international_transfers'])) {
                            $file['international_transfers'] = [];
                        }
                        $it = [];
                        if(trim($fields[27]))
                            $it['organisation'] = $fields[27];
                        if(trim($fields[28]))
                            $it['description'] = $fields[28];
                        if(trim($fields[29]))
                            $it['country'] = $fields[29];
                        if(trim($fields[30]))
                            $it['documents'] = $fields[30];
                        $file['international_transfers'][] = $it;
                    }
                    if(trim($fields[32])) {
                        if( !isset($file['processors'])) {
                            $file['processors'] = [];
                        }
                        $p = [];
                        if(trim($fields[31])) {
                            $p['id'] = $fields[31];
                        }
                        $p['name'] = $fields[32];
                        if(trim($fields[33]))
                            $p['contact'] = $fields[33];
                        if(trim($fields[34]))
                            $p['activities'] = $fields[34];
                        if(trim($fields[35]))
                            $p['security_measures'] = $fields[35];
                        if(trim($fields[37])) {
                            $rep = [];
                            if(trim($fields[36])) {
                                $rep['id'] = $fields[36];
                            }
                            $rep['name'] = $fields[37];
                            if(trim($fields[38]))
                                $rep['contact'] = $fields[38];
                            $p['representative'] = $rep;
                        }
                        if(trim($fields[40])) {
                            $dpo = [];
                            if(trim($fields[39])) {
                                $dpo['id'] = $fields[39];
                            }
                            $dpo['name'] = $fields[40];
                            if(trim($fields[41]))
                                $dpo['contact'] = $fields[41];
                            $p['data_protection_officer'] = $dpo;
                        }
                        $file['processors'][] = $p;
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
            $id = $this->create($newData);
            if(isset($data['personal_data'])) {
                foreach ($data['personal_data'] as $pd) {
                    $personalData = [];
                    $personalData['id'] = $this->recordPersonalDataService->importFromArray($pd, $anr, $id, $dataCategoryMap);
                    $newData['personalData'][] = $personalData;
                }
            }
            if(isset($data['international_transfers'])) {
                foreach ($data['international_transfers'] as $it) {
                    $internationalTransfers = [];
                    $internationalTransfers['id'] = $this->recordInternationalTransferService->importFromArray($it, $anr, $id);
                    $newData['internationalTransfers'][] = $internationalTransfers;
                }
            }
            return $id;
        }
        return false;
    }

}
