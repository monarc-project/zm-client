<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Table\InstanceRiskTable;
use MonarcFO\Model\Table\InstanceTable;
use MonarcFO\Model\Table\MonarcObjectTable;

/**
 * This class is the service that handles AMV links in use within an ANR.
 * @see \MonarcFO\Model\Entity\Amv
 * @see \MonarcFO\Model\Table\AmvTable
 * @package MonarcFO\Service
 */
class AnrAmvService extends \MonarcCore\Service\AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $assetTable;
    protected $threatTable;
    protected $MonarcObjectTable;
    protected $instanceTable;
    protected $instanceRiskTable;
    protected $vulnerabilityTable;
    protected $measureTable;
    protected $filterColumns = ['status'];
    protected $dependencies = ['anr', 'asset', 'threat', 'vulnerability', 'measure[1]()', 'measure[2]()', 'measure[3]()'];

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        list($filterJoin,$filterLeft,$filtersCol) = $this->get('entity')->getFiltersForService();

        return $this->get('table')->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $filtersCol),
            $filterAnd,
            $filterJoin,
            $filterLeft
        );
    }

    /**
     * @inheritdoc
     */
    public function getFilteredCount($filter = null, $filterAnd = null)
    {
        list($filterJoin,$filterLeft,$filtersCol) = $this->get('entity')->getFiltersForService();

        return $this->get('table')->countFiltered(
            $this->parseFrontendFilter($filter, $filtersCol),
            $filterAnd,
            $filterJoin,
            $filterLeft
        );
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $entity = $this->get('table')->getEntity($id);
        if (!$entity) {
            throw new \MonarcCore\Exception\Exception('Entity does not exist', 412);
        }
        if ($entity->get('anr')->get('id') != $data['anr']) {
            throw new \MonarcCore\Exception\Exception('Anr id error', 412);
        }

        $data['asset'] = $entity->get('asset')->get('id'); // asset can not be changed

        $this->filterPostFields($data, $entity);

        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->setLanguage($this->getLanguage());

        if (empty($data)) {
            throw new \MonarcCore\Exception\Exception('Data missing', 412);
        }

        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        //update instance risk associated
        $i = 1;
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('instanceRiskTable');
        $instancesRisks = $instanceRiskTable->getEntityByFields(['amv' => $id]);
        $nbInstancesRisks = count($instancesRisks);
        foreach ($instancesRisks as $instanceRisk) {
            $instanceRisk->threat = $entity->threat;
            $instanceRisk->vulnerability = $entity->vulnerability;
            $instanceRiskTable->save($instanceRisk, ($i == $nbInstancesRisks));
            $i++;
        }

        return $this->get('table')->save($entity);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $entity = $this->get('table')->getEntity($id);
        if (!$entity) {
            throw new \MonarcCore\Exception\Exception('Entity does not exist', 412);
        }
        if ($entity->get('anr')->get('id') != $data['anr']) {
            throw new \MonarcCore\Exception\Exception('Anr id error', 412);
        }

        // on ne permet pas de modifier l'asset
        $data['asset'] = $entity->get('asset')->get('id');

        $entity->setLanguage($this->getLanguage());

        foreach ($this->dependencies as $dependency) {
            if (!isset($data[$dependency]) && $entity->$dependency) {
                $data[$dependency] = $entity->$dependency->id;
            }
        }

        $entity->exchangeArray($data, true);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        return $this->get('table')->save($entity);
    }

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        $class = $this->get('entity');
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        /** @var AnrTable $table */
        $table = $this->get('table');
        $id = $table->save($entity, $last);

        // Create instances risks
        /** @var MonarcObjectTable $MonarcObjectTable */
        $MonarcObjectTable = $this->get('MonarcObjectTable');
        $objects = $MonarcObjectTable->getEntityByFields(['anr' => $data['anr'], 'asset' => $entity->get('asset')->get('id')]);
        foreach ($objects as $object) {
            /** @var InstanceTable $instanceTable */
            $instanceTable = $this->get('instanceTable');
            $instances = $instanceTable->getEntityByFields(['anr' => $data['anr'], 'object' => $object->get('id')]);
            $i = 1;
            $nbInstances = count($instances);
            foreach ($instances as $instance) {
                $instanceRisk = new InstanceRisk();

                $instanceRisk->setLanguage($this->getLanguage());
                $instanceRisk->setDbAdapter($this->get('table')->getDb());
                $instanceRisk->set('anr', $this->get('anrTable')->getEntity($data['anr']));
                $instanceRisk->set('amv', $entity);
                $instanceRisk->set('asset', $entity->asset);
                $instanceRisk->set('instance', $instance);
                $instanceRisk->set('threat', $entity->threat);
                $instanceRisk->set('vulnerability', $entity->vulnerability);

                $this->get('instanceRiskTable')->save($instanceRisk, ($i == $nbInstances));
                $i++;
            }
        }

        return $id;
    }
}
