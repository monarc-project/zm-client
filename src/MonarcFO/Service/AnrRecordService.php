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


    /**
     * Creates a record of processing activity
     * @param array $data The record details fields
     * @return object The resulting created record object (entity)
     */
    public function updateRecord($newId, $data)
    {
        if(!isset($data['controller']['id'])) {
            $data['controller']['anr'] = $this->anrTable->getEntity($data['anr']);
            // Create a new controller
            $data['controller']['id'] = $this->recordControllerService->create($data['controller'], true);
        }
        $data['controller'] = $data['controller']['id'];//$this->controllerTable->getEntity($data['controller']['id']);
        $jointControllers = array();
        foreach ($data['jointControllers'] as $jc) {
            array_push($jointControllers, $jc['id']);
        }
        $data['jointControllers'] = $jointControllers;
        $processors = array();
        foreach ($data['processors'] as $processor) {
            array_push($processors,$processor['id']);
        }
        $data['processors'] = $processors;
        $recipientCategories = array();
        foreach ($data['recipientCategories'] as $recipientCategory) {
            array_push($recipientCategories,$recipientCategory['id']);
        }
        $data['recipientCategories'] = $recipientCategories;
        return $this->update($newId, $data);
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
        return $this->create($data, true);
    }

}
