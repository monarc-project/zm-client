<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;

/**
 * AnrRecord Personal Data Service
 *
 * Class AnrRecordPersonalDataService
 * @package Monarc\FrontOffice\Service
 */
class AnrRecordPersonalDataService extends AbstractService
{
    protected $dependencies = ['anr', 'record', 'dataCategories'];
    protected $filterColumns = ['label'];
    /** @var AnrRecordDataCategoryService */
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
        $dataCategories = [];
        if (!empty($data['dataCategories'])) {
            foreach ($data['dataCategories'] as $dataCategory) {
                if (!isset($dataCategory['id'])) {
                    $dc['anr'] = $this->anrTable->findById((int)$data['anr']);
                    $dc['id'] = $this->recordDataCategoryService->createDataCategory($dataCategory);
                }
                $dataCategories[] = $dataCategory['id'];
            }
        }
        $data['dataCategories'] = $dataCategories;

        return $this->create($data);
    }

    public function deletePersonalData($id)
    {
        $personalDataEntity = $this->get('table')->getEntity($id);
        $anrId = $personalDataEntity->getAnr()->getId();
        $dataCategoriesToCheck = [];
        foreach($personalDataEntity->dataCategories as $dc) {
            $dataCategoriesToCheck[] = $dc->id;
        }

        $result = $this->get('table')->delete($id);
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
        $dataCategories = [];
        if (!empty($data['dataCategories'])) {
            foreach ($data['dataCategories'] as $dc) {
                if (!isset($dc['id'])) {
                    $dc['anr'] = $this->anrTable->findById((int)$data['anr']);
                    $dc['id'] = $this->recordDataCategoryService->createDataCategory($dc);
                }
                $dataCategories[] = $dc['id'];
            }
        }
        $data['dataCategories'] = $dataCategories;
        $oldDataCategories = [];
        foreach ($entity->dataCategories as $dc) {
            $oldDataCategories[] = $dc->id;
        }
        $result = $this->update($id, $data);
        foreach($oldDataCategories as $dc) {
            if(!in_array($dc, $dataCategories)
                && $this->recordDataCategoryService->orphanDataCategory($dc, $data['anr'])
            ) {
                $this->recordDataCategoryService->delete(['anr' => $data['anr'], 'id' => $dc]);
            }
        }
        return $result;
    }

    /**
    * Generates the array to be exported into a file when calling {#exportPersonalData}
    * @see #exportPersonalData
    * @param int $id The personalData's id
    * @return array The data array that should be saved
    * @throws \Monarc\Core\Exception\Exception If the personal data is not found
    */
    public function generateExportArray($id)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \Monarc\Core\Exception\Exception('Entity `id` not found.');
        }
        $return = [];
        if ($entity->dataSubject != "") {
            $return["data_subject"] = $entity->dataSubject;
        }
        if($entity->dataCategories) {
            foreach($entity->dataCategories as $dc) {
                $return['data_categories'][] = $this->recordDataCategoryService->generateExportArray($dc->id);
            }
        }
        if ($entity->description != "") {
            $return["description"] = $entity->description;
        }
        $return["retention_period"] = $entity->retentionPeriod;
        if ($entity->retentionPeriodMode == 0) {
            $return["retention_period_mode"] = "day(s)";
        } else if ($entity->retentionPeriodMode == 1){
            $return["retention_period_mode"] = "month(s)";
        } else {
            $return["retention_period_mode"] = "year(s)";
        }
        if ($entity->retentionPeriodDescription != "") {
            $return["retention_period_description"] = $entity->retentionPeriodDescription;
        }
        return $return;
    }

    /**
     * Imports a record personal data from a data array. This data is generally what has been exported into a file.
     * @param array $data The record personal data's data fields
     * @param \Monarc\FrontOffice\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr, $recordId)
    {
        $newData = [];
        $newData['anr'] = $anr;
        if(isset($data['data_categories'])) {
            foreach ($data['data_categories'] as $dc) {
                $dataCategory = [];
                $dataCategory["id"] = $this->recordDataCategoryService->importFromArray($dc, $anr);
                $newData['dataCategories'][] = $dataCategory;
            }
        }
        $newData['record'] = $recordId;
        $id = $this->createPersonalData($newData);
        $newData['dataSubject'] = (isset($data['data_subject']) ? $data['data_subject'] : '');
        $newData['description'] = (isset($data['description']) ? $data['description'] : '');
        $newData['retentionPeriodDescription'] = (isset($data['retention_period_description']) ? $data['retention_period_description'] : '');
        $newData['retentionPeriod'] = $data['retention_period'];
        if (substr($data['retention_period_mode'], 0, 3) == "day") {
            $newData["retentionPeriodMode"] = 0;
        } else if (substr($data['retention_period_mode'], 0, 3) == "mon") {
            $newData["retentionPeriodMode"] = 1;
        }  else {
            $newData["retentionPeriodMode"] = 2;
        }
        $this->updatePersonalData($id, $newData);
        return $id;
    }
}
