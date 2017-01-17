<?php
namespace MonarcFO\Service;

use \Doctrine\ORM\Query\Expr\Join;
use \MonarcFO\Model\Entity\InstanceRisk;
use \MonarcFO\Model\Entity\Object;
use MonarcFO\Model\Table\InstanceRiskTable;
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
    /** @var  InstanceRiskTable */
    protected $instanceRiskTable;
    protected $threatTable;
    protected $vulnerabilityTable;
    protected $translateService;
    
    public function getRisks($anrId, $instanceId = null,$params = []){
        $anr = $this->get('anrTable')->getEntity($anrId); // on check que l'ANR existe
        return $this->getInstancesRisks($anr->get('id'),$instanceId,$params);
    }

    public function getCsvRisks($anrId, $instanceId = null,$params){
        $risks = $this->getRisks($anrId, $instanceId, $params);

        $lang = $this->getLanguage();

        $translate = $this->get('translateService');

        $instancesCache = [];

        $output = '';
        if (count($risks) > 0) {
            $fields = [
                'instanceName'.$lang => $translate->translate('Instance', $lang),
                'c_impact' => $translate->translate('Impact C', $lang),
                'i_impact' => $translate->translate('Impact I', $lang),
                'd_impact' => $translate->translate('Impact D', $lang),
                'threatLabel'.$lang => $translate->translate('Threat', $lang),
                'threatCode' => $translate->translate('Threat code', $lang),
                'threatRate' => $translate->translate('Prob.', $lang),
                'vulnLabel'.$lang => $translate->translate('Vulnerability', $lang),
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

                $risk['instanceName'.$lang] = $instance['name'.$lang];

                foreach($fields as $k => $v){
                    $array_values[] = $risk[$k];
                }
                $output .= '"';
                $output .= implode('","', str_replace('"', '\"', $array_values));
                $output .= "\"\r\n";
            }
        }

        return $output;
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

        $arraySelect = [
            'o.id as oid',
            'ir.id as id',
            'i.id as instanceid',
            'amv.id as amvid',
            'asset.id as assetid',
            'asset.label1 as assetLabel1',
            'asset.label2 as assetLabel2',
            'asset.label3 as assetLabel3',
            'asset.label4 as assetLabel4',
            'asset.description1 as assetDescription1',
            'asset.description2 as assetDescription2',
            'asset.description3 as assetDescription3',
            'asset.description4 as assetDescription4',
            'threat.id as threatid',
            'threat.code as threatCode',
            'threat.label1 as threatLabel1',
            'threat.label2 as threatLabel2',
            'threat.label3 as threatLabel3',
            'threat.label4 as threatLabel4',
            'threat.description1 as threatDescription1',
            'threat.description2 as threatDescription2',
            'threat.description3 as threatDescription3',
            'threat.description4 as threatDescription4',
            'ir.threatRate as threatRate',
            'vulnerability.id as vulnerabilityid',
            'vulnerability.code as vulnCode',
            'vulnerability.label1 as vulnLabel1',
            'vulnerability.label2 as vulnLabel2',
            'vulnerability.label3 as vulnLabel3',
            'vulnerability.label4 as vulnLabel4',
            'vulnerability.description1 as vulnDescription1',
            'vulnerability.description2 as vulnDescription2',
            'vulnerability.description3 as vulnDescription3',
            'vulnerability.description4 as vulnDescription4',
            'ir.vulnerabilityRate as vulnerabilityRate',
            'ir.kindOfMeasure as kindOfMeasure',
            'ir.specific as specific',
            'ir.reductionAmount as reductionAmount',
            'i.c as c_impact',
            'ir.riskC as c_risk',
            'threat.c as c_risk_enabled',
            'i.i as i_impact',
            'ir.riskI as i_risk',
            'threat.i as i_risk_enabled',
            'i.d as d_impact',
            'ir.riskD as d_risk',
            'threat.d as d_risk_enabled',
            'ir.cacheTargetedRisk as target_risk',
            'ir.cacheMaxRisk as max_risk',
            'ir.comment as comment',
            'ir.kindOfMeasure',
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
            'o.scope as scope',
        ];

        $query = $this->get('instanceRiskTable')->getRepository()->createQueryBuilder('ir')
            ->select($arraySelect)
            ->where('i.anr = :anrid')
            ->setParameter(':anrid',$anrId);

        if(empty($instance)){
            // On prend toutes les instances, on est sur l'anr
        }elseif($instance->get('asset') && $instance->get('asset')->get('type') == \MonarcCore\Model\Entity\AssetSuperClass::TYPE_PRIMARY){
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
            ->leftJoin('ir.asset', 'asset')
            ->innerJoin('i.object', 'o')
            ->leftJoin('amv.measure1', 'measure1')
            ->leftJoin('amv.measure2', 'measure2')
            ->leftJoin('amv.measure3', 'measure3')
            ->andWhere('ir.cacheMaxRisk >= -1 '); // seuil

        if (isset($params['kindOfMeasure'])) {
            $query->andWhere('ir.kindOfMeasure = :kom')
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
            $query->andWhere('ir.cacheMaxRisk > :min')
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
            case 'threatRate':
                $query->orderBy('ir.threatRate',$params['order_direction']);
                break;
            case 'targetRisk':
                $query->orderBy('ir.cacheTargetedRisk',$params['order_direction']);
                break;
            default:
            case 'maxRisk':
                $query->orderBy('ir.cacheMaxRisk',$params['order_direction']);
                break;
        }
        if($params['order'] != 'instance'){
            $query->addOrderBy('i.name'.$this->getLanguage(),'ASC');
        }
        $query->addOrderBy('threat.code','ASC')
            ->addOrderBy('vulnerability.code','ASC');
        $result = $query->getQuery()->getScalarResult();

        $globalRisks = $return = [];
        $changes = [
            'instanceid',
            'amvid',
            'assetid',
            'threatid',
            'vulnerabilityid',
        ];

        foreach($result as $r){
            if(isset($globalRisks[$r['oid']][$r['threat']][$r['vulnerability']]) &&
                isset($return[$globalRisks[$r['id']][$r['threat']][$r['vulnerability']]]) &&
                $return[$globalRisks[$r['oid']][$r['threat']][$r['vulnerability']]]['max_risk'] < $r['max_risk']){
                unset($return[$globalRisks[$r['id']][$r['threat']][$r['vulnerability']]]);
                unset($globalRisks[$r['oid']][$r['threat']][$r['vulnerability']]);
            }
            if(!isset($globalRisks[$r['id']][$r['threat']][$r['vulnerability']])){
                $return[$r['id']] = $r;
                $return[$r['id']]['t'] = !((!$r['ir_kindOfMeasure']) || ($r['ir_kindOfMeasure'] == InstanceRisk::KIND_NOT_TREATED));
                unset($return[$r['id']]['ir_kindOfMeasure']);
                foreach($changes as $c){
                    $return[$r['id']][substr($c, 0,-2)] = $return[$r['id']][$c];
                    unset($return[$r['id']][$c]);
                }

                $return[$r['id']]['measure1'] = [
                    'code' => $r['measure1_code'],
                    'description1' => $r['measure1_description1'],
                    'description2' => $r['measure1_description2'],
                    'description3' => $r['measure1_description3'],
                    'description4' => $r['measure1_description4'],
                ];
                $return[$r['id']]['measure2'] = [
                    'code' => $r['measure2_code'],
                    'description1' => $r['measure2_description1'],
                    'description2' => $r['measure2_description2'],
                    'description3' => $r['measure2_description3'],
                    'description4' => $r['measure2_description4'],
                ];
                $return[$r['id']]['measure3'] = [
                    'code' => $r['measure3_code'],
                    'description1' => $r['measure3_description1'],
                    'description2' => $r['measure3_description2'],
                    'description3' => $r['measure3_description3'],
                    'description4' => $r['measure3_description4'],
                ];
                unset(
                    $return[$r['id']]['measure1_code'], $return[$r['id']]['measure1_description1'], $return[$r['id']]['measure1_description2'], $return[$r['id']]['measure1_description3'], $return[$r['id']]['measure1_description4'],
                    $return[$r['id']]['measure2_code'], $return[$r['id']]['measure2_description1'], $return[$r['id']]['measure2_description2'], $return[$r['id']]['measure2_description3'], $return[$r['id']]['measure2_description4'],
                    $return[$r['id']]['measure3_code'], $return[$r['id']]['measure3_description1'], $return[$r['id']]['measure3_description2'], $return[$r['id']]['measure3_description3'], $return[$r['id']]['measure3_description4']
                );
                if($r['scope'] == Object::SCOPE_GLOBAL){
                    $globalRisks[$r['oid']][$r['threat']][$r['vulnerability']] = $r['id'];
                }
            }
        }
        return array_values($return);
    }

    /**
     * Create
     *
     * @param $data
     * @param bool $last
     * @return mixed
     */
    public function create($data, $last = true) {

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

        $dependencies =  (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);


        /** @var AnrTable $table */
        $table = $this->get('table');
        $id =  $table->save($entity, $last);

        //if global object, save risk of all instance of global object for this anr
        if($entity->instance->object->scope == Object::SCOPE_GLOBAL){
            $brothers = $instanceTable->getEntityByFields(['anr' => $entity->anr->id, 'object' => $entity->instance->object->id]);
            $i = 1;
            foreach ($brothers as $brother){
                $last = ($i == count($brothers)) ? true : false;
                $newRisk = clone $entity;
                $newRisk->instance = $brother;
                $table->save($newRisk, $last);
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
    public function deleteInstanceRisks($instanceId, $anrId){
        $risks = $this->getInstanceRisks($instanceId, $anrId);
        $table = $this->get('table');
        $nb = count($risks);
        $i = 1;
        foreach($risks as $r){
            $table->delete($r->id,($i == $nb));
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
    public function deleteFromAnr($id, $anrId = null) {

        $entity = $this->get('table')->getEntity($id);

        if (!$entity->specific){
            throw new \Exception('You can not delete a not specific risk', 412);
        }

        if ($entity->anr->id != $anrId){
            throw new \Exception('Anr id error', 412);
        }

        $connectedUser = $this->get('table')->getConnectedUser();

        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('userAnrTable');
        $rights = $userAnrTable->getEntityByFields(['user' => $connectedUser['id'], 'anr' => $anrId]);
        $rwd = 0;
        foreach($rights as $right) {
            if ($right->rwd == 1) {
                $rwd = 1;
            }
        }

        if (!$rwd) {
            throw new \Exception('You are not authorized to do this action', 412);
        }

        //if object is global, delete all risks link to brothers instances
        if ($entity->instance->object->scope == Object::SCOPE_GLOBAL){
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

            foreach($instancesRisks as $instanceRisk) {
                foreach($brothers as $brother){
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
