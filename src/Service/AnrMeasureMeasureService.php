<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Entity\MeasureMeasure;

/**
 * AnrMeasureMeasureService Service
 *
 * Class AnrMeasureMeasureService
 * @package Monarc\FrontOffice\Service
 */
class AnrMeasureMeasureService extends AbstractService
{
    protected $table;
    protected $entity;
    protected $anrTable;
    protected $userAnrTable;
    protected $measureEntity;
    protected $measureTable;
    protected $dependencies = ['category', 'anr'];
    protected $forbiddenFields;

    public function create($data, $last = true)
    {
        if ($data['father'] === $data['child']) {
            throw new Exception('You cannot add a component to itself', 412);
        }

        $anrTable = $this->get('anrTable');
        $measureMeasureTable = $this->get('table');
        $measuresMeasures = $measureMeasureTable->getEntityByFields([
            'anr' => $data['anr'],
            'child' => $data['child']['uuid'],
            'father' => $data['father']['uuid']
        ]);

        if (!empty($measuresMeasures)) { // the linkk already exist
            throw new Exception('This component already exist for this object', 412);
        }

        $anr = $anrTable->getEntity($data['anr']);

        /** @var MeasureMeasure $measureMeasure */
        $measureMeasureClass = $this->get('entity');
        $measureMeasure = new $measureMeasureClass();
        $measureMeasure->setAnr($anr);
        $measureMeasure->setFather($data['father']['uuid']);
        $measureMeasure->setChild($data['child']['uuid']);
        $measureMeasureTable->save($measureMeasure, false);
        $measureMeasureReversed = clone $measureMeasure;
        $measureMeasureReversed->setFather($data['child']['uuid']);
        $measureMeasureReversed->setChild($data['father']['uuid']);
        $measureMeasureTable->save($measureMeasureReversed);

        return null;
    }

    public function delete($id)
    {
        $measureTable = $this->get('measureTable');
        $father = $measureTable->getEntity(['uuid' => $id['father'], 'anr' => $id['anr']]);
        $child = $measureTable->getEntity(['uuid' => $id['child'], 'anr' => $id['anr']]);
        $father->removeLinkedMeasure($child);

        $measureTable->save($father);
    }
}
