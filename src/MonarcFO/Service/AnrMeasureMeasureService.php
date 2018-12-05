<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
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
      file_put_contents('php://stderr', print_r($data, TRUE).PHP_EOL);
      $id = null;
        if ($data['father'] == $data['child']) {
            throw new \MonarcCore\Exception\Exception("You cannot add yourself as a component", 412);
        }
        $measureTable = $this->get('measureTable');
        $anrTable = $this->get('anrTable');
        $measureMeasureTable = $this->get('table');
        $measuresMeasures = $measureMeasureTable->getEntityByFields(['child' => $data['child'] , 'father' => $data['father']]);

        if (count($measuresMeasures)) { // the linkk already exist
            throw new \MonarcCore\Exception\Exception('This component already exist for this object', 412);
        }else {
          $anr = $anrTable->getEntity($data['anr']);
          $father = $measureTable->getEntity($data['father']);
          $child = $measureTable->getEntity($data['child']);
          $entity = $this->get('entity');
          $entity->setAnr($anr);
          $entity->setFather($father);
          $entity->setChild($child);
          $measureMeasureTable->save($entity, false);
          $entity2 = clone $entity; //make the save in the other way
          $entity2->setFather($child);
          $entity2->setChild($father);
          $measureMeasureTable->save($entity2);
        }
        return $id;
    }
}
