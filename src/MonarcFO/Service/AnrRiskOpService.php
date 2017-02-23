<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Model\Entity\AbstractEntity;
use MonarcFO\Model\Entity\Asset;
use MonarcFO\Model\Entity\InstanceRiskOp;
use MonarcFO\Model\Table\InstanceRiskOpTable;

/**
 * This class is the service that handles operational risks within an ANR.
 * @package MonarcFO\Service
 */
class AnrRiskOpService extends \MonarcCore\Service\AbstractService
{
    protected $filterColumns = [];
    protected $dependencies = ['anr', 'scale'];
    protected $instanceRiskOpService;
    protected $instanceTable;
    protected $rolfRiskTable;
    protected $rolfRiskService;
    protected $objectTable;
    protected $anrTable;
    protected $userAnrTable;

    /**
     * Helper method to find the specified string in the provided fields within the provided object. The search is
     * a wildcard, case-insensitive search.
     * @param AbstractEntity $obj The entity in which we want to find the search string
     * @param string $search The string to find
     * @param array $fields The fields inside the object in which we want to find the data
     * @return bool true if the string was found, false otherwise.
     */
    protected function findInFields($obj, $search, $fields = [])
    {
        foreach ($fields as $field) {
            if (stripos((is_object($obj) ? $obj->{$field} : $obj[$field]), $search) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Computes and returns the list of operational risks for the provided ANR and instance. The instance may be
     * omitted to retrieve the entire list of operational risks for the entire ANR.
     * @param int $anrId The ANR ID
     * @param array|null $instance The instance data array, or null to not filter by instance
     * @param array $params Eventual filters on kindOfMeasure, keywords, thresholds
     * @return array An array of operational risks
     */
    public function getRisksOp($anrId, $instance = null, $params = [])
    {
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('instanceTable');

        $instances = [];
        if ($instance) {
            $instanceEntity = $instanceTable->getEntity($instance['id']);
            $instances[] = $instanceEntity->id;

            // Get children instances
            $instanceTable->initTree($instanceEntity);
            $temp = isset($instanceEntity->parameters['children']) ? $instanceEntity->parameters['children'] : [];
            while (!empty($temp)) {
                $sub = array_shift($temp);
                $instances[] = $sub->id;
                if($sub->get('asset')->get('type') == Asset::TYPE_PRIMARY){

                }

                if (!empty($sub->parameters['children'])) {
                    foreach ($sub->parameters['children'] as $subsub) {
                        array_unshift($temp, $subsub);
                    }
                }
            }
        }

        $sql = "SELECT      ir.id as id, ir.risk_cache_label1 as label1, ir.risk_cache_label2 as label2, ir.risk_cache_label3 as label3,
                            ir.risk_cache_label4 as label4, ir.risk_cache_description1 as description1, ir.risk_cache_description2 as description2,
                            ir.risk_cache_description3 as description3, ir.risk_cache_description4 as description4, ir.net_prob as netProb, ir.net_r as netR,
                            ir.net_o as netO, ir.net_l as netL, ir.net_f as netF, ir.net_p as netP, ir.cache_net_risk as cacheNetRisk, ir.brut_prob as brutProb,
                            ir.brut_r as brutR, ir.brut_o as brutO, ir.brut_l as brutL, ir.brut_f as brutF, ir.brut_p as brutP,
                            ir.cache_brut_risk as cacheBrutRisk, ir.kind_of_measure as kindOfMeasure, ir.`comment`, ir.`specific`,
                            ir.targeted_prob as targetedProb, ir.targeted_r as targetedR, ir.targeted_o as targetedO, ir.targeted_l as targetedL,
                            ir.targeted_f as targetedF, ir.targeted_p as targetedP, ir.cache_targeted_risk as cacheTargetedRisk,
                            IF(ir.kind_of_measure IS NULL OR ir.kind_of_measure = " .  \MonarcCore\Model\Entity\InstanceRiskOpSuperClass::KIND_NOT_TREATED . ", false, true) as t,
                            i.id as iid, i.name1, i.name2, i.name3, i.name4,
                            o.scope
                FROM        instances_risks_op as ir
                INNER JOIN  instances as i
                ON          i.id = ir.instance_id
                INNER JOIN  assets as a
                ON          a.id = i.asset_id
                INNER JOIN  objects as o
                ON          i.object_id = o.id
                WHERE       ir.anr_id = :anrid
                AND         a.type = :type ";
        $queryParams = [
            ':anrid' => $anrId,
            ':type' => Asset::TYPE_PRIMARY,
        ];
        $typeParams = [];
        if(!empty($instances)){
            $sql .= " AND i.id IN (:ids) ";
            $queryParams[':ids'] = $instances;
            $typeParams[':ids'] = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
        }

        // FILTER: kind_of_measure ==
        if (isset($params['kindOfMeasure'])) {
            if ($params['kindOfMeasure'] == \MonarcCore\Model\Entity\InstanceRiskOpSuperClass::KIND_NOT_TREATED) {
                $sql .= " AND (ir.kind_of_measure IS NULL OR ir.kind_of_measure = :kom) ";
                $queryParams[':kom'] = \MonarcCore\Model\Entity\InstanceRiskOpSuperClass::KIND_NOT_TREATED;
            } else {
                $sql .= " AND ir.kind_of_measure = :kom ";
                $queryParams[':kom'] = $params['kindOfMeasure'];
            }
        }
        // FILTER: Keywords
        if (!empty($params['keywords'])) {
            $filters = [
                'i.label1',
                'i.label2',
                'i.label3',
                'i.label4',
                'ir.risk_cache_label1',
                'ir.risk_cache_label2',
                'ir.risk_cache_label3',
                'ir.risk_cache_label4',
                'ir.risk_cache_description1',
                'ir.risk_cache_description2',
                'ir.risk_cache_description3',
                'ir.risk_cache_description4',
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
        if (isset($params['thresholds']) && $params['thresholds'] != -1) {
            $sql .= " AND ir.cache_net_risk > :min ";
            $queryParams[':min'] = $params['thresholds'];
        }

        $sql .= ' ORDER BY ir.cache_net_risk DESC';
        $res = $this->get('table')->getDb()->getEntityManager()->getConnection()
            ->fetchAll($sql, $queryParams, $typeParams);
        foreach($res as &$r){
            $r['instanceInfos'] = [
                'id' => $r['iid'],
                'scope' => $r['scope'],
                'name1' => $r['name1'],
                'name2' => $r['name2'],
                'name3' => $r['name3'],
                'name4' => $r['name4'], // TODO: ajouter le path de l'instance
            ];
            unset($r['iid'],$r['scope'],$r['name1'],$r['name2'],$r['name3'],$r['name4']);
        }
        return $res;
    }

    /**
     * Creates a specific operational risk (a manual risk that is not directly related to an AMV link). It may either
     * be entirely new, or duplicated for another existing operational risk.
     * @param array $data The operational risk details fields
     * @return object The resulting created risk object (entity)
     * @throws \Exception If the risk already exists on the instance
     */
    public function createSpecificRiskOp($data)
    {
        $data['specific'] = 1;

        $instance = $this->instanceTable->getEntity($data['instance']);
        $data['instance'] = $instance;
        $data['object'] = $this->objectTable->getEntity($instance->object->id);

        if ($data['source'] == 2) {
            // Create a new risk
            $anr = $this->anrTable->getEntity($data['anr']);

            $label = $data['label'];
            $desc = (isset($data['description'])) ? $data['description'] : '';
            unset($data['label']);
            unset($data['description']);

            $riskData = [
                'anr' => $anr,
                'code' => $data['code'],
                'label' . $anr->language => $label,
                'description' . $anr->language => $desc,
            ];
            $data['risk'] = $this->rolfRiskService->create($riskData, true);
        } else {
            // Check if we don't already have it
            /** @var InstanceRiskOpTable $table */
            $table = $this->get('table');
            if ($table->getEntityByFields(['anr' => $data['anr'], 'instance' => $data['instance']->id, 'rolfRisk' => $data['risk']])) {
                throw new \Exception("This risk already exists in this instance", 412);
            }

        }

        // Install an existing risk
        $sourceRiskId = $data['risk'];
        $risk = $this->rolfRiskTable->getEntity($sourceRiskId);

        $data['rolfRisk'] = $risk;
        $data['riskCacheCode'] = $risk->code;
        $data['riskCacheLabel1'] = $risk->label1;
        $data['riskCacheLabel2'] = $risk->label2;
        $data['riskCacheLabel3'] = $risk->label3;
        $data['riskCacheLabel4'] = $risk->label4;
        $data['riskCacheDescription1'] = $risk->description1;
        $data['riskCacheDescription2'] = $risk->description2;
        $data['riskCacheDescription3'] = $risk->description3;
        $data['riskCacheDescription4'] = $risk->description4;

        return $this->create($data, true);
    }

    /**
     * @inheritdoc
     */
    public function deleteFromAnr($id, $anrId = null)
    {
        $entity = $this->get('table')->getEntity($id);

        if (!$entity->specific) {
            throw new \Exception('You can not delete a not specific risk', 412);
        }

        return parent::deleteFromAnr($id, $anrId);
    }
}
