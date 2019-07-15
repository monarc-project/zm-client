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

    /**
     * Creates a data category for a processing activity if it does not exist in the anr
     * @param array $data The data category details fields
     * @return object The resulting data category object (entity)
     */
    public function createDataCategory($data)
    {
        $dc = $this->get('table')->getEntityByFields(['label' => $data['label'], 'anr' => $data['anr']->get('id')]);
        file_put_contents('php://stderr', print_r(count($dc), TRUE).PHP_EOL);
        if(count($dc)) {
            return $dc[0]->get('id');
        }
        return $this->create($data, true);
    }

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
        file_put_contents('php://stderr', print_r($data, TRUE).PHP_EOL);
        $data['anr'] = $anr;
        $data['label'] = $data['name'];
        if(!isset($data['id'])) {
            $dc = $this->get('table')->getEntityByFields(['label' => $data['label'], 'anr' => $anr]);
            if(count($dc)) {
                $data['id'] = $dc[0]->get('id');
            } else {
                $data['id'] = -1;
            }
        }
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
