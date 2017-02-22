<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Entity\Object;
use MonarcFO\Model\Table\InstanceTable;

/**
 * Anr Risk Service
 *
 * Class AnrRiskService
 * @package MonarcFO\Service
 */
class AnrRiskService extends \MonarcCore\Service\AbstractService
{
    protected $filterColumns = [];
    protected $dependencies = ['anr', 'vulnerability', 'threat', 'instance', 'asset'];
    protected $anrTable;
    protected $userAnrTable;
    protected $instanceTable;
    protected $instanceRiskTable;
    protected $threatTable;
    protected $vulnerabilityTable;
    protected $translateService;

    /**
     * Get Risks
     *
     * @param $anrId
     * @param null $instanceId
     * @param array $params
     * @param bool $count
     * @return int
     */
    public function getRisks($anrId, $instanceId = null, $params = [], $count = false)
    {
        $anr = $this->get('anrTable')->getEntity($anrId); // on check que l'ANR existe
        return $this->getInstancesRisks($anr->get('id'), $instanceId, $params, $count);
    }

    /**
     * Get Csv Risks
     *
     * @param $anrId
     * @param null $instanceId
     * @param $params
     * @return string
     */
    public function getCsvRisks($anrId, $instanceId = null, $params)
    {
        return $this->get('table')->getCsvRisks($anrId, $instanceId, $params, $this->get('translateService'), \MonarcCore\Model\Entity\AbstractEntity::FRONT_OFFICE);
    }

    /**
     * Get Instances Risks
     *
     * @param $anrId
     * @param null $instanceId
     * @param array $params
     * @param bool $count
     * @return int
     * @throws \Exception
     */
    protected function getInstancesRisks($anrId, $instanceId = null, $params = [])
    {
        return $this->get('table')->getFilteredInstancesRisks($anrId, $instanceId, $params, \MonarcCore\Model\Entity\AbstractEntity::FRONT_OFFICE);
    }

    /**
     * Create
     *
     * @param $data
     * @param bool $last
     * @return mixed
     * @throws \Exception
     */
    public function create($data, $last = true)
    {
        $data['specific'] = 1;

        // Check that we don't already have a risk with this vuln/threat/instance combo
        $entity = $this->instanceRiskTable->getEntityByFields(['anr' => $data['anr'], 'vulnerability' => $data['vulnerability'], 'threat' => $data['threat'], 'instance' => $data['instance']]);
        if ($entity) {
            throw new \Exception("This risk already exists in this instance", 412);
        }

        $class = $this->get('entity');
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());

        //retrieve asset
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('instanceTable');
        $instance = $instanceTable->getEntity($data['instance']);
        $data['asset'] = $instance->asset->id;

        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        /** @var AnrTable $table */
        $table = $this->get('table');
        $id = $table->save($entity, $last);

        //if global object, save risk of all instance of global object for this anr
        if ($entity->instance->object->scope == Object::SCOPE_GLOBAL) {
            $brothers = $instanceTable->getEntityByFields(['anr' => $entity->anr->id, 'object' => $entity->instance->object->id, 'id' => ['op' => '!=', 'value' => $instance->id]]);
            $i = 1;
            $nbBrothers = count($brothers);
            foreach ($brothers as $brother) {
                $newRisk = clone $entity;
                $newRisk->instance = $brother;
                $table->save($newRisk, ($i == $nbBrothers));
                $i++;
            }
        }

        return $id;
    }

    /**
     * Delete
     *
     * @param $instanceId
     * @param $anrId
     */
    public function deleteInstanceRisks($instanceId, $anrId)
    {
        $risks = $this->getInstanceRisks($instanceId, $anrId);
        $table = $this->get('table');
        $i = 1;
        $nb = count($risks);
        foreach ($risks as $r) {
            $table->delete($r->id, ($i == $nb));
            $i++;
        }
    }

    /**
     * Delete From Anr
     *
     * @param $id
     * @param null $anrId
     * @return mixed
     * @throws \Exception
     */
    public function deleteFromAnr($id, $anrId = null)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity->specific) {
            throw new \Exception('You can not delete a not specific risk', 412);
        }

        if ($entity->anr->id != $anrId) {
            throw new \Exception('Anr id error', 412);
        }

        $connectedUser = $this->get('table')->getConnectedUser();

        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('userAnrTable');
        $rights = $userAnrTable->getEntityByFields(['user' => $connectedUser['id'], 'anr' => $anrId]);
        $rwd = 0;
        foreach ($rights as $right) {
            if ($right->rwd == 1) {
                $rwd = 1;
            }
        }

        if (!$rwd) {
            throw new \Exception('You are not authorized to do this action', 412);
        }

        //if object is global, delete all risks link to brothers instances
        if ($entity->instance->object->scope == Object::SCOPE_GLOBAL) {
            //retrieve brothers
            /** @var InstanceTable $instanceTable */
            $instanceTable = $this->get('instanceTable');
            $brothers = $instanceTable->getEntityByFields(['anr' => $entity->anr->id, 'object' => $entity->instance->object->id]);

            //retrieve instances with same risk
            $instancesRisks = $this->get('table')->getEntityByFields([
                'anr' => $entity->anr->id,
                'asset' => $entity->asset->id,
                'threat' => $entity->threat->id,
                'vulnerability' => $entity->vulnerability->id,
            ]);

            foreach ($instancesRisks as $instanceRisk) {
                foreach ($brothers as $brother) {
                    if ($brother->id == $instanceRisk->instance->id) {
                        if ($instanceRisk->id != $id) {
                            $this->get('table')->delete($instanceRisk->id);
                        }
                    }

                }
            }
        }

        return $this->get('table')->delete($id);
    }
}