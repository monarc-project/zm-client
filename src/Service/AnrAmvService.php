<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\AmvSuperClass;
use Monarc\Core\Service\AmvService;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Table\AmvTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\MeasureTable;
use Monarc\FrontOffice\Model\Table\MonarcObjectTable;
use Monarc\FrontOffice\Model\Table\ThreatTable;
use Monarc\FrontOffice\Model\Table\VulnerabilityTable;
use Ramsey\Uuid\Uuid;

/**
 * This class is the service that handles AMV links in use within an ANR.
 * @see \Monarc\FrontOffice\Model\Entity\Amv
 * @see \Monarc\FrontOffice\Model\Table\AmvTable
 * @package Monarc\FrontOffice\Service
 */
class AnrAmvService extends AmvService
{
    protected $userAnrTable;
    protected $MonarcObjectTable;
    protected $instanceRiskTable;
    protected $measureTable;
    protected $referentialTable;
    protected $amvTable;
    protected $filterColumns = ['status'];
    protected $dependencies = ['anr', 'asset', 'threat', 'vulnerability'];

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null, $filterJoin = null)
    {
        list($filterJoin, $filterLeft, $filtersCol) = $this->get('entity')->getFiltersForService();

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
        list($filterJoin, $filterLeft, $filtersCol) = $this->get('entity')->getFiltersForService();

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
        /** @var AmvTable $amvTable */
        $amvTable = $this->get('table');
        /** @var Amv $amv */
        $amv = $amvTable->findByUuidAndAnrId($id['uuid'], (int)$id['anr']);

        $linkedMeasuresUuids = array_column($data['measures'], 'uuid');
        foreach ($amv->getMeasures() as $measure) {
            $linkedMeasuresUuidKey = array_search($measure->getUuid(), $linkedMeasuresUuids, true);
            if ($linkedMeasuresUuidKey === false) {
                $amv->removeMeasure($measure);
                continue;
            }

            unset($data['measures'][$linkedMeasuresUuidKey]);
        }
        /** @var MeasureTable $measureTable */
        $measureTable = $this->get('measureTable');
        foreach ($data['measures'] as $measure) {
            $amv->addMeasure($measureTable->getEntity($measure));
        }

        $amv->setUpdater($this->getConnectedUser()->getFirstname() . ' ' . $this->getConnectedUser()->getLastname());

        if ($this->isThreatChanged($data, $amv) || $this->isVulnerabilityChanged($data, $amv)) {
            $newAmv = (new Amv())
                ->setUuid(Uuid::uuid4())
                ->setAnr($amv->getAnr())
                ->setAsset($amv->getAsset())
                ->setThreat($amv->getThreat())
                ->setVulnerability($amv->getVulnerability())
                ->setMeasures($amv->getMeasures())
                ->setPosition($amv->getPosition())
                ->setStatus($amv->getStatus())
                ->setCreator($amv->getUpdater());

            if ($this->isThreatChanged($data, $amv)) {
                /** @var ThreatTable $threatTable */
                $threatTable = $this->get('threatTable');
                $threat = $threatTable->findByAnrAndUuid($amv->getAnr(), $data['threat']);
                $newAmv->setThreat($threat);
            }
            if ($this->isVulnerabilityChanged($data, $amv)) {
                /** @var VulnerabilityTable $vulnerabilityTable */
                $vulnerabilityTable = $this->get('vulnerabilityTable');
                $vulnerability = $vulnerabilityTable->findByAnrAndUuid($amv->getAnr(), $data['vulnerability']);
                $newAmv->setVulnerability($vulnerability);
            }

            /** @var InstanceRisk[] $instancesRisks */
            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $instancesRisks = $instanceRiskTable->findByAmv($amv);
            foreach ($instancesRisks as $instanceRisk) {
                $instanceRisk->setThreat($newAmv->getThreat());
                $instanceRisk->setVulnerability($newAmv->getVulnerability());
                $instanceRisk->setAmv($newAmv);
            }

            $amvTable->deleteEntity($amv, false);
            $amv = $newAmv;
        }

        $amvTable->saveEntity($amv);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        /** @var AmvTable $amvTable */
        $amvTable = $this->get('table');
        $amv = $amvTable->findByUuidAndAnrId($id['uuid'], (int)$data['anr']);

        if (isset($data['status'])) {
            $amv->setStatus((int)$data['status']);
        }
        $amv->setUpdater($this->getConnectedUser()->getFirstname() . ' ' . $this->getConnectedUser()->getLastname());

        $amvTable->saveEntity($amv);
    }

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        $class = $this->get('entity');
        /** @var Amv $amv */
        $amv = new $class();
        $amv->setLanguage($this->getLanguage());
        $amv->setDbAdapter($this->get('table')->getDb());

        //manage the measures separately because it's the slave of the relation amv<-->measures
        if (!empty($data['measures'])) {
            foreach ($data['measures'] as $measure) {
                $measureEntity = $this->get('measureTable')->getEntity($measure);
                $measureEntity->addAmv($amv);
            }
            unset($data['measures']);
        }
        $amv->exchangeArray($data);
        unset($data['measures']);
        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($amv, $dependencies);

        $amv->setCreator(
            $this->getConnectedUser()->getFirstname() . ' ' . $this->getConnectedUser()->getLastname()
        );

        /** @var AmvTable $table */
        $table = $this->get('table');
        $id = $table->save($amv, $last);

        // Create instances risks
        /** @var MonarcObjectTable $MonarcObjectTable */
        $MonarcObjectTable = $this->get('MonarcObjectTable');
        $objects = $MonarcObjectTable->getEntityByFields([
            'anr' => $data['anr'],
            'asset' => [
                'uuid' => $amv->getAsset()->getUuid(),
                'anr' => $data['anr']
            ]
        ]);
        foreach ($objects as $object) {
            /** @var InstanceTable $instanceTable */
            $instanceTable = $this->get('instanceTable');
            $instances = $instanceTable->getEntityByFields(['anr' => $data['anr'], 'object' => ['anr' => $data['anr'], 'uuid' => $object->getUuid()]]);
            $i = 1;
            $nbInstances = count($instances);
            foreach ($instances as $instance) {
                $instanceRisk = new InstanceRisk();

                $instanceRisk->setLanguage($this->getLanguage());
                $instanceRisk->setDbAdapter($this->get('table')->getDb());
                $instanceRisk->set('anr', $this->get('anrTable')->getEntity($data['anr']));
                $instanceRisk->set('amv', $amv);
                $instanceRisk->set('asset', $amv->asset);
                $instanceRisk->set('instance', $instance);
                $instanceRisk->set('threat', $amv->threat);
                $instanceRisk->set('vulnerability', $amv->vulnerability);

                $this->get('instanceRiskTable')->save($instanceRisk, ($i == $nbInstances));
                $i++;
            }
        }

        return $id;
    }

    protected function isThreatChanged(array $data, AmvSuperClass $amv): bool
    {
        return $amv->getThreat()->getUuid() !== $data['threat'];
    }

    protected function isVulnerabilityChanged(array $data, AmvSuperClass $amv): bool
    {
        return $amv->getVulnerability()->getUuid() !== $data['vulnerability'];
    }
}
