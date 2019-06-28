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
    * Generates the array to be exported into a file when calling {#exportPersonalData}
    * @see #exportPersonalData
    * @param int $id The personalData's id
    * @return array The data array that should be saved
    * @throws \MonarcCore\Exception\Exception If the personal data is not found
    */
    public function generateExportArray($id)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \MonarcCore\Exception\Exception('Entity `id` not found.');
        }
        $return = [];
        if($entity->dataSubjects) {
            foreach($entity->dataSubjects as $ds) {
                $return['data_subjects'][] = $this->recordDataSubjectService->generateExportArray($ds->id);
            }
        }
        if($entity->dataCategories) {
            foreach($pd->dataCategories as $dc) {
                $return['data_categories'][] = $this->recordDataCategoryService->generateExportArray($dc->id);
            }
        }
        if ($entity->description != "") {
            $return["description"] = $entity->description;
        }
        $return["retention_period"] = $entity->retentionPeriod;
        if ($entity->retentionPeriodMode == 0) {
            $return["retention_period_mode"] = "day";
        } else if ($entity->retentionPeriodMode == 1){
            $return["retention_period_mode"] = "month";
        } else {
            $return["retention_period_mode"] = "year";
        }
        if ($entity->retentionPeriodDescription != "") {
            $return["retention_period_description"] = $entity->retentionPeriodDescription;
        }
        return $return;
    }

    /**
     * Imports a record personal data from a data array. This data is generally what has been exported into a file.
     * @param array $data The record personal data's data fields
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr, $recordId)
    {
        $newData = [];
        $newData['anr'] = $anr;
        if(isset($data['data_subjects'])) {
            foreach ($data['data_subjects'] as $ds) {
                $dataSubject = [];
                $dataSubject['id'] = $this->recordDataSubjectService->importFromArray($ds, $anr);
                $newData['dataSubjects'][] = $dataSubject;
            }
        }
        if(isset($data['data_categories'])) {
            foreach ($data['data_categories'] as $dc) {
                $dataCategory = [];
                $dataCategory['id'] = $this->recordDataCategoryService-->importFromArray($dc, $anr);
                $newData['dataCategories'][] = $dataCategory;
            }
        }
        $newData['record'] = $recordId;
        $id = $this->createPersonalData($newData);
        $newData['description'] = (isset($data['description']) ? $data['description'] : '');
        $newData['retentionPeriodDescription'] = (isset($data['retention_period_description']) ? $data['retention_period_description'] : '');
        $newData['retentionPeriod'] = $data['retention_period'];
        if ($data['retention_period_mode'] == "day") {
            $newData["retentionPeriodMode"] = 0;
        } else if ($data['retention_period_mode'] == "month") {
            $newData["retentionPeriodMode"] = 1;
        }  else {
            $newData["retentionPeriodMode"] = 2;
        }
        $this->updatePersonalData($id, $newData);
        return $id;
    }
}
