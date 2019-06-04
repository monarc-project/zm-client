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
    protected $dependencies = ['anr', 'controller', 'jointControllers', 'processors', 'recipients'];
    protected $recordControllerService;
    protected $recordProcessorService;
    protected $recordRecipientCategoryService;
    protected $userAnrTable;
    protected $anrTable;
    protected $controllerTable;
    protected $processorTable;
    protected $recipientCategoryTable;


    public function deleteRecord($newId)
    {
        $entity = $this->get('table')->getEntity($newId['id']);
        $controllerId = $entity->controller->id;
        $anrId = $entity->anr->id;
        $controllersToDelete = array();
        $processorsToDelete = array();
        $categoriesToDelete = array();
        if($this->controllerWithoutRecord($controllerId, $entity->id, $anrId)) {
            array_push($controllersToDelete, $controllerId);
        }
        foreach($entity->jointControllers as $jc) {
            if($this->controllerWithoutRecord($jc->id, $entity->id, $anrId)) {
                array_push($controllersToDelete, $jc->id);
            }
        }
        foreach($entity->processors as $p) {
            if($this->processorWithoutRecord($p->id, $entity->id, $anrId)) {
                $processorEntity = $this->processorTable->getEntity($p->id);
                foreach($processorEntity->controllers as $controller) {
                    if($this->controllerWithoutRecord($controller->id, $entity->id, $anrId)) {
                        array_push($controllersToDelete, $controller->id);
                    }
                }
                array_push($processorsToDelete, $p->id);
            }
        }
        foreach($entity->recipients as $r) {
            if($this->recipientCategoryWithoutRecord($r->id, $entity->id, $anrId)) {
                array_push($categoriesToDelete, $r->id);
            }
        }
        $result = $this->delete($newId);
        foreach($controllersToDelete as $c) {
            $this->recordControllerService->delete(['anr'=> $anrId, 'id' => $c]);
        }
        foreach($processorsToDelete as $p) {
            $this->recordProcessorService->delete(['anr'=> $anrId, 'id' => $p]);
        }
        foreach($categoriesToDelete as $r) {
            $this->recordRecipientCategoryService->delete(['anr'=> $anrId, 'id' => $r]);
        }

        return $result;
    }

    public function controllerWithoutRecord($controllerId, $recordId, $anrId) {
        $records = $this->get('table')->getEntityByFields(['controller' => $controllerId, 'anr' => $anrId]);
        foreach($records as $record) {
            if($record->id != $recordId) {
                return false;
            }
        }
        $jointForRecords = $this->get('table')->getEntityByFields(['jointControllers' => $controllerId, 'anr' => $anrId]);
        foreach($jointForRecords as $record) {
            if($record->id != $recordId) {
                return false;
            }
        }

        $behalfForProcessors = $this->processorTable->getEntityByFields(['controllers' => $controllerId, 'anr' => $anrId]);
        foreach($behalfForProcessors as $processor) {
            $records = $this->get('table')->getEntityByFields(['processors' => $processor->id, 'anr' => $anrId]);
            if(count($records)> 1 || (count($records)==1 && $records[0]->id != $recordId)) {
                return false;
            }
        }
        return true;
    }

    public function processorWithoutRecord($processorId, $recordId, $anrId) {
        $records = $this->get('table')->getEntityByFields(['processors' => $processorId, 'anr' => $anrId]);
        if(count($records) <= 1) {
            //Sanity Check
            if (count($records) ==1 && $records[0]->id != $recordId) {
                file_put_contents('php://stderr', print_r('This processor has only one record and it is not the one currently being deleted', TRUE).PHP_EOL);
            }
            return true;
        }
        return false;
    }

    public function recipientCategoryWithoutRecord($recipientId, $recordId, $anrId) {
        $records = $this->get('table')->getEntityByFields(['recipients' => $recipientId, 'anr' => $anrId]);
        if(count($records) <= 1) {
            //Sanity Check
            if (count($records) ==1 && $records[0]->id != $recordId) {
                file_put_contents('php://stderr', print_r('This recipient category has only one record and it is not the one currently being deleted', TRUE).PHP_EOL);
            }
            return true;
        }
        return false;
    }

    /**
     * Updates a record of processing activity
     * @param array $data The record details fields
     * @return object The resulting created record object (entity)
     */
    public function updateRecord($id, $data)
    {
        $entity = $this->get('table')->getEntity($id);
        if(!isset($data['controller']['id'])) {
            $data['controller']['anr'] = $this->anrTable->getEntity($data['anr']);
            // Create a new controller
            $data['controller']['id'] = $this->recordControllerService->create($data['controller'], true);
        }
        $data['controller'] = $data['controller']['id'];
        $jointControllers = array();
        foreach ($data['jointControllers'] as $jc) {
            if(!isset($jc['id'])) {
                $jc['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new controller
                $jc['id'] = $this->recordControllerService->create($jc, true);
            }
            array_push($jointControllers, $jc['id']);
        }
        $data['jointControllers'] = $jointControllers;
        $processors = array();
        foreach ($data['processors'] as $processor) {
            if(!isset($processor['id'])) {
                $processor['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new Processor
                $processor['id'] = $this->recordProcessorService->createProcessor($processor, true);
            }
            array_push($processors,$processor['id']);
        }
        $data['processors'] = $processors;
        $recipientCategories = array();
        foreach ($data['recipients'] as $recipientCategory) {
            if(!isset($recipientCategory['id'])) {
                $recipientCategory['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new recipient category
                $recipientCategory['id'] = $this->recordRecipientCategoryService->create($recipientCategory, true);
            }
            array_push($recipientCategories,$recipientCategory['id']);
        }
        $data['recipients'] = $recipientCategories;
        $oldControllerId = $entity->controller->id;
        $oldJointControllers = array();
        foreach( $entity->jointControllers as $js) {
            array_push($oldJointControllers, $js->id);
        }
        $oldProcessors = array();
        foreach( $entity->processors as $p) {
            array_push($oldProcessors, $p->id);
        }
        $oldRecipientCategories = array();
        foreach( $entity->recipients as $r) {
            array_push($oldRecipientCategories, $r->id);
        }
        $data['erasure'] = (new \DateTime($data['erasure']))->setTimezone((new \DateTime())->getTimezone());
        $result = $this->update($id, $data);
        if(!in_array($oldControllerId, $jointControllers) && $data['controller'] != $oldControllerId
            && $this->controllerWithoutRecord($oldControllerId, $id, $data['anr'])) {
            $this->recordControllerService->delete(['anr'=> $data['anr'], 'id' => $oldControllerId]);
        }
        foreach($oldJointControllers as $js) {
            if(!in_array($js, $jointControllers) && $js != $data['controller']
               && $this->controllerWithoutRecord($js, $id, $data['anr'])) {
                $this->recordControllerService->delete(['anr'=> $data['anr'], 'id' => $js]);
            }
        }
        foreach($oldProcessors as $processor) {
            if(!in_array($processor, $processors) && $this->processorWithoutRecord($processor, $id, $data['anr'])) {
                $processorEntity = $this->processorTable->getEntity($processor);
                foreach($processorEntity->controllers as $controller) {
                    if($this->controllerWithoutRecord($controller->id, $id, $data['anr'])) {
                        $this->recordControllerService->delete(['anr'=> $anrId, 'id' => $controller->id]);
                    }
                }
                $this->recordProcessorService->delete(['anr'=> $data['anr'], 'id' => $processor]);
            }
        }
        foreach($oldRecipientCategories as $rc) {
            if(!in_array($rc, $recipientCategories) && $this->recipientCategoryWithoutRecord($rc, $id, $data['anr'])) {
                $this->recordRecipientCategoryService->delete(['anr'=> $data['anr'], 'id' => $rc]);;
            }
        }
        return $result;
    }
    /**
     * Creates a record of processing activity
     * @param array $data The record details fields
     * @return object The resulting created record object (id)
     */
    public function createRecord($data)
    {
        if(!isset($data['controller']['id'])) {
            $data['controller']['anr'] = $this->anrTable->getEntity($data['anr']);
            // Create a new controller
            $data['controller']['id'] = $this->recordControllerService->create($data['controller'], true);
        }
        $data['controller'] = $data['controller']['id'];
        $jointControllers = array();
        foreach ($data['jointControllers'] as $jc) {
            if(!isset($jc['id'])) {
                $jc['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new controller
                $jc['id'] = $this->recordControllerService->create($jc, true);
            }
            array_push($jointControllers, $jc['id']);
        }
        $data['jointControllers'] = $jointControllers;
        $processors = array();
        foreach ($data['processors'] as $processor) {
            if(!isset($processor['id'])) {
                $processor['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new Processor
                $processor['id'] = $this->recordProcessorService->createProcessor($processor, true);
            }
            array_push($processors,$processor['id']);
        }
        $data['processors'] = $processors;
        $recipientCategories = array();
        foreach ($data['recipients'] as $recipientCategory) {
            if(!isset($recipientCategory['id'])) {
                $recipientCategory['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new recipient category
                $recipientCategory['id'] = $this->recordRecipientCategoryService->create($recipientCategory, true);
            }
            array_push($recipientCategories,$recipientCategory['id']);
        }
        $data['recipients'] = $recipientCategories;
        $data['erasure'] = (new \DateTime($data['erasure']))->setTimezone((new \DateTime())->getTimezone());
        return $this->create($data, true);
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
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR entity
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
            $newData['erasure'] = (new \DateTime('@' .strtotime($data['erasure'])))->format('Y-m-d\TH:i:s.u\Z');;
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
                $controllerEntity = $this->controllerTable->getEntity($data['controller']['id']);
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
                    $controllerEntity = $this->controllerTable->getEntity($jc['id']);
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
                    $recipientEntity = $this->recipientCategoryTable->getEntity($r['id']);
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
                        $controllerEntity = $this->controllerTable->getEntity($bc['id']);
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
