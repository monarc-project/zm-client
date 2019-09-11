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
            } else {
                $this->recordProcessorService->deleteActivityAndSecMeasure($p, $id);
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
            } else if (!in_array($processor, $processors)) {
                $this->recordProcessorService->deleteActivityAndSecMeasure($processor, $id);
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
        if($entity->getProcessors()) {
            foreach ($entity->getProcessors() as $p) {
                $data = [];
                $activities = $p->getActivities();
                $data["activities"] = $activities[$recordId];
                $secMeasures = $p->getSecMeasures();
                $data["security_measures"] = $secMeasures[$recordId];
                $processor["id"] = $this->recordProcessorService->importActivityAndSecMeasures($data, $p->getId(), $id);
            }
        }
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
        $exportedRecords = array();
        $elem = $this->generateExportArray($data['id'], $filename);
        $exportedRecord = json_encode([$elem]);
        $data['filename'] = $filename;
        if (! empty($data['password'])) {
            $exportedRecord = $this->encrypt($exportedRecord, $data['password']);
        }
        return $exportedRecord;
    }


    public function exportAll($data) {
        $recordEntities = $this->get('table')->getEntityByFields(['anr' => $data["anr"]]);
        $exportedRecords = array();
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
            $return['processors'][] = $this->recordProcessorService->generateExportArray($p->id, $id);
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

        if ($data['isJson'] == 'true') {
            $f = $data['file'];
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

                foreach($file as $key => $record) {
                    if ($record !== false && ($id = $this->importFromArray($record, $anrId)) !== false) {
                        $ids[] = $id;
                    } else {
                        $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                    }
                }
            }
        } else {
            $array = $data['csv'];
            $file = [];
            $file['type'] = 'record';
            foreach($array as $key => $row) {
                if($key != 0 && trim($row["name"])) {
                    if ($file !== false && ($id = $this->importFromArray($file, $anrId)) !== false) {
                        $ids[] = $id;
                    } else {
                        $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
                    }
                    $file = [];
                    $file['type'] = 'record';
                }
                if(trim($row['name'])) {
                    $file['name'] = $row['name'];
                }
                if(trim($row['purposes'])) {
                    $file['purposes'] = $row['purposes'];
                }
                if(trim($row['security measures'])) {
                    $file['security_measures'] = $row['security measures'];
                }
                if(trim($row['controller name'])) {
                    $file['controller'] = [];
                    $file['controller']['name'] = $row['controller name'];
                    if(trim($row['controller contact'])) {
                        $file['controller']['contact'] = $row['controller contact'];
                    }
                }
                if(trim($row['representative name'])) {
                    $file['representative'] = [];
                    $file['representative']['name'] = $row['representative name'];
                    if(trim($row['representative contact'])) {
                        $file['representative']['contact'] = $row['representative contact'];
                    }
                }
                if(trim($row['data protection officer name'])) {
                    $file['data_protection_officer'] = [];
                    $file['data_protection_officer']['name'] = $row['data protection officer name'];
                    if(trim($row['data protection officer contact'])) {
                        $file['data_protection_officer']['contact'] = $row['data protection officer contact'];
                    }
                }
                if(trim($row['joint controllers name'])) {
                    if( !isset($file['joint_controllers'])) {
                        $file['joint_controllers'] = [];
                    }
                    $jc = [];
                    $jc['name'] = $row['joint controllers name'];
                    if(trim($row['joint controllers contact'])) {
                        $jc['contact'] = $row['joint controllers contact'];
                    }
                    $file['joint_controllers'][] = $jc;
                }
                if(trim($row['retention period unit'])) {
                    if( !isset($file['personal_data'])) {
                        $file['personal_data'] = [];
                    }
                    $pd = [];
                    if(trim($row['data subject'])) {
                        $pd['data_subject'] = $row['data subject'];
                    }
                    if(trim($row['data categories'])) {
                        foreach(explode(", ", $row['data categories']) as $dc) {
                            $dataCategory = [];
                            $dataCategory['name'] = $dc;
                            $pd['data_categories'][] = $dataCategory;
                        }
                    }
                    if(trim($row['description'])) {
                        $pd['description'] = $row['description'];
                    }
                    if(trim($row['retention period']) != "") {
                        $pd['retention_period'] = $row['retention period'];
                    }
                    if(trim($row['retention period unit'])) {
                        $pd['retention_period_mode'] = $row['retention period unit'];
                    }
                    if(trim($row['retention period description'])) {
                        $pd['retention_period_description'] = $row['retention period description'];
                    }
                    $file['personal_data'][] = $pd;
                }
                if(trim($row['data recipient']) || trim($row['data recipient type']) || trim($row['description'])) {
                    if( !isset($file['recipients'])) {
                        $file['recipients'] = [];
                    }
                    $r = [];
                    if(trim($row['data recipient'])) {
                        $r['name'] = $row['data recipient'];
                    }
                    if(trim($row['data recipient type'])) {
                        $r['type'] = $row['data recipient type'];
                    }
                    if(trim($row['description'])) {
                        $r['description'] = $row['description'];
                    }
                    $file['recipients'][] = $r;
                }
                if(trim($row['organisation of international transfer']) || trim($row['description']) || trim($row['country']) || trim($row['documents'])) {
                    if( !isset($file['international_transfers'])) {
                        $file['international_transfers'] = [];
                    }
                    $it = [];
                    if(trim($row['organisation of international transfer']))
                        $it['organisation'] = $row['organisation of international transfer'];
                    if(trim($row['description']))
                        $it['description'] = $row['description'];
                    if(trim($row['country']))
                        $it['country'] = $row['country'];
                    if(trim($row['documents']))
                        $it['documents'] = $row['documents'];
                    $file['international_transfers'][] = $it;
                }
                if(trim($row['data processor name'])) {
                    if( !isset($file['processors'])) {
                        $file['processors'] = [];
                    }
                    $p = [];
                    $p['name'] = $row['data processor name'];
                    if(trim($row['data processor contact']))
                        $p['contact'] = $row['data processor contact'];
                    if(trim($row['activities']))
                        $p['activities'] = $row['activities'];
                    if(trim($row['data processor security measures']))
                        $p['security_measures'] = $row['data processor security measures'];
                    if(trim($row['data processor representative name'])) {
                        $rep = [];
                        $rep['name'] = $row['data processor representative name'];
                        if(trim($row['data processor representative contact']))
                            $rep['contact'] = $row['data processor representative contact'];
                        $p['representative'] = $rep;
                    }
                    if(trim($row['data processor data protection officer name'])) {
                        $dpo = [];
                        $dpo['name'] = $row['data processor data protection officer name'];
                        if(trim($row['data processor data protection officer contact']))
                            $dpo['contact'] = $row['data processor data protection officer contact'];
                        $p['data_protection_officer'] = $dpo;
                    }
                    $file['processors'][] = $p;
                }
            }
            if ($file !== false && $file['name'] && ($id = $this->importFromArray($file, $anrId)) !== false) {
                $ids[] = $id;
            } else {
                $errors[] = 'The file "' . $f['name'] . '" can\'t be imported';
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
    public function importFromArray($data, $anr)
    {
        $newData = [];
        $newData['anr'] = $anr;
        $newData['label'] = $data['name'];
        $newData['purposes'] = (isset($data['purposes']) ? $data['purposes'] : '');
        $newData['secMeasures'] = (isset($data['security_measures']) ? $data['security_measures'] : '');
        if(isset($data['controller'])) {
            $newData['controller']["id"] = $this->recordActorService->importFromArray($data['controller'], $anr);
        }
        if(isset($data['representative'])) {
            $newData['representative']["id"] = $this->recordActorService->importFromArray($data['representative'], $anr);
        }
        if(isset($data['data_protection_officer'])) {
            $newData['dpo']["id"] = $this->recordActorService->importFromArray($data['data_protection_officer'], $anr);
        }
        if(isset($data['joint_controllers'])) {
            foreach ($data['joint_controllers'] as $jc) {
                $jointController = [];
                $jointController["id"] = $this->recordActorService->importFromArray($jc, $anr);
                $newData['jointControllers'][] = $jointController;
            }
        }
        if(isset($data['recipients'])) {
            foreach ($data['recipients'] as $r) {
                $recipient = [];
                $recipient["id"] = $this->recordRecipientService->importFromArray($r, $anr);
                $newData['recipients'][] = $recipient;
            }
        }
        $createdProcessors = [];
        if(isset($data['processors'])) {
            foreach ($data['processors'] as $p) {
                $processor = [];
                $processor["id"] = $this->recordProcessorService->importFromArray($p, $anr);
                $createdProcessors[$processor["id"]] = $p;
                $newData['processors'][] = $processor;
            }
        }
        $id = $this->create($newData);
        if(isset($data['processors'])) {
            foreach ($createdProcessors as $processorId => $p) {
                $processor["id"] = $this->recordProcessorService->importActivityAndSecMeasures($p, $processorId, $id);
            }
        }
        if(isset($data['personal_data'])) {
            foreach ($data['personal_data'] as $pd) {
                $personalData = [];
                $personalData['id'] = $this->recordPersonalDataService->importFromArray($pd, $anr, $id);
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

}
