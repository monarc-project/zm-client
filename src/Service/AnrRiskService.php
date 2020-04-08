<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

/**
 * This class is the service that handles risks within an ANR.
 * @package Monarc\FrontOffice\Service
 */
class AnrRiskService extends AbstractService
{
    use RecommendationsPositionsUpdateTrait;

    protected $filterColumns = [];
    protected $dependencies = ['anr', 'vulnerability', 'threat', 'instance', 'asset'];
    protected $anrTable;
    protected $userAnrTable;
    protected $instanceTable;
    protected $instanceRiskTable;
    protected $threatTable;
    protected $vulnerabilityTable;
    protected $translateService;

    /** @var RecommandationTable */
    protected $recommandationTable;

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
        $entity = $this->getList(0, 1, null, null, [
            'anr' => $data['anr'],
            'th.anr' => $data['anr'],
            'v.anr' => $data['anr'],
            'v.uuid' => $data['vulnerability']['uuid'],
            'th.uuid' => $data['threat']['uuid'],
            'i.id' => $data['instance']
        ]);
        if (!empty($entity)) {
            throw new Exception("This risk already exists in this instance", 412);
        }

        $class = $this->get('entity');
        /** @var InstanceRisk $entity */
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
        if ($entity->getInstance()->getObject()->getScope() === MonarcObject::SCOPE_GLOBAL) {
            $brothers = $instanceTable->getEntityByFields([
                'anr' => $entity->getAnr()->getId(),
                'object' => [
                    'anr' => $entity->getAnr()->getId(),
                    'uuid' => (string)$entity->getInstance()->getObject()->getUuid()
                ],
                'id' => [
                    'op' => '!=',
                    'value' => $instance->id,
                ]
            ]);
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
     * TODO: check if we use the method or the InstanceRiskService::deleteInstanceRisks is only used.
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
        /** @var InstanceRiskTable $instanceRiskTable */
        $instanceRiskTable = $this->get('table');
        /** @var InstanceRisk $instanceRisk */
        $instanceRisk = $instanceRiskTable->findById($id);

        if (!$instanceRisk->isSpecific()) {
            throw new Exception('You can not delete a not specific risk', 412);
        }

        if ($instanceRisk->getAnr()->getId() !== $anrId) {
            throw new Exception('Anr id error', 412);
        }

        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('userAnrTable');
        $userAnr = $userAnrTable->findByAnrAndUser($instanceRisk->getAnr(), $instanceRiskTable->getConnectedUser());
        if ($userAnr === null || $userAnr->getRwd() === 0) {
            throw new Exception('You are not authorized to do this action', 412);
        }

        // If the object is global, delete all risks link to brothers instances
        if ($instanceRisk->getInstance()->getObject()->isScopeGlobal()) {
            // Retrieve brothers
            /** @var InstanceTable $instanceTable */
            $instanceTable = $this->get('instanceTable');
            /** @var Instance[] $brothers */
            $brothers = $instanceTable->getEntityByFields([
                'anr' => $instanceRisk->getAnr()->getId(),
                'object' => [
                    'anr' => $instanceRisk->getAnr()->getId(),
                    'uuid' => (string)$instanceRisk->getInstance()->getObject()->getUuid(),
                ],
            ]);

            // Retrieve instances with same risk
            $instancesRisks = $instanceRiskTable->getEntityByFields([
                'asset' => [
                    'uuid' => (string)$instanceRisk->getAsset()->getUuid(),
                    'anr' => $instanceRisk->getAnr()->getId(),
                ],
                'threat' => [
                    'uuid' => (string)$instanceRisk->getThreat()->getUuid(),
                    'anr' => $instanceRisk->getAnr()->getId(),
                ],
                'vulnerability' => [
                    'uuid' => (string)$instanceRisk->getVulnerability()->getUuid(),
                    'anr' => $instanceRisk->getAnr()->getId(),
                ],
            ]);

            foreach ($instancesRisks as $instanceRisk) {
                foreach ($brothers as $brother) {
                    if ($brother->getId() === $instanceRisk->getInstance()->getId() && $instanceRisk->getId() !== $id) {
                        $instanceRiskTable->deleteEntity($instanceRisk, false);
                    }
                }
            }
        }

        $instanceRiskTable->deleteEntity($instanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($instanceRisk);

        return true;
    }
}
