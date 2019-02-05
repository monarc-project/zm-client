<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
/**
 * This class is the service that handles measures in use within an ANR. Inherits its behavior from its MonarcCore
 * parent class MeasureService
 * @see \MonarcCore\Service\MeasureService
 * @package MonarcFO\Service
 */
class AnrMeasureService extends \MonarcCore\Service\MeasureService
{
    protected $table;
    protected $entity;
    protected $anrTable;
    protected $userAnrTable;
    protected $SoaEntity;
    protected $SoaTable;
    protected $dependencies = ['category' ,'anr', 'referential', 'measuresLinked'];
    protected $forbiddenFields = [];

    /**
     * Creates a new entity of the type of this class, where the fields have the value of the $data array.
     * @param array $data The object's data
     * @param bool $last Whether or not this will be the last element of a batch. Setting this to false will suspend
     *                   flushing to the database to increase performance during batch insertions.
     * @return object The created entity object
     */
    public function create($data, $last = true)
    {
        try{
          $uuid = parent::create($data, $last);
        }
        catch(\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) // we check if the uuid id already existing
        {
          unset($data['uuid']); //if the uuid exist create a new one
          $uuid = parent::create($data, $last)->toString();
        }
        $table = $this->get('table');
        $SoaClass = $this->get('SoaEntity');
        $SoaTable = $this->get('SoaTable');
        $anrTable = $this->get('anrTable');
        $measure = $table->getEntity(['uuid' =>$uuid,'anr'=>$data['anr']]);
        $anr = $anrTable->getEntity($data['anr']);
        $SoaEntity = new $SoaClass();
        $SoaEntity->setMeasure($measure);
        $SoaEntity->setAnr($anr);
        $SoaTable->save($SoaEntity,$last);
    }
}
