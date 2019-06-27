<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Data Category Service
 *
 * Class AnrRecordDataCategoryService
 * @package MonarcFO\Service
 */
class AnrRecordDataCategoryService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $personalDataTable;

    public function orphanDataCategory($dataCategoryId, $anrId) {
        $personalData = $this->personalDataTable->getEntityByFields(['dataCategories' => $dataCategoryId, 'anr' => $anrId]);
        if(count($personalData)> 0) {
            return false;
        }
        return true;
    }

    /**
    * Generates the array to be exported into a file when calling {#exportDataCategory}
    * @see #exportDataCategory
    * @param int $id The data category's id
    * @return array The data array that should be saved
    * @throws \MonarcCore\Exception\Exception If the data category is not found
    */
    public function generateExportArray($id)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity) {
            throw new \MonarcCore\Exception\Exception('Entity `id` not found.');
        }

        $return['id']= $entity->id;
        $return['name']= $entity->label;
        return $return;
    }

    /**
     * Imports a record data category from a data array. This data is generally what has been exported into a file.
     * @param array $data The record data category's data fields
     * @param \MonarcFO\Model\Entity\Anr $anr The target ANR id
     * @return bool|int The ID of the generated asset, or false if an error occurred.
     */
    public function importFromArray($data, $anr)
    {
        $data['anr'] = $anr;
        $data['label'] = $data['name'];
        $id = $data['id'];
        unset($data['name']);
        try {
            $dataCategoryEntity = $this->get('table')->getEntity($data['id']);
            if ($dataCategoryEntity->get('anr')->get('id') != $anr || $dataCategoryEntity->get('label') != $data['label']) {
                unset($data['id']);
                $id = $this->create($data);
            }
        } catch (\MonarcCore\Exception\Exception $e) {
            unset($data['id']);
            $id = $this->create($data);
        }
        return $id;
    }
}
