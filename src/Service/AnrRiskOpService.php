<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Entity\Asset;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

/**
 * This class is the service that handles operational risks within an ANR.
 * @package Monarc\FrontOffice\Service
 */
class AnrRiskOpService extends AbstractService
{
    use RecommendationsPositionsUpdateTrait;

    protected $filterColumns = [];
    protected $dependencies = ['anr', 'scale'];
    protected $instanceRiskOpService;
    protected $instanceTable;
    protected $rolfRiskTable;
    protected $rolfRiskService;
    protected $monarcObjectTable;
    protected $anrTable;
    protected $userAnrTable;
    protected $translateService;

    /** @var RecommandationTable */
    protected $recommandationTable;

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
            $instanceEntity = $instanceTable->getEntity($instance);
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

        // Get language
        $anr = $this->anrTable->getEntity($anrId);
        $l = $anr->language;

        $sql = "SELECT      ir.id as id, ir.rolf_risk_id as rolfRiskId, ir.`owner` as `owner`, ir.`context` as `context`, ir.risk_cache_label1 as label1, ir.risk_cache_label2 as label2, ir.risk_cache_label3 as label3,
                            ir.risk_cache_label4 as label4, ir.risk_cache_description1 as description1, ir.risk_cache_description2 as description2,
                            ir.risk_cache_description3 as description3, ir.risk_cache_description4 as description4, 
                            ir.cache_net_risk as cacheNetRisk,
                            ir.cache_brut_risk as cacheBrutRisk, ir.kind_of_measure as kindOfMeasure, ir.`comment`, ir.`specific`,
                            ir.cache_targeted_risk as cacheTargetedRisk,
                            IF(ir.kind_of_measure IS NULL OR ir.kind_of_measure = " .  \Monarc\Core\Model\Entity\InstanceRiskOpSuperClass::KIND_NOT_TREATED . ", false, true) as t,
                            i.id as iid, i.name$l, i.position, o.scope, rec.recommendations
                FROM        instances_risks_op as ir
                INNER JOIN  instances as i
                ON          i.id = ir.instance_id
                INNER JOIN  assets as a
                ON          a.uuid = i.asset_id
                AND         a.anr_id = i.anr_id
                INNER JOIN  objects as o
                ON          i.object_id = o.uuid
                AND         i.anr_id = o.anr_id
                LEFT JOIN  (SELECT rr.instance_risk_op_id, rr.anr_id,
                            GROUP_CONCAT(rr.recommandation_id) AS recommendations
                            FROM   recommandations_risks AS rr
                            GROUP BY rr.instance_risk_op_id) AS rec
                ON          ir.id = rec.instance_risk_op_id
                AND         ir.anr_id = rec.anr_id
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

        // FILTER: rolfRisks ==
        if (isset($params['rolfRisks'])) {
          if (!is_array($params['rolfRisks'])) {
            $params['rolfRisks'] = explode(',', substr($params['rolfRisks'],1,-1));
          }
          $sql .= " AND ir.rolf_risk_id IN (:rolfRiskIds) ";
          $queryParams[':rolfRiskIds'] = $params['rolfRisks'];
          $typeParams[':rolfRiskIds'] = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
        }

