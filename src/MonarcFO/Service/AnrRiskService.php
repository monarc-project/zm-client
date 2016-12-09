<?php
namespace MonarcFO\Service;

use \Doctrine\ORM\Query\Expr\Join;
use \MonarcFO\Model\Entity\InstanceRisk;
use \MonarcFO\Model\Entity\Object;

/**
 * Anr Risk Service
 *
 * Class AnrRiskService
 * @package MonarcFO\Service
 */
class AnrRiskService extends \MonarcCore\Service\AbstractService
{
    protected $filterColumns = [];
    protected $dependencies = ['anr', 'scale'];

    protected $anrTable;
    protected $instanceTable;
    protected $instanceRiskTable;
    
    public function getRisks($anrId, $instanceId = null,$params = []){
        $anr = $this->get('anrTable')->getEntity($anrId); // on check que l'ANR existe
        return $this->getInstancesRisks($anr->get('id'),$instanceId,$params);
    }

    public function getCsvRisks($anrId, $instanceId = null,$params){

    }

    protected function getInstancesRisks($anrId, $instanceId = null, $params = []){
        $order = isset($params['order']) ? $params['order'] : 'maxRisk';
        $dir = isset($params['order_direction']) ? $params['order_direction'] : 'desc';

        if(!empty($instanceId)){
            $instance = $this->get('instanceTable')->getEntity($instanceId);
            if($instance->get('anr')->get('id') != $anrId){
                throw new \Exception('Anr ids differents', 412);
            }
        }

        $query = $this->get('instanceRiskTable')->getRepository()->createQueryBuilder('ir')
            ->select([
                'ir', 'amv', 'threat', 'vulnerability', 'i', 'asset', 'o.scope', 'measure1', 'measure2', 'measure3',
            ])
            ->where('i.anr = :anrid')
            ->setParameter(':anrid',$anrId);

        if(empty($instance)){
            // On prend toutes les instances, on est sur l'anr
        }elseif($instance->get('asset') && $instance->get('asset')->get('type') == \MonarcCore\Model\Entity\AssetSuperClass::ASSET_PRIMARY){
            $instanceIds = [];
            $instanceIds[$instance->get('id')] = $instance->get('id');
            $this->get('instanceTable')->initTree($instance);
            $temp = isset($instance->parameters['children']) ? $instance->parameters['children'] : [];
            while( ! empty($temp) ){
                $sub = array_shift($temp);
                $instanceIds[$sub->get('id')] = $sub->get('id');
                if(!empty($sub->parameters['children'])){
                    foreach($sub->parameters['children'] as $subsub){
                        array_unshift($temp, $subsub);
                    }
                }
            }
            $query->andWhere('i.id IN (:ids)')
                ->setParameter(':ids',$instanceIds);
        }else{
            $query->andWhere('i.id = :id')
                ->setParameter(':id',$instance->get('id'));
        }

        $query->innerJoin('ir.instance', 'i')
            ->leftJoin('ir.amv', 'amv')
            ->innerJoin('ir.threat', 'threat')
            ->innerJoin('ir.vulnerability', 'vulnerability')
            ->innerJoin('ir.asset', 'asset')
            ->innerJoin('i.object', 'o')
            ->leftJoin('amv.measure1', 'measure1')
            ->leftJoin('amv.measure2', 'measure2')
            ->leftJoin('amv.measure3', 'measure3')
            ->andWhere('ir.cacheMaxRisk >= -1 '); // seuil

        if (isset($params['kindOfMeasure'])) {
            $query->andWhere('ir.kindOfMeasure != :kom')
                ->setParameter(':kom',$params['kindOfMeasure']);
        }
        if(!empty($params['keywords'])){
            $filters = [
                'asset.label1',
                'asset.label2',
                'asset.label3',
                'asset.label4',
                //'amv.label1',
                //'amv.label2',
                //'amv.label3',
                //'amv.label4',
                'threat.label1',
                'threat.label2',
                'threat.label3',
                'threat.label4',
                'vulnerability.label1',
                'vulnerability.label2',
                'vulnerability.label3',
                'vulnerability.label4',
                'measure1.code',
                'measure1.description1',
                'measure1.description2',
                'measure1.description3',
                'measure1.description4',
                'measure2.code',
                'measure2.description1',
                'measure2.description2',
                'measure2.description3',
                'measure2.description4',
                'measure3.code',
                'measure3.description1',
                'measure3.description2',
                'measure3.description3',
                'measure3.description4',
                'i.name1',
                'i.name2',
                'i.name3',
                'i.name4',
                'ir.comment',
            ];
            $orFilter = [];
            foreach($filters as $f){
                $k = str_replace('.', '', $f);
                $orFilter[] = $f." LIKE :".$k;
                $query->setParameter(":$k",'%'.$params['keywords'].'%');
            }
            $query->andWhere('('.implode(' OR ',$orFilter).')');
        }


        // More filters
        if (isset($params['thresholds']) && $params['thresholds'] > 0) {
            $query->andWhere('((threat.c > 0 OR threat.i > 0 OR threat.d > 0) AND GREATEST(threat.c,threat.i,threat.d) < :min)')
                ->setParameter(':min',$params['thresholds']);
        }

        $params['order_direction'] = isset($params['order_direction']) && strtolower(trim($params['order_direction'])) != 'asc' ? 'DESC' : 'ASC';

        switch($params['order']){
            case 'instance':
                $query->orderBy('i.name'.$this->getLanguage(),$params['order_direction']);
            break;
            case 'auditOrder':
                $query->orderBy('amv.position',$params['order_direction']);
                break;
            case 'c_impact':
                $query->orderBy('i.c',$params['order_direction']);
                break;
            case 'i_impact':
                $query->orderBy('i.i',$params['order_direction']);
                break;
            case 'd_impact':
                $query->orderBy('i.d',$params['order_direction']);
                break;
            case 'threat':
                $query->orderBy('threat.label'.$this->getLanguage(),$params['order_direction']);
                break;
            case 'vulnerability':
                $query->orderBy('vulnerability.label'.$this->getLanguage(),$params['order_direction']);
                break;
            case 'vulnerabilityRate':
                $query->orderBy('ir.vulnerabilityRate',$params['order_direction']);
                break;
            case 'vulnerabilityRate':
                $query->orderBy('ir.cacheTargetedRisk',$params['order_direction']);
                break;
            default:
            case 'maxRisk':
                $query->orderBy('ir.cacheMaxRisk',$params['order_direction']);
                break;
        }
        if($params['order'] != 'instance'){
            $query->orderBy('i.name'.$this->getLanguage(),'ASC');
        }
        $query->orderBy('threat.code','ASC')
            ->orderBy('vulnerability.code','ASC');
        $result = $query->getQuery()->getScalarResult();

        $globalRisks = $return = [];

        foreach($result as $r){
            if(!isset($globalRisks[$r['i_id']][$r['threat_id']][$r['vulnerability_id']])){
                $return[$r['ir_id']] = [
                    'id' => $r['ir_id'],
                    'instance' => $r['i_id'],
                    'amv' => $r['amv_id'],
                    'asset' => $r['asset_id'],
                    'assetLabel1' => $r['asset_label1'],
                    'assetLabel2' => $r['asset_label2'],
                    'assetLabel3' => $r['asset_label3'],
                    'assetLabel4' => $r['asset_label4'],
                    'assetDescription1' => $r['asset_description1'],
                    'assetDescription2' => $r['asset_description2'],
                    'assetDescription3' => $r['asset_description3'],
                    'assetDescription4' => $r['asset_description4'],
                    'threat' => $r['threat_id'],
                    'threatLabel1' => $r['threat_label1'],
                    'threatLabel2' => $r['threat_label2'],
                    'threatLabel3' => $r['threat_label3'],
                    'threatLabel4' => $r['threat_label4'],
                    'threatDescription1' => $r['threat_description1'],
                    'threatDescription2' => $r['threat_description2'],
                    'threatDescription3' => $r['threat_description3'],
                    'threatDescription4' => $r['threat_description4'],
                    'threatRate' => $r['ir_threatRate'],
                    'vulnerability' => $r['vulnerability_id'],
                    'vulnLabel1' => $r['vulnerability_label1'],
                    'vulnLabel2' => $r['vulnerability_label2'],
                    'vulnLabel3' => $r['vulnerability_label3'],
                    'vulnLabel4' => $r['vulnerability_label4'],
                    'vulnDescription1' => $r['vulnerability_description1'],
                    'vulnDescription2' => $r['vulnerability_description2'],
                    'vulnDescription3' => $r['vulnerability_description3'],
                    'vulnDescription4' => $r['vulnerability_description4'],
                    'vulnerabilityRate' => $r['ir_vulnerabilityRate'],
                    'kindOfMeasure' => $r['ir_kindOfMeasure'],
                    'reductionAmount' => $r['ir_reductionAmount'],
                    'c_impact' => $r['i_c'],
                    'c_risk' => $r['ir_riskC'],
                    'c_risk_enabled' => $r['threat_c'],
                    'i_impact' => $r['i_i'],
                    'i_risk' => $r['ir_riskI'],
                    'i_risk_enabled' => $r['threat_i'],
                    'd_impact' => $r['i_d'],
                    'd_risk' => $r['ir_riskD'],
                    'd_risk_enabled' => $r['threat_d'],
                    't' => ((!$r['ir_kindOfMeasure']) || ($r['ir_kindOfMeasure'] == InstanceRisk::KIND_NOT_TREATED)) ? false : true,
                    'target_risk' => $r['ir_cacheTargetedRisk'],
                    'max_risk' => $r['ir_cacheMaxRisk'],
                    'comment' => $r['ir_comment'],
                    'measure1' => [
                        'code' => $r['measure1_code'],
                        'description1' => $r['measure1_description1'],
                        'description2' => $r['measure1_description2'],
                        'description3' => $r['measure1_description3'],
                        'description4' => $r['measure1_description4'],
                    ],
                    'measure2' => [
                        'code' => $r['measure2_code'],
                        'description1' => $r['measure2_description1'],
                        'description2' => $r['measure2_description2'],
                        'description3' => $r['measure2_description3'],
                        'description4' => $r['measure2_description4'],
                    ],
                    'measure3' => [
                        'code' => $r['measure3_code'],
                        'description1' => $r['measure3_description1'],
                        'description2' => $r['measure3_description2'],
                        'description3' => $r['measure3_description3'],
                        'description4' => $r['measure3_description4'],
                    ],
                ];
                if($r['scope']== Object::SCOPE_GLOBAL){
                    $globalRisks[$r['i_id']][$r['threat_id']][$r['vulnerability_id']];
                }
            }
        }
        return $return;
    }
}
