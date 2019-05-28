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
        $this->removeControllerWithoutRecord($controllerId, $entity->id, $anrId);
        foreach($entity->jointControllers as $jc) {
            $this->removeControllerWithoutRecord($jc->id, $entity->id, $anrId);
        }
        foreach($entity->processors as $p) {
            $this->removeProcessorWithoutRecord($p->id, $anrId);
        }
        foreach($entity->recipients as $r) {
            $this->removeRecipientCategoryWithoutRecord($r->id, $anrId);
        }
        return $this->delete($newId);
    }

    public function removeControllerWithoutRecord($controllerId, $recordId, $anrId) {
        $records = $this->get('table')->getEntityByFields(['controller' => $controllerId, 'anr' => $anrId]);
        $jointForRecords = $this->get('table')->getEntityByFields(['jointControllers' => $controllerId, 'anr' => $anrId]);
        $canRemove = true;
        foreach($records as $record) {
            if($record->id != $recordId) {
                $canRemove = false;
                break;
            }
        }
        if($canRemove) {
            foreach($jointForRecords as $record) {
                if($record->id != $recordId) {
                    $canRemove = false;
                    break;
                }
            }
        }
        if($canRemove) {
            $this->recordControllerService->delete(['anr'=> $anrId, 'id' => $controllerId]);
        }
    }

    public function removeProcessorWithoutRecord($processorId, $anrId) {
        $records = $this->get('table')->getEntityByFields(['processors' => $processorId, 'anr' => $anrId]);
        if(count($records) == 1) {
            $this->recordProcessorService->delete(['anr'=> $anrId, 'id' => $processorId]);
        }
    }

    public function removeRecipientCategoryWithoutRecord($recipientId, $anrId) {
        $records = $this->get('table')->getEntityByFields(['recipients' => $recipientId, 'anr' => $anrId]);
        if(count($records) == 1) {
            $this->recordRecipientCategoryService->delete(['anr'=> $anrId, 'id' => $recipientId]);
        }
    }

    /**
     * Creates a record of processing activity
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
        $data['recipientCategories'] = $recipientCategories;
        $oldControllerId = $entity->controller->id;
        $oldJointControllers = $entity->jointControllers;
        $data['erasure'] = new \DateTime($data['erasure']);
        $result = $this->update($id, $data);
        if(!in_array($oldControllerId, $jointControllers) && $data['controller'] != $oldControllerId) {
            $this->removeControllerWithoutRecord($oldControllerId, $id, $data['anr']);
        }
        foreach($oldJointControllers as $js) {
            if(!in_array($js->id, $jointControllers) && $js->id != $data['controller']) {
                $this->removeControllerWithoutRecord($js->id, $id, $data['anr']);
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
        foreach ($data['recipientCategories'] as $recipientCategory) {
            if(!isset($recipientCategory['id'])) {
                $recipientCategory['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new recipient category
                $recipientCategory['id'] = $this->recordRecipientCategoryService->create($recipientCategory, true);
            }
            array_push($recipientCategories,$recipientCategory['id']);
        }
        $data['recipientCategories'] = $recipientCategories;
        $data['erasure'] = new \DateTime($data['erasure']);
        return $this->create($data, true);
    }

}
