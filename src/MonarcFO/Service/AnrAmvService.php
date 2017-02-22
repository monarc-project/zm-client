<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Table\InstanceTable;
use MonarcFO\Model\Table\ObjectTable;

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
    protected $objectTable;
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
        $filterJoin = [
            [
                'as' => 'a',
                'rel' => 'asset',
            ],
            [
                'as' => 'th',
                'rel' => 'threat',
            ],
            [
                'as' => 'v',
                'rel' => 'vulnerability',
            ],
        ];
        $filterLeft = [
            [
                'as' => 'm1',
                'rel' => 'measure1',
            ],
            [
                'as' => 'm2',
                'rel' => 'measure2',
            ],
            [
                'as' => 'm3',
                'rel' => 'measure3',
            ],
        ];
        $filtersCol = [];
        $filtersCol[] = 'a.code';
        $filtersCol[] = 'a.label1';
        $filtersCol[] = 'a.label2';
        $filtersCol[] = 'a.label3';
        $filtersCol[] = 'a.description1';
        $filtersCol[] = 'a.description2';
        $filtersCol[] = 'a.description3';
        $filtersCol[] = 'th.code';
        $filtersCol[] = 'th.label1';
        $filtersCol[] = 'th.label2';
        $filtersCol[] = 'th.label3';
        $filtersCol[] = 'th.description1';
        $filtersCol[] = 'th.description2';
        $filtersCol[] = 'th.description3';
        $filtersCol[] = 'v.code';
        $filtersCol[] = 'v.label1';
        $filtersCol[] = 'v.label2';
        $filtersCol[] = 'v.label3';
        $filtersCol[] = 'v.description1';
        $filtersCol[] = 'v.description2';
        $filtersCol[] = 'v.description3';
        $filtersCol[] = 'm1.code';
        $filtersCol[] = 'm1.description1';
        $filtersCol[] = 'm1.description2';
        $filtersCol[] = 'm1.description3';
        $filtersCol[] = 'm2.code';
        $filtersCol[] = 'm2.description1';
        $filtersCol[] = 'm2.description2';
        $filtersCol[] = 'm2.description3';
        $filtersCol[] = 'm3.code';
        $filtersCol[] = 'm3.description1';
        $filtersCol[] = 'm3.description2';
        $filtersCol[] = 'm3.description3';

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
    public function update($id, $data)
    {
        $entity = $this->get('table')->getEntity($id);
        if (!$entity) {
            throw new \Exception('Entity does not exist', 412);
        }
        if ($entity->get('anr')->get('id') != $data['anr']) {
            throw new \Exception('Anr id error', 412);
        }

        $data['asset'] = $entity->get('asset')->get('id'); // on ne permet pas de modifier l'asset

        $this->filterPostFields($data, $entity);

        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->setLanguage($this->getLanguage());

        if (empty($data)) {
            throw new \Exception('Data missing', 412);
        }

        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        return $this->get('table')->save($entity);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $entity = $this->get('table')->getEntity($id);
        if (!$entity) {
            throw new \Exception('Entity does not exist', 412);
        }
        if ($entity->get('anr')->get('id') != $data['anr']) {
            throw new \Exception('Anr id error', 412);
        }

        $data['asset'] = $entity->get('asset')->get('id'); // on ne permet pas de modifier l'asset

        $entity->setLanguage($this->getLanguage());

        foreach ($this->dependencies as $dependency) {
            if (!isset($data[$dependency])) {
                if ($entity->$dependency) {
                    $data[$dependency] = $entity->$dependency->id;
                }
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

        //create instances risks
        /** @var ObjectTable $objectTable */
        $objectTable = $this->get('objectTable');
        $objects = $objectTable->getEntityByFields(['anr' => $data['anr'], 'asset' => $entity->get('asset')->get('id')]);
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