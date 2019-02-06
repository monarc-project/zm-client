<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrMeasureMeasureService Service
 *
 * Class AnrMeasureMeasureService
 * @package MonarcFO\Service
 */
class AnrMeasureMeasureService extends AbstractService
{
    protected $table;
    protected $entity;
    protected $anrTable;
    protected $userAnrTable;
    protected $measureEntity;
    protected $measureTable;
    protected $dependencies = ['category' ,'anr'];
    protected $forbiddenFields = [];

    public function create($data, $last=true)
    {
        $id = null;
        if ($data['father'] == $data['child']) {
            throw new \MonarcCore\Exception\Exception("You cannot add yourself as a component", 412);
        }
        $measureTable = $this->get('measureTable');
        $anrTable = $this->get('anrTable');
        $measureMeasureTable = $this->get('table');
        $measuresMeasures = $measureMeasureTable->getEntityByFields(['anr' => $data['anr'],'child' => $data['child']['uuid'] , 'father' => $data['father']['uuid']]);

        if (count($measuresMeasures)) { // the linkk already exist
            throw new \MonarcCore\Exception\Exception('This component already exist for this object', 412);
        }else {
            $anr = $anrTable->getEntity($data['anr']);
            $class = $this->get('entity');
            $entity = new $class();
            $entity->setLanguage($this->getLanguage());
            $entity->setDbAdapter($this->get('table')->getDb());
            $entity->setAnr($anr);
            $entity->setFather($data['father']['uuid']);
            $entity->setChild($data['child']['uuid']);
            $measureMeasureTable->save($entity, false);
            $entity2 = clone $entity; //make the save in the other way
            $entity2->setFather($data['child']['uuid']);
            $entity2->setChild($data['father']['uuid']);
            $measureMeasureTable->save($entity2);
        }
        return $id;
    }

    public function delete($id)
    {
      $measureTable = $this->get('measureTable');
      $father = $measureTable->getEntity(['uuid'=>$id['father'],'anr'=>$id['anr']]);
      $child = $measureTable->getEntity(['uuid'=>$id['child'],'anr'=>$id['anr']]);
      $father->deleteLinkedMeasure($child);
      $measureTable->save($father);
    }
}
