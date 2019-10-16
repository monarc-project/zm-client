<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Monarc\Core\Service\AbstractService;
use Monarc\Core\Model\Entity\User;


/**
 * This class is the service that handles risks within an ANR.
 * @package Monarc\FrontOffice\Service
 */
class AnrRiskService extends AbstractService
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
     * Computes and returns the list of risks for either the entire ANR, or a specific instance if an ID is set.
     * @param int $anrId The ANR ID
     * @param int|null $instanceId An instance ID, or null to not filter by instance
     * @param array $params An array of fields to filter
     * @param bool $count If true, only the number of risks will be returned
     * @return int|array If $count is true, the number of risks. Otherwise, an array of risks.
     */
    public function getRisks($anrId, $instanceId = null, $params = [], $count = false)
    {
        $anr = $this->get('anrTable')->getEntity($anrId); // on check que l'ANR existe
        return $this->getInstancesRisks($anr->get('id'), $instanceId, $params, $count);
    }

    /**
     * Returns the list of risks in CSV format for either the entire ANR, or a specific instance if an ID is set.
     * @param int $anrId The ANR ID
     * @param int|null $instanceId An instance ID, or null to not filter by instance
     * @param array $params An array of fields to filter
     * @return string CSV-compliant data of the risks list
     */
    public function getCsvRisks($anrId, $instanceId = null, $params = [])
    {
        return $this->get('table')->getCsvRisks($anrId, $instanceId, $params, $this->get('translateService'), \Monarc\Core\Model\Entity\AbstractEntity::FRONT_OFFICE);
    }

    /**
     * Computes and returns the list of risks for either the entire ANR, or a specific instance if an ID is set.
     * @param int $anrId The ANR ID
     * @param int|null $instanceId An instance ID, or null to not filter by instance
     * @param array $params An array of fields to filter
     * @return array An array of risks.
     */
    protected function getInstancesRisks($anrId, $instanceId = null, $params = [])
    {
        return $this->get('table')->getFilteredInstancesRisks($anrId, $instanceId, $params, \Monarc\Core\Model\Entity\AbstractEntity::FRONT_OFFICE);
    }

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        $data['specific'] = 1;

        // Check that we don't already have a risk with this vuln/threat/instance combo
        $entity = $this->getList(0,10,null,null,['anr' => $data['anr'],'th.anr' => $data['anr'],'v.anr' => $data['anr'], 'v.uuid' => $data['vulnerability']['uuid'], 'th.uuid' => $data['threat']['uuid'],'i.id' => $data['instance'] ]);
        //$entity = $this->instanceRiskTable->getEntityByFields(['anr' => $data['anr'], 'vulnerability' => $data['vulnerability'], 'threat' => $data['threat'], 'instance' => $data['instance']]);

        if ($entity) {
            throw new \Monarc\Core\Exception\Exception("This risk already exists in this instance", 412);
        }

        $class = $this->get('entity');
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());

        //retrieve asset
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('instanceTable');
        $instance = $instanceTable->getEntity($data['instance']);
        $data['asset'] = ['uuid' => $instance->asset->uuid->toString(), 'anr' => $data['anr']];
        $entity->exchangeArray($data);
        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        /** @var AnrTable $table */
        $table = $this->get('table');
        $id = $table->save($entity, $last);

        //if global object, save risk of all instance of global object for this anr
        if ($entity->instance->object->scope == MonarcObject::SCOPE_GLOBAL) {
            $brothers = $instanceTable->getEntityByFields(['anr' => $entity->anr->id, 'object' => ['anr' => $entity->anr->id, 'uuid' => $entity->instance->object->uuid->toString()], 'id' => ['op' => '!=', 'value' => $instance->id]]);
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function deleteFromAnr($id, $anrId = null)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity->specific) {
            throw new \Monarc\Core\Exception\Exception('You can not delete a not specific risk', 412);
        }

        if ($entity->anr->id != $anrId) {
            throw new \Monarc\Core\Exception\Exception('Anr id error', 412);
        }

        /** @var User $connectedUser */
        $connectedUser = $this->get('table')->getConnectedUser();

        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('userAnrTable');
        $rights = $userAnrTable->getEntityByFields(['user' => $connectedUser->getId(), 'anr' => $anrId]);
        $rwd = 0;
        foreach ($rights as $right) {
            if ($right->rwd == 1) {
                $rwd = 1;
            }
        }

        if (!$rwd) {
            throw new \Monarc\Core\Exception\Exception('You are not authorized to do this action', 412);
        }

        // If the object is global, delete all risks link to brothers instances
        if ($entity->instance->object->scope == MonarcObject::SCOPE_GLOBAL) {
            // Retrieve brothers
            /** @var InstanceTable $instanceTable */
            $instanceTable = $this->get('instanceTable');
            $brothers = $instanceTable->getEntityByFields(['object' => ['anr' => $entity->anr->id, 'uuid' => $entity->instance->object->uuid->toString()], 'anr' => $entity->anr->id]);

            // Retrieve instances with same risk
            $instancesRisks = $this->get('table')->getEntityByFields([
                'asset' => ['uuid' => $entity->asset->uuid->toString(), 'anr' => $entity->anr->id],
                'threat' => ['uuid'=> $entity->threat->uuid->toString(), 'anr' => $entity->anr->id],
                'vulnerability' => ['uuid'=> $entity->vulnerability->uuid->toString(), 'anr' => $entity->anr->id],
            ]);

            foreach ($instancesRisks as $instanceRisk) {
                foreach ($brothers as $brother) {
                    if ($brother->id == $instanceRisk->instance->id && $instanceRisk->id != $id) {
                        $this->get('table')->delete($instanceRisk->id);
                    }
                }
            }
        }

        return $this->get('table')->delete($id);
    }
}