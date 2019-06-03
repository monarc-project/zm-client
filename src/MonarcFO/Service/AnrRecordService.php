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
     * @return object The resulting created record object (entity)
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
            'name' => $entity->get('label' . $this->getLanguage()),
            'controller' => [
                                'name' => $entity->controller->label,
                                'contact' => $entity->controller->contact,
                            ],
            'erasure' => strftime("%d/%m/%Y", $entity->erasure->getTimeStamp()),
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
            $return['joint_controllers'][] = [
                                                'name' => $jc->label,
                                                'contact' => $jc->contact,
                                            ];
        }
        foreach ($entity->recipients as $rc) {
            $return['recipients'][] =  $rc->label;
        }
        $processorService = $this->get('recordProcessorService');
        foreach ($entity->processors as $p) {
            $return['processors'][] = $processorService->generateExportArray($p->id);
        }
        return $return;
    }
}
