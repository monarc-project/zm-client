<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Data Subject Service
 *
 * Class AnrRecordDataSubjectService
 * @package MonarcFO\Service
 */
class AnrRecordDataSubjectService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $personalDataTable;

    public function orphanDataSubject($dataSubjectId, $anrId) {
        $personalData = $this->personalDataTable->getEntityByFields(['dataSubjects' => $dataSubjectId, 'anr' => $anrId]);
        if(count($personalData)> 0) {
            return false;
        }
        return true;
    }

    /**
    * Generates the array to be exported into a file when calling {#exportDataSubject}
    * @see #exportDataSubject
    * @param int $id The data subject's id
    * @return array The data array that should be saved
    * @throws \MonarcCore\Exception\Exception If the data subject is not found
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
     * Imports a record data subject from a data array. This data is generally what has been exported into a file.
     * @param array $data The record data subject's data fields
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
            $dataSubjectEntity = $this->get('table')->getEntity($data['id']);
            if ($dataSubjectEntity->get('anr')->get('id') != $anr || $dataSubjectEntity->get('label') != $data['label']) {
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