        // FILTER: kind_of_measure ==
        if (isset($params['kindOfMeasure'])) {
            if ($params['kindOfMeasure'] == \Monarc\Core\Model\Entity\InstanceRiskOpSuperClass::KIND_NOT_TREATED) {
                $sql .= " AND (ir.kind_of_measure IS NULL OR ir.kind_of_measure = :kom) ";
                $queryParams[':kom'] = \Monarc\Core\Model\Entity\InstanceRiskOpSuperClass::KIND_NOT_TREATED;
            } else {
                $sql .= " AND ir.kind_of_measure = :kom ";
                $queryParams[':kom'] = $params['kindOfMeasure'];
            }
        }
        // FILTER: Keywords
        if (!empty($params['keywords'])) {
            $filters = [
                'i.name' . $l . '',
                'i.label' . $l . '',
                'ir.risk_cache_label'. $l . '',
                'ir.risk_cache_description'. $l . '',
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

        // ORDER
        $params['order_direction'] = isset($params['order_direction']) && strtolower(trim($params['order_direction'])) != 'asc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY ";
        switch ($params['order']) {
            case 'instance':
                $sql .= " i.name$l ";
                break;
            case 'position':
                $sql .= " i.position ";
                break;
            case 'brutProb':
                $sql .= " ir.brut_prob ";
                break;
            case 'cacheBrutRisk':
                $sql .= " ir.cache_brut_risk ";
                break;
            // case 'netProb':
            //     $sql .= " ir.net_prob ";
            //     break;
            case 'cacheTargetedRisk':
                $sql .= " ir.cache_targeted_risk ";
                break;
            default:
            case 'cacheNetRisk':
                $sql .= " ir.cache_net_risk ";
                break;
        }
        $sql .= " " . $params['order_direction'] . " , i.name$l ASC ";

        $res = $this->get('table')->getDb()->getEntityManager()->getConnection()
            ->fetchAll($sql, $queryParams, $typeParams);
        foreach($res as &$r){
            $r['instanceInfos'] = [
                'id' => $r['iid'],
                'scope' => $r['scope'],
                'name'. $l => $r['name'. $l],
                ];
            unset($r['iid'],$r['scope'],$r['name'. $l]);
        }
        return $res;
    }

    /**
     * Creates a specific operational risk (a manual risk that is not directly related to an AMV link). It may either
     * be entirely new, or duplicated for another existing operational risk.
     * @param array $data The operational risk details fields
     * @return object The resulting created risk object (entity)
     * @throws Exception If the risk already exists on the instance
     */
    public function createSpecificRiskOp($data)
    {
        $data['specific'] = 1;

        $instance = $this->instanceTable->getEntity($data['instance']);
        $data['instance'] = $instance;
        $data['object'] = $this->monarcObjectTable->getEntity(['anr' => $data['anr'], 'uuid' => $instance->getObject()->getUuid()]);

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
                throw new Exception("This risk already exists in this instance", 412);
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

    public function deleteFromAnr($id, $anrId = null)
    {
        /** @var InstanceRiskOpTable $operationalRiskTable */
        $operationalRiskTable = $this->get('table');
        /** @var InstanceRiskOp $operationalRisk */
        $operationalRisk = $operationalRiskTable->findById($id);

        if (!$operationalRisk->isSpecific()) {
            throw new Exception('You can not delete a not specific risk', 412);
        }

        $result = parent::deleteFromAnr($id, $anrId);

        $this->processRemovedInstanceRiskRecommendationsPositions($operationalRisk);

        return $result;
    }

    /**
     * Return a csv containing the operational risks
     * @param int $anrId The ANR ID
     * @param array|null $instance The instance data array, or null to not filter by instance
     * @param array $params Eventual filters on kindOfMeasure, keywords, thresholds
     * @return a string with all the data CV formated
     */
    public function getCsvRisksOp($anrId, $instance=null, $params=[])
    {
      $translate = $this->get('translateService');
      $risks = $this->getRisksOp($anrId, $instance, $params);
      $lang = $this->anrTable->getEntity($anrId)->language;
      $ShowBrut = $this->anrTable->getEntity($anrId)->showRolfBrut;

      $output = '';
      if (count($risks) > 0) {
          $fields_1 = [
              'instanceInfos' => $translate->translate('Asset', $lang),
              'label'. $lang => $translate->translate('Risk description', $lang),
              ];
          if ($ShowBrut == 1){
          $fields_2 = [
              'brutProb' =>  $translate->translate('Prob.', $lang) . "(" . $translate->translate('Inherent risk', $lang) . ")",
              'brutR' => 'R' . " (" . $translate->translate('Inherent risk', $lang) . ")",
              'brutO' => 'O' . " (" . $translate->translate('Inherent risk', $lang) . ")",
              'brutL' => 'L' . " (" . $translate->translate('Inherent risk', $lang) . ")",
              'brutF' => 'F' . " (" . $translate->translate('Inherent risk', $lang) . ")",
              'brutF' => 'P' . " (" . $translate->translate('Inherent risk', $lang) . ")",
              'cacheBrutRisk' => $translate->translate('Inherent risk', $lang),
              ];
          }
          else {
            $fields_2 = [];
          }
          $fields_3 = [
              'netProb' => $translate->translate('Prob.', $lang) . "(" . $translate->translate('Net risk', $lang) . ")",
              'netR' => 'R' . " (" . $translate->translate('Net risk', $lang) . ")",
              'netO' => 'O' . " (" . $translate->translate('Net risk', $lang) . ")",
              'netL' => 'L' . " (" . $translate->translate('Net risk', $lang) . ")",
              'netF' => 'F' . " (" . $translate->translate('Net risk', $lang) . ")",
              'netF' => 'P' . " (" . $translate->translate('Net risk', $lang) . ")",
              'cacheNetRisk' => $translate->translate('Current risk', $lang) . " (" . $translate->translate('Net risk', $lang) . ")",
              'comment' => $translate->translate('Existing controls', $lang),
              'kindOfMeasure' => $translate->translate('Treatment', $lang),
              'cacheTargetedRisk' => $translate->translate('Residual risk', $lang),
              ];
          $fields = $fields_1 + $fields_2 + $fields_3;

        // Fill in the headers
          $output .= implode(',', array_values($fields)) . "\n";
          foreach ($risks as $risk) {
          foreach ($fields as $k => $v) {
              if ($k == 'kindOfMeasure'){
                  switch ($risk[$k]) {
                    case 1:
                        $array_values[] = 'Reduction';
                        break;
                    case 2:
                        $array_values[] = 'Denied';
                        break;
                    case 3:
                        $array_values[] = 'Accepted';
                        break;
                    default:
                      $array_values[] = 'Not treated';
                  }
                }
                elseif ($k == 'instanceInfos') {
                  $array_values[] = $risk[$k]['name' . $lang];
                }
                elseif ($risk[$k] == '-1'){
                  $array_values[] = null;
                }
                else {
                  $array_values[] = $risk[$k];
                }
            }
          $output .= '"';
          $search = ['"',"\n"];
          $replace = ["'",' '];
          $output .= implode('","', str_replace($search, $replace, $array_values));
          $output .= "\"\r\n";
          $array_values = null;
          }
      }

      return $output;
    }
}
