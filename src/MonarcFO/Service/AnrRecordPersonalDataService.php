<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Personal Data Service
 *
 * Class AnrRecordPersonalDataService
 * @package MonarcFO\Service
 */
class AnrRecordPersonalDataService extends AbstractService
{
    protected $dependencies = ['anr', 'record', 'dataSubjects', 'dataCategories'];
    protected $filterColumns = ['label'];
    protected $recordDataSubjectService;
    protected $recordDataCategoryService;
    protected $userAnrTable;
    protected $anrTable;

    /**
     * Creates a personal data record for a processing activity
     * @param array $data The  personal data details fields
     * @return object The resulting created  personal data object (entity)
     */
    public function createPersonalData($data)
    {
        file_put_contents('php://stderr', print_r($data, TRUE).PHP_EOL);
        $dss = array();
        foreach ($data['dataSubjects'] as $ds) {
            if(!isset($ds['id'])) {
                $ds['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new personal data
                $ds['id'] = $this->recordDataSubjectService->create($ds, true);
            }
            array_push($dss, $ds['id']);
        }
        $data['dataSubjects'] = $dss;
        $dcs = array();
        foreach ($data['dataCategories'] as $dc) {
            if(!isset($dc['id'])) {
                $dc['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new controller
                $dc['id'] = $this->recordDataCategoryService->create($dc, true);
            }
            array_push($dcs, $dc['id']);
        }
        $data['dataCategories'] = $dcs;
        return $this->create($data, true);
    }

    public function deletePersonalData($id)
    {
        $personalDataEntity = $this->get('table')->getEntity($id);
        $anrId = $personalDataEntity->anr->id;
        $dataSubjectsToCheck = array();
        foreach($personalDataEntity->dataSubjects as $ds) {
            array_push($dataSubjectsToCheck, $ds->id);
        }

        $dataCategoriesToCheck = array();
        foreach($personalDataEntity->dataCategories as $dc) {
            array_push($dataCategoriesToCheck, $dc->id);
        }


        $result = $this->get('table')->delete($id);
        foreach($dataSubjectsToCheck as $ds) {
            if($this->recordDataSubjectService->orphanDataSubject($ds, $anrId)) {
                $this->recordDataSubjectService->delete(['anr'=> $anrId, 'id' => $ds]);
            }
        }
        foreach($dataCategoriesToCheck as $dc) {
            if($this->recordDataCategoryService->orphanDataCategory($dc, $anrId)) {
                $this->recordDataCategoryService->delete(['anr'=> $anrId, 'id' => $dc]);
            }
        }
        return $result;
    }

    /**
     * Updates the personal data of a processing activity
     * @param array $data The personal data details fields
     * @return object The resulting created personal data object (entity)
     */
    public function updatePersonalData($id, $data)
    {
        file_put_contents('php://stderr', print_r($data, TRUE).PHP_EOL);
        $entity = $this->get('table')->getEntity($id);
        $dataSubjects = array();
        foreach ($data['dataSubjects'] as $ds) {
            if(!isset($ds['id'])) {
                $ds['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new data subject
                $ds['id'] = $this->recordDataSubjectService->create($ds, true);
            }
            array_push($dataSubjects, $ds['id']);
        }
        $dataCategories = array();
        foreach ($data['dataCategories'] as $dc) {
            if(!isset($dc['id'])) {
                $dc['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new data category
                $dc['id'] = $this->recordDataCategoryService->create($dc, true);
            }
            array_push($dataCategories, $dc['id']);
        }
        $data['dataSubjects'] = $dataSubjects;
        $data['dataCategories'] = $dataCategories;
        $oldDataSubjects = array();
        foreach( $entity->dataSubjects as $ds) {
            array_push($oldDataSubjects, $ds->id);
        }
        $oldDataCategories = array();
        foreach( $entity->dataCategories as $dc) {
            array_push($oldDataCategories, $dc->id);
        }
        $result = $this->update($id, $data);
        foreach($oldDataSubjects as $ds) {
            if(!in_array($ds, $dataSubjects) && $this->recordDataSubjectService->orphanDataSubject($ds, $data['anr'])) {
                $this->recordDataSubjectService->delete(['anr'=> $data['anr'], 'id' => $ds]);
            }
        }
        foreach($oldDataCategories as $dc) {
            if(!in_array($dc, $dataCategories) && $this->recordDataCategoryService->orphanDataCategory($dc, $data['anr'])) {
                $this->recordDataCategoryService->delete(['anr'=> $data['anr'], 'id' => $dc]);
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

    }
}
