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
    protected $dependencies = ['anr','controllers'];
    protected $recordControllerService;
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $anrTable;
    protected $recordTable;
    protected $controllerTable;

    /**
     * Creates a processor of processing activity
     * @param array $data The processor details fields
     * @return object The resulting created processor object (entity)
     */
    public function createProcessor($data)
    {
        $behalfControllers = array();
        foreach ($data['controllers'] as $bc) {
            if(!isset($bc['id'])) {
                $bc['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new controller
                $bc['id'] = $this->recordControllerService->create($bc, true);
            }
            array_push($behalfControllers, $bc['id']);
        }
        $data['controllers'] = $behalfControllers;
        return $this->create($data, true);
    }

    public function deleteProcessor($id)
    {
        $processorEntity = $this->get('table')->getEntity($id);
        $anrId = $entity->anr->id;
        $controllersToCheck = array();

        foreach($processorEntity->controllers as $controller) {
            array_push($controllersToDelete, $controller->id);
        }
        $result = $this->get('table')->delete($id);
        foreach($controllersToCheck as $c) {
            if($this->recordControllerService->controllerWithoutRecord($c, $entity->id, $anrId)) {
                $this->recordControllerService->delete(['anr'=> $anrId, 'id' => $c]);
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
        $behalfControllers = array();
        foreach ($data['controllers'] as $bc) {
            if(!isset($bc['id'])) {
                $bc['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new controller
                $bc['id'] = $this->recordControllerService->create($bc, true);
            }
            array_push($behalfControllers, $bc['id']);
        }
        $data['controllers'] = $behalfControllers;
        $oldBehalfs = array();
        foreach( $entity->controllers as $bc) {
            array_push($oldBehalfs, $bc->id);
        }
        $result = $this->update($id, $data);
        foreach($oldBehalfs as $bc) {
            if(!in_array($bc, $behalfControllers) && $this->recordControllerService->controllerWithoutRecord($bc, $id, $data['anr'])) {
                $this->recordControllerService->delete(['anr'=> $data['anr'], 'id' => $bc]);
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
            'contact' => $entity->contact,
        ];
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
        foreach ($entity->controllers as $bc) {
            $return['controllers_behalf'][] =   [
                                                    'id' => $bc->id,
                                                    'name' => $bc->label,
                                                    'contact' => $bc->contact,
                                                ];
        }
        return $return;
    }

    public function processorWithoutRecord($processorId, $recordId, $anrId) {
        $records = $this->recordTable->getEntityByFields(['processors' => $processorId, 'anr' => $anrId]);
        if(count($records) > 0) {
            return false;
        }
        return true;
    }
}
