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
        $risks = $this->getRisks($anrId, $instanceId, $params);

        $lang = $this->getLanguage();

        $translate = $this->get('translateService');

        $instancesCache = [];

        $output = '';
        if (count($risks) > 0) {
            $fields = [
                'instanceName' . $lang => $translate->translate('Instance', $lang),
                'c_impact' => $translate->translate('Impact C', $lang),
                'i_impact' => $translate->translate('Impact I', $lang),
                'd_impact' => $translate->translate('Impact D', $lang),
                'threatLabel' . $lang => $translate->translate('Threat', $lang),
                'threatCode' => $translate->translate('Threat code', $lang),
                'threatRate' => $translate->translate('Prob.', $lang),
                'vulnLabel' . $lang => $translate->translate('Vulnerability', $lang),
                'vulnCode' => $translate->translate('Vulnerability code', $lang),
                'vulnerabilityRate' => $translate->translate('Qualif.', $lang),
                'c_risk' => $translate->translate('Current risk C', $lang),
                'i_risk' => $translate->translate('Current risk I', $lang),
                'd_risk' => $translate->translate('Current risk D', $lang),
                'target_risk' => $translate->translate('Target risk', $lang),
            ];

            // Fill in the header
            $output .= implode(',', array_values($fields)) . "\n";

            // Fill in the lines then
            foreach ($risks as $risk) {
                $array_values = [];

                if (isset($instancesCache[$risk['instance']])) {
                    $instance = $instancesCache[$risk['instance']];
                } else {
                    $instance = $this->instanceTable->get($risk['instance']);
                    $instancesCache[$risk['instance']] = $instance;
                }

                $risk['instanceName' . $lang] = $instance['name' . $lang];

                foreach ($fields as $k => $v) {
                    $array_values[] = $risk[$k];
                }
                $output .= '"';
                $output .= implode('","', str_replace('"', '\"', $array_values));
                $output .= "\"\r\n";
            }
        }

        return $output;
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
        $params['order'] = isset($params['order']) ? $params['order'] : 'maxRisk';

        if (!empty($instanceId)) {
            $instance = $this->get('instanceTable')->getEntity($instanceId);
            if ($instance->get('anr')->get('id') != $anrId) {
                throw new \Exception('Anr ids differents', 412);
            }
        }
        $l = $this->get('anrTable')->getEntity($anrId)->get('language');
        $arraySelect = [
            'o.id as oid',
            'ir.id as id',
            'i.id as instance',
            'a.id as amv',
            'ass.id as asset',
            'ass.label' . $l . ' as assetLabel' . $l . '',
            'ass.description' . $l . ' as assetDescription' . $l . '',
            't.id as threat',
            't.code as threatCode',
            't.label' . $l . ' as threatLabel' . $l . '',
            't.description' . $l . ' as threatDescription' . $l . '',
            'ir.threat_rate as threatRate',
            'v.id as vulnerability',
            'v.code as vulnCode',
            'v.label' . $l . ' as vulnLabel' . $l . '',
            'v.description' . $l . ' as vulnDescription' . $l . '',
            'ir.vulnerability_rate as vulnerabilityRate',
            'ir.`specific` as `specific`',
            'ir.reduction_amount as reductionAmount',
            'i.c as c_impact',
            'ir.risk_c as c_risk',
            't.c as c_risk_enabled',
            'i.i as i_impact',
            'ir.risk_i as i_risk',
            't.i as i_risk_enabled',
            'i.d as d_impact',
            'ir.risk_d as d_risk',
            't.d as d_risk_enabled',
            'ir.cache_targeted_risk as target_risk',
            'ir.cache_max_risk as max_risk',
            'ir.comment as comment',
            'CONCAT(m1.code, \' - \', m1.description' . $l . ') as measure1',
            'CONCAT(m2.code, \' - \', m2.description' . $l . ') as measure2',
            'CONCAT(m3.code, \' - \', m3.description' . $l . ') as measure3',
            'o.scope as scope',
            'ir.kind_of_measure as kindOfMeasure',
            'IF(ir.kind_of_measure IS NULL OR ir.kind_of_measure = ' . InstanceRisk::KIND_NOT_TREATED . ', false, true) as t',
            'ir.threat_id as tid',
            'ir.vulnerability_id as vid',
        ];

        $sql = "
            SELECT      " . implode(',', $arraySelect) . "
            FROM        instances_risks AS ir
            INNER JOIN  instances i
            ON          ir.instance_id = i.id
            LEFT JOIN   amvs AS a
            ON          ir.amv_id = a.id
            INNER JOIN  threats AS t
            ON          ir.threat_id = t.id
            INNER JOIN  vulnerabilities AS v
            ON          ir.vulnerability_id = v.id
            LEFT JOIN   assets AS ass
            ON          ir.asset_id = ass.id
            INNER JOIN  objects AS o
            ON          i.object_id = o.id
            LEFT JOIN   measures as m1
            ON          a.measure1_id = m1.id
            LEFT JOIN   measures as m2
            ON          a.measure2_id = m2.id
            LEFT JOIN   measures as m3
            ON          a.measure3_id = m3.id
            WHERE       ir.cache_max_risk >= -1
            AND         ir.anr_id = :anrid ";
        $queryParams = [
            ':anrid' => $anrId,
            //':anrid2' => $anrId,
        ];
        $typeParams = [];
        // Find instance(s) id
        if (empty($instance)) {
            // On prend toutes les instances, on est sur l'anr
            // $sql .= $subSql;
        } elseif ($instance->get('asset') && $instance->get('asset')->get('type') == \MonarcCore\Model\Entity\AssetSuperClass::TYPE_PRIMARY) {
            $instanceIds = [];
            $instanceIds[$instance->get('id')] = $instance->get('id');
            $this->get('instanceTable')->initTree($instance);
            $temp = isset($instance->parameters['children']) ? $instance->parameters['children'] : [];
            while (!empty($temp)) {
                $sub = array_shift($temp);
                $instanceIds[$sub->get('id')] = $sub->get('id');
                if (!empty($sub->parameters['children'])) {
                    foreach ($sub->parameters['children'] as $subsub) {
                        array_unshift($temp, $subsub);
                    }
                }
            }

            $sql .= " AND i.id IN (:ids) ";
            $queryParams[':ids'] = $instanceIds;
            $typeParams[':ids'] = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
        } else {
            $sql .= " AND i.id = :id ";
            $queryParams[':id'] = $instance->get('id');
        }

        // FILTER: kind_of_measure ==
        if (isset($params['kindOfMeasure'])) {
            if ($params['kindOfMeasure'] == \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED) {
                $sql .= " AND (ir.kind_of_measure IS NULL OR ir.kind_of_measure = :kom) ";
                $queryParams[':kom'] = \MonarcCore\Model\Entity\InstanceRiskSuperClass::KIND_NOT_TREATED;
            } else {
                $sql .= " AND ir.kind_of_measure = :kom ";
                $queryParams[':kom'] = $params['kindOfMeasure'];
            }
        }
        // FILTER: Keywords
        if (!empty($params['keywords'])) {
            $filters = [
                'ass.label' . $l . '',
                //'amv.label'.$l.'',
                't.label' . $l . '',
                'v.label' . $l . '',
                'm1.code',
                'm1.description' . $l . '',
                'm2.code',
                'm2.description' . $l . '',
                'm3.code',
                'm3.description' . $l . '',
                'i.name' . $l . '',
                'ir.comment',
            ];
            $orFilter = [];
            foreach ($filters as $f) {
                $k = str_replace('.', '', $f);
                $orFilter[] = $f . " LIKE :" . $k;
                $queryParams[":$k"] = '%' . $params['keywords'] . '%';
            }
            $sql .= " AND (" . implode(' OR ', $orFilter) . ") ";
        }
        // FILTER: cache_max_risk (min)
        if (isset($params['thresholds']) && $params['thresholds'] > 0) {
            $sql .= " AND ir.cache_max_risk > :min ";
            $queryParams[':min'] = $params['thresholds'];
        }

        // ORDER
        $params['order_direction'] = isset($params['order_direction']) && strtolower(trim($params['order_direction'])) != 'asc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY ";
        switch ($params['order']) {
            case 'instance':
                $sql .= " i.name$l ";
                break;
            case 'auditOrder':
                $sql .= " a.position ";
                break;
            case 'c_impact':
                $sql .= " i.c ";
                break;
            case 'i_impact':
                $sql .= " i.i ";
                break;
            case 'd_impact':
                $sql .= " i.d ";
                break;
            case 'threat':
                $sql .= " t.label$l ";
                break;
            case 'vulnerability':
                $sql .= " v.label$l ";
                break;
            case 'vulnerabilityRate':
                $sql .= " ir.vulnerability_rate ";
                break;
            case 'threatRate':
                $sql .= " ir.threat_rate ";
                break;
            case 'targetRisk':
                $sql .= " ir.cache_targeted_risk ";
                break;
            default:
            case 'maxRisk':
                $sql .= " ir.cache_max_risk ";
                break;
        }
        $sql .= " " . $params['order_direction'] . " ";
        if ($params['order'] != 'instance') {
            $sql .= " , i.name$l ASC ";
        }
        $sql .= " , t.code ASC , v.code ASC ";

        $res = $this->get('instanceRiskTable')->getDb()->getEntityManager()->getConnection()
            ->fetchAll($sql, $queryParams, $typeParams);
        $lst = [];
        foreach($res as $r){
            // GROUP BY if scope = GLOBAL
            if($r['scope'] == Object::SCOPE_GLOBAL){
                $key = 'o'.$r['oid'].'-'.$r['tid'].'-'.$r['vid'];
                if(!isset($lst[$key]) || $lst[$key]['max_risk'] < $r['max_risk']){
                    $lst[$key] = $r;
                }
            }else{
                $lst['r'.$r['id']] = $r;
            }
        }
        return array_values($lst);
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