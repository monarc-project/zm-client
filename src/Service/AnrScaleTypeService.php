<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\InstanceConsequenceService;
use Monarc\FrontOffice\Model\Entity\ScaleImpactType;
use Monarc\FrontOffice\Model\Table\InstanceTable;

/**
 * This class is the service that handles scales types within an ANR. This is a simple CRUD service.
 * @package Monarc\FrontOffice\Service
 */
class AnrScaleTypeService extends \Monarc\Core\Service\AbstractService
{
    protected $filterColumns = [];
    protected $dependencies = ['anr', 'scale'];
    protected $anrTable;
    protected $userAnrTable;
    protected $scaleTable;
    protected $instanceTable;
    protected $instanceConsequenceService;
    protected $types = [
        1 => 'C',
        2 => 'I',
        3 => 'D',
        4 => 'R',
        5 => 'O',
        6 => 'L',
        7 => 'F',
        8 => 'P',
    ];

    /**
     * Returns the types of scales available
     * @return array [id => type kind string]
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        $types = $this->getTypes();

        $scales = parent::getList($page, $limit, $order, $filter, $filterAnd);


        foreach ($scales as $key => $scale) {

            if (isset($scale['type'])) {
              if (isset($types[$scale['type']])) {
                  $scales[$key]['type'] = $types[$scale['type']];
              } else {
                  $scales[$key]['type'] = 'CUS'; // Custom user-defined column
              }
              $scales[$key]['type_id'] = $scale['type'];
            }
        }

        return $scales;
    }

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        $scales = parent::getList(1,0, null, null, ['anr' => $data['anrId']]);

        if (!isset($data['isSys'])) {
            $data['isSys'] = 0;
        }
        if (!isset($data['isHidden'])) {
            $data['isSys'] = 0;
        }
        if (!isset($data['type'])) {
            $data['type'] = count($scales) + 1;
        }

        $anrId = $data['anr'];

        $class = $this->get('entity');

        /** @var ScaleImpactType $entity */
        $entity = new $class();
        $entity->setDbAdapter($this->get('table')->getDb());

        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        $entity->setLabels($data['labels']);

        $id = $this->get('table')->save($entity);

        // Retrieve all instances for the current ANR
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('instanceTable');
        $instances = $instanceTable->getEntityByFields(['anr' => $anrId]);
        $i = 1;
        $nbInstances = count($instances);
        foreach ($instances as $instance) {
            //create instances consequences
            $dataConsequences = [
                'anr' => $anrId,
                'instance' => $instance->id,
                'object' => $instance->getObject()->getUuid(),
                'scaleImpactType' => $id,
            ];
            /** @var InstanceConsequenceService $instanceConsequenceService */
            $instanceConsequenceService = $this->get('instanceConsequenceService');
            $instanceConsequenceService->create($dataConsequences, ($i == $nbInstances));
            $i++;
        }

        return $id;
    }
}
