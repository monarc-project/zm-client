<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Entity\InstanceRiskOp;
use MonarcFO\Model\Entity\MonarcObject;
use MonarcFO\Model\Entity\RecommandationHistoric;
use MonarcFO\Model\Entity\RecommandationRisk;
use MonarcFO\Model\Table\InstanceRiskOpTable;
use MonarcFO\Model\Table\InstanceRiskTable;
use MonarcFO\Model\Table\InstanceTable;
use MonarcFO\Model\Table\RecommandationHistoricTable;
use MonarcFO\Model\Table\RecommandationRiskTable;
use MonarcFO\Model\Table\RecommandationTable;
use MonarcFO\Service\AbstractService;

/**
 * This class is the service that handles risks' recommendations within an ANR.
 * @package MonarcFO\Service
 */
class AnrRecommandationRiskService extends \MonarcCore\Service\AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $recommandationTable;
    protected $recommandationHistoricTable;
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;
    protected $recommandationHistoricEntity;
    protected $anrService;
    protected $anrInstanceService;
    protected $instanceTable;
    protected $MonarcObjectTable;
    protected $dependencies = [
       'recommandation',  'anr', 'asset', 'threat', 'vulnerability', 'instance', 'instanceRisk', 'instanceRiskOp'
    ];

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        list($filterJoin,$filterLeft,$filtersCol) = $this->get('entity')->getFiltersForService();
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $recosRisks = $table->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd,
            $filterJoin,
            $filterLeft
        );

        foreach ($recosRisks as $key => $recoRisk) {

            if (!empty($recoRisk['recommandation'])) {
                if (empty($recoRisk['recommandation']->duedate) || $recoRisk['recommandation']->duedate == '0000-00-00') {
                } else {
                    if ($recoRisk['recommandation']->duedate instanceof \DateTime) {
                        $recoRisk['recommandation']->duedate = $recoRisk['recommandation']->duedate->getTimestamp();
                    } else {
                        $recoRisk['recommandation']->duedate = strtotime($recoRisk['recommandation']->duedate);
                    }
                    $recoRisk['recommandation']->duedate = date('d-m-Y', $recoRisk['recommandation']->duedate);
                }

                $recoRisk['recommandation']->recommandationSet->anr = $recoRisk['anr']->id; 
                $recosRisks[$key]['recommandation'] = $recoRisk['recommandation'];         
            }
        }

        // Filter out duplicate global objects
        $knownGlobObjId = $objectCache = [];

        if (isset($filterAnd['r.uuid']) && isset($filterAnd['r.anr'])) {
            return array_filter($recosRisks, function ($in) use (&$knownGlobObjId, &$objectCache) {
                $instance = $this->instanceTable->getEntity($in['instance']);
                $objId = $instance->object->uuid->toString();

                if (!isset($knownGlobObjId[$objId][$in['threat']->uuid->toString()][$in['vulnerability']->uuid->toString()])) {
                    $objectCache[$objId] = $instance->object;

                    if ($instance->object->scope == 2) { // SCOPE_GLOBAL
                        $knownGlobObjId[$objId][$in['threat']->uuid->toString()][$in['vulnerability']->uuid->toString()] = $objId;
                    }

                    return true;
                } else {
                    return false;
                }
            });
        } else {
            return $recosRisks;
        }
    }

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        //verify not already exist
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        if ($data['op']) {
            $exist = $table->getEntityByFields([
                'anr' => $data['anr'],
                'recommandation' => ['anr' => $data['anr'], 'uuid' => $data['recommandation']],
                'instanceRiskOp' => $data['risk']
            ]);
            /** @var InstanceRiskOpTable $instanceRiskOpTable */
            $tableUsed = $this->get('instanceRiskOpTable');
        } else {
            $exist = $table->getEntityByFields([
                'anr' => $data['anr'],
                'recommandation' => ['anr' => $data['anr'], 'uuid' => $data['recommandation']],
                'instanceRisk' => $data['risk']
            ]);
            /** @var InstanceRiskTable $instanceRiskTable */
            $tableUsed = $this->get('instanceRiskTable');
        }
        if (count($exist)) {
            throw new \MonarcCore\Exception\Exception('Risk already link to this recommendation', 412);
        }

        $gRisk = $tableUsed->getEntity($data['risk']);
        $id = $this->createRecommandationRisk($data, $gRisk);
        if ($gRisk->getInstance()->getObject()->get('scope') == MonarcObject::SCOPE_GLOBAL && !$data['op']) {

            $instances = $this->get('instanceTable')->getEntityByFields([
              'object' => ['anr' => $gRisk->anr->id, 'uuid' => $gRisk->getInstance()->getObject()->get('uuid')->toString()],
                'anr' => $gRisk->anr->id,
                'id' => ['op' => '!=', 'value' => $gRisk->getInstance()->get('id')],
            ]);
            $instanceIds = [];
            foreach ($instances as $i) {
                $instanceIds[$i->get('id')] = $i->get('id');
            }

            if (!empty($instanceIds)) {
                $brothers = $tableUsed->getEntityByFields([
                    'asset' => ['anr' => $gRisk->anr->id, 'uuid' => $gRisk->asset->uuid->toString()],
                     'threat' => ['anr' => $gRisk->anr->id, 'uuid' => $gRisk->threat->uuid->toString()],
                     'vulnerability' => ['anr' => $gRisk->anr->id, 'uuid' => $gRisk->vulnerability->uuid->toString()],
                    'instance' => ['op' => 'IN', 'value' => $instanceIds],
                    'anr' => $gRisk->anr->id
                ]);

                foreach ($brothers as $brother) {
                    $this->createRecommandationRisk($data, $brother);
                }
            }
        }

        $reco = $this->get('recommandationTable')->getEntity(['anr' => $data['anr'], 'uuid' => $data['recommandation']]);
        $pos = $reco->get('position');
        if (empty($pos) && ($gRisk->get('kindOfMeasure') != null && $gRisk->get('kindOfMeasure') != InstanceRisk::KIND_NOT_TREATED)) {
            // On ajoute cette recommandation au début de la pile
            $recos = $this->get('recommandationTable')->getEntityByFields(['anr' => $reco->get('anr')->get('id'), 'position' => ['op' => 'IS NOT', 'value' => null]], ['position' => 'ASC']);
            foreach ($recos as $r) {
                $r->set('position', $r->get('position') + 1);
                $this->get('recommandationTable')->save($r, false);
            }
            $reco->set('position', 1);
            $this->get('recommandationTable')->save($reco);
        }

        return $id;
    }


    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $recommandationRisk = $table->getEntity($id);

        $idAnr = $recommandationRisk->anr->id;
        $idReco = ['anr' => $recommandationRisk->recommandation->anr->id, 'uuid' => $recommandationRisk->recommandation->uuid->toString()];
        $pos = $recommandationRisk->recommandation->position;

        if ($recommandationRisk->instanceRisk) {
            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $risk = $instanceRiskTable->getEntity($recommandationRisk->instanceRisk->id);

            if ($risk->getInstance()->getObject()->get('scope') == MonarcObject::SCOPE_GLOBAL) {
                if(is_null($risk->amv) && $risk->specific == 1) //case specific amv_id = null
                  $brothers = $instanceRiskTable->getEntityByFields([
                    'asset' => ['anr' => $risk->anr->id, 'uuid' => $risk->asset->uuid->toString()],
                    'threat' => ['anr' => $risk->anr->id, 'uuid' => $risk->threat->uuid->toString()],
                    'vulnerability' => ['anr' => $risk->anr->id, 'uuid' => $risk->vulnerability->uuid->toString()],
                    ]);
                else
                  $brothers = $instanceRiskTable->getEntityByFields(['amv' => ['anr' => $risk->anr->id, 'uuid' => $risk->amv->uuid->toString()], 'anr' => $risk->anr->id]);
                $brothersIds = [];
                foreach ($brothers as $brother) {
                   if ($risk->getInstance()->getObject()->get('uuid')->toString() == $brother->getInstance()->getObject()->get('uuid')->toString()) {
                       $brothersIds[] = $brother->id;
                   }
                }
                $recommandationRisksReco = $table->getEntityByFields(['anr' => $recommandationRisk->anr->id, 'recommandation' => $idReco]);
                foreach ($recommandationRisksReco as $recommandationRiskReco) {
                    if ($recommandationRiskReco->instanceRisk && in_array($recommandationRiskReco->instanceRisk->id, $brothersIds)) {
                        $this->get('table')->delete($recommandationRiskReco->id);
                    }
                }
            } else {
                $this->get('table')->delete($id);
            }
        } else {
            /** @var InstanceRiskOpTable $instanceRiskOpTable */
            $instanceRiskOpTable = $this->get('instanceRiskOpTable');
            $riskOp = $instanceRiskOpTable->getEntity($recommandationRisk->instanceRiskOp->id);

            if ($riskOp->getObject()->get('scope') == MonarcObject::SCOPE_GLOBAL) {
                $brothers = $instanceRiskOpTable->getEntityByFields(['anr' => $riskOp->anr->id, 'rolfRisk' => $riskOp->rolfRisk->id]);
                $brothersIds = [];
                foreach ($brothers as $brother) {
                    $brothersIds[] = $brother->id;
                }

                $recommandationRisksReco = $table->getEntityByFields(['anr' => $recommandationRisk->anr->id, 'recommandation' => $idReco]);
                foreach ($recommandationRisksReco as $recommandationRiskReco) {
                    if ($recommandationRiskReco->instanceRiskOp && in_array($recommandationRiskReco->instanceRiskOp->id, $brothersIds)) {
                        $this->get('table')->delete($recommandationRiskReco->id);
                    }
                }
            } else {
                $this->get('table')->delete($id);
            }
        }

        // Update brother's recommandation position if necessary
        $bros = current($table->getEntityByFields(['anr' => $idAnr,'recommandation'=>$idReco, 'id'=>['op'=>'!=', 'value'=>$id]]));
        if(empty($bros) && $pos > 0){ // is last recorisk
            $reco = $this->get('recommandationTable')->getEntity($idReco);
            
            $recos = $this->get('recommandationTable')->getEntityByFields(['anr'=>$reco->get('anr')->get('id'), 'position' => ['op' => '>', 'value'=>$reco->get('position')]],['position'=>'ASC']);
            foreach($recos as $r){
                $r->set('position',$r->get('position')-1);
                $this->get('recommandationTable')->save($r,false);
            }
            $reco->set('position',null);
            $this->get('recommandationTable')->save($reco);
        }
    }


    /**
     * Computes and returns the treatment plan for the specified ANR and/or recommendation, if the id is set.
     * @param int $anrId The ANR ID
     * @param bool|int $uuid The UUID of a recommendation, or false to retrieve the entire ANR's treatment plan
     * @return mixed An array of recommendations
     */
    public function getTreatmentPlan($anrId, $uuid = false)
    {
        // Retrieve recommandations risks
        /** @var RecommandationTable $table */
        $table = $this->get('table');
        $params = ['anr' => $anrId];
        if ($uuid) {
            $params = ['anr' => $anrId, 'recommandation' => ['anr' => $anrId, 'uuid' => $uuid->toString()]];
        }
        $recommandationsRisks = $table->getEntityByFields($params);

        // Retrieve recommandations
        /** @var RecommandationTable $recommandationTable */
        $recommandationTable = $this->get('recommandationTable');
        $recommandations = $recommandationTable->getEntityByFields(['anr' => $anrId], ['position' => 'ASC', 'importance' => 'DESC']);

        foreach ($recommandations as $key => $recommandation) {
            $recommandations[$key] = $recommandation->getJsonArray();
            $dueDate = $recommandations[$key]['duedate'];
            $recommandations[$key]['duedate'] = (empty($dueDate) || $dueDate == '0000-00-00') ? '' : date('d-m-Y', ($dueDate instanceof \DateTime ? $dueDate->getTimestamp() : strtotime($dueDate)));
            unset($recommandations[$key]['__initializer__']);
            unset($recommandations[$key]['__cloner__']);
            unset($recommandations[$key]['__isInitialized__']);
            $nbRisks = 0;
            $global = [];
            $risksToUnset = [];
            foreach ($recommandationsRisks as $recommandationRisk) {
                if ($recommandationRisk->recommandation->uuid == $recommandation->uuid) {
                    // Retrieve instance risk associated, if any
                    if ($recommandationRisk->instanceRisk && $recommandationRisk->instanceRisk->kindOfMeasure != InstanceRisk::KIND_NOT_TREATED) {
                        $instanceRisk = $recommandationRisk->instanceRisk;
                        if (is_object($instanceRisk->asset)) {
                            $instanceRisk->asset = $instanceRisk->asset->getJsonArray();
                        }
                        if (is_object($instanceRisk->threat)) {
                            $instanceRisk->threat = $instanceRisk->threat->getJsonArray();
                        }
                        if (is_object($instanceRisk->vulnerability)) {
                            $instanceRisk->vulnerability = $instanceRisk->vulnerability->getJsonArray();
                        }
                        $riskData = $instanceRisk->getJsonArray();
                        $riskData['instance'] = $this->instanceTable->getEntity($riskData['instance'])->getJsonArray();
                        $recommandations[$key]['risks'][] = $riskData;
                        $nbRisks++;
                    }

                    // Retrieve instance risk op associated, if any
                    if ($recommandationRisk->instanceRiskOp && $recommandationRisk->instanceRiskOp->kindOfMeasure != InstanceRiskOp::KIND_NOT_TREATED) {
                        $data = $recommandationRisk->instanceRiskOp->getJsonArray();
                        $instance = $recommandationRisk->instanceRiskOp->instance->getJsonArray();
                        unset($instance['__initializer__']);
                        unset($instance['__cloner__']);
                        unset($instance['__isInitialized__']);
                        $data['instance'] = $instance;
                        $recommandations[$key]['risksop'][] = $data;
                        $nbRisks++;
                    }

                    // If the object is global, only keep the highest risk value
                    if ($recommandationRisk->objectGlobal) {
                        foreach ($global as $glob) {
                            if (
                                ($glob['objectId'] == $recommandationRisk->objectGlobal->uuid->toString())
                                &&
                                ($glob['assetId'] == $recommandationRisk->asset->uuid->toString())
                                &&
                                ($glob['threatId'] == $recommandationRisk->threat->uuid->toString())
                                &&
                                ($glob['vulnerabilityId'] == $recommandationRisk->vulnerability->uuid->toString())
                            ) {
                                if ($glob['maxRisk'] < $recommandationRisk->instanceRisk->cacheMaxRisk) {
                                    $value = $glob['riskId'];
                                } else {
                                    $value = $recommandationRisk->instanceRisk->id;
                                }

                                $risksToUnset[$value] = $value;
                            }
                        }

                        $global[] = [
                            'objectId' => $recommandationRisk->objectGlobal->uuid->toString(),
                            'maxRisk' => $recommandationRisk->instanceRisk->cacheMaxRisk,
                            'riskId' => $recommandationRisk->instanceRisk->id,
                            'assetId' => $recommandationRisk->asset->uuid->toString(),
                            'threatId' => $recommandationRisk->threat->uuid->toString(),
                            'vulnerabilityId' => $recommandationRisk->vulnerability->uuid->toString(),
                        ];
                    }
                }
            }

            if (isset($recommandations[$key]['risks'])) {
                foreach ($recommandations[$key]['risks'] as $k => $risk) {
                    if (isset($risksToUnset[$risk['id']])) {
                        unset($recommandations[$key]['risks'][$k]);
                        $nbRisks--;
                    }
                }
            }

            if (!$nbRisks) {
                unset($recommandations[$key]);
            }
        }

        $output = array_values($recommandations);
        usort($output, function ($a, $b) {
            return $a['position'] - $b['position'];
        });


        return $output;
    }


    /**
     * Creates a new recommendation for the provided risk
     * @param array $data The data from the API
     * @param InstanceRisk|InstanceRiskOp $risk The target risk or OP risk
     * @return RecommandationRisk The created/saved recommendation risk
     */
    public function createRecommandationRisk($data, $risk)
    {
      /** @var RecommandationRiskTable $table */
      $table = $this->get('table');

        $class = $this->get('entity');
        $entity = new $class();
        $entity->set('anr', $risk->anr);
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);
        if ($data['op']) {
            $entity->setInstanceRisk(null);
            $entity->setInstanceRiskOp($risk);
            $entity->setAnr(null);
            $table->save($entity);
            $entity->set('anr', $risk->anr); //TO IMPROVE  double save to have the correct anr_id with risk op
        } else {
            $entity->setInstanceRisk($risk);
            $entity->setInstanceRiskOp(null);

            $entity->setAsset($risk->getAsset());
            $entity->setThreat($risk->getThreat());
            $entity->setVulnerability($risk->getVulnerability());
        }

        $entity->setInstance($risk->getInstance());
        if ($risk->getInstance()->getObject()->get('scope') == MonarcObject::SCOPE_GLOBAL) {
            $entity->setObjectGlobal($risk->getInstance()->getObject());
        }

        return $table->save($entity);
    }

    /**
     * Resets the recommendations order for the provided ANR
     * @param int $anrId The ANR ID
     */
    public function initPosition($anrId)
    {
        $recoRisks = $this->get('table')->getEntityByFields([
            'anr' => $anrId,
        ]);

        $uuidReco = [];
        foreach ($recoRisks as $rr) {
            if ($rr->instanceRisk && $rr->instanceRisk->kindOfMeasure != InstanceRisk::KIND_NOT_TREATED) {
                $uuidReco[$rr->recommandation->uuid] = $rr->recommandation->uuid;
            }
            if ($rr->instanceRiskOp && $rr->instanceRiskOp->kindOfMeasure != InstanceRisk::KIND_NOT_TREATED) {
                $uuidReco[$rr->recommandation->uuid] = $rr->recommandation->uuid;
            }
        }

        if (!empty($uuidReco)) {
            // Retrieve recommandations
            /** @var RecommandationTable $recommandationTable */
            $recommandationTable = $this->get('recommandationTable');
            $recommandations = $recommandationTable->getEntityByFields(['anr' => $anrId, 'uuid' => $uuidReco], ['importance' => 'DESC', 'code' => 'ASC']);

            $i = 1;
            $nbRecommandations = count($recommandations);
            foreach ($recommandations as $recommandation) {
                $recommandation->position = $i;
                $recommandationTable->save($recommandation, ($i == $nbRecommandations));
                $i++;
            }
        }
    }

    /**
     * Validates a recommendation risk. Operational risks may not be validated, and will throw an error.
     * @param int $recoRiskId Recommendation risk ID
     * @param string $data The validation data (comment, etc)
     * @throws \MonarcCore\Exception\Exception If the risk is an OP risk, or if the recommendation risk ID is invalid.
     */
    public function validateFor($recoRiskId, $data) {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $recoRisk = $table->getEntity($recoRiskId);

        // validate for operational risks
        if (is_null($recoRisk->instanceRisk)) {
            // Verify if risk is final or intermediate (risk attach to others recommandations)
            $riskRecommandations = $table->getEntityByFields(['instanceRiskOp' => $recoRisk->instanceRiskOp->id]);
            $final = (count($riskRecommandations) == 1);
            // Automatically record the change in history before modifying values
            $this->createRecoRiskOpHistoric($data, $recoRisk, $final);

            if ($final) {
                // Overload observation for volatile comment (after measure)
                $cacheCommentAfter = [];

                /** @var RecommandationHistoricTable $recoHistoTable */
                $recoHistoTable = $this->get('recommandationHistoricTable');
                $riskRecoHistos = $recoHistoTable->getEntityByFields([
                    'instanceRiskOp' => $recoRisk->get('instanceRiskOp')->get('id')
                ], ['id' => 'DESC']);
                $c = 0;

                foreach ($riskRecoHistos as $riskRecoHisto) {
                    /*
                    On ne prend que:
                    - le dernier "final"
                    - et les précédent "non final"
                    */
                    if(!$riskRecoHisto->get('final') || ($riskRecoHisto->get('final') && $c <= 0)){
                        if (strlen($riskRecoHisto->get('cacheCommentAfter'))) {
                            $cacheCommentAfter[] =  $riskRecoHisto->get('cacheCommentAfter');
                        }
                        $c++;
                    }else{
                        break;
                    }
                }
                $instanceRiskOp = $recoRisk->get('instanceRiskOp');
                $instanceRiskOp->comment = implode("\n\n", array_reverse($cacheCommentAfter)); // array_reverse because "['id' => 'DESC']"
                $instanceRiskOp->mitigation = '';
                $instanceRiskOp->netProb = $instanceRiskOp->get('targetedProb');
                $instanceRiskOp->netR = $instanceRiskOp->get('targetedR');
                $instanceRiskOp->netO = $instanceRiskOp->get('targetedO');
                $instanceRiskOp->netL = $instanceRiskOp->get('targetedL');
                $instanceRiskOp->netF = $instanceRiskOp->get('targetedF');
                $instanceRiskOp->netP = $instanceRiskOp->get('targetedP');
                $instanceRiskOp->cacheNetRisk = $instanceRiskOp->get('cacheTargetedRisk');

                $impacts= ['r', 'o', 'l', 'f', 'p'];
                foreach ($impacts as $i) {
                    $icol = 'targeted' . strtoupper($i);
                    $instanceRiskOp->$icol = -1;
                }
                $instanceRiskOp->targetedProb = -1;
                $instanceRiskOp->cacheTargetedRisk = -1;
                $instanceRiskOp->kindOfMeasure = InstanceRiskOp::KIND_NOT_TREATED;

            }
        } else { // validate for information risks

        // Verify if risk is final or intermediate (risk attach to others recommandations)
        $riskRecommandations = $table->getEntityByFields(['instanceRisk' => $recoRisk->instanceRisk->id]);
        $final = (count($riskRecommandations) == 1);

        // Automatically record the change in history before modifying values
        $this->createRecoRiskHistoric($data, $recoRisk, $final);

        if ($final) {
            // Overload observation for volatile comment (after measure)
            $cacheCommentAfter = [];

            /** @var RecommandationHistoricTable $recoHistoTable */
            $recoHistoTable = $this->get('recommandationHistoricTable');
            $riskRecoHistos = $recoHistoTable->getEntityByFields([
                'instanceRisk' => $recoRisk->get('instanceRisk')->get('id')
            ], ['id' => 'DESC']);
            $c = 0;

            foreach ($riskRecoHistos as $riskRecoHisto) {
                /*
                On ne prend que:
                - le dernier "final"
                - et les précédent "non final"
                */
                if(!$riskRecoHisto->get('final') || ($riskRecoHisto->get('final') && $c <= 0)){
                    if (strlen($riskRecoHisto->get('cacheCommentAfter'))) {
                        $cacheCommentAfter[] =  $riskRecoHisto->get('cacheCommentAfter');
                    }
                    $c++;
                }else{
                    break;
                }
            }

            // Update instance risk
            $instanceRisk = $recoRisk->get('instanceRisk');

            $instanceRisk->comment = implode("\n\n", array_reverse($cacheCommentAfter)); // array_reverse because "['id' => 'DESC']"
            $instanceRisk->commentAfter = '';

            // Apply reduction vulnerability on risk
            $oldVulRate = $instanceRisk->get('vulnerabilityRate');
            $newVulnerabilityRate = $instanceRisk->get('vulnerabilityRate') - $instanceRisk->get('reductionAmount');
            $instanceRisk->vulnerabilityRate = ($newVulnerabilityRate >= 0) ? $newVulnerabilityRate : 0;

            $instanceRisk->riskC = $this->getRiskC($instanceRisk->get('instance')->get('c'), $instanceRisk->get('threatRate'), $instanceRisk->get('vulnerabilityRate'));
            $instanceRisk->riskI = $this->getRiskI($instanceRisk->get('instance')->get('i'), $instanceRisk->get('threatRate'), $instanceRisk->get('vulnerabilityRate'));
            $instanceRisk->riskD = $this->getRiskD($instanceRisk->get('instance')->get('d'), $instanceRisk->get('threatRate'), $instanceRisk->get('vulnerabilityRate'));

            $risks = [];
            $impacts = [];
            if ($instanceRisk->threat->c) {
                $risks[] = $instanceRisk->get('riskC');
                $impacts[] = $instanceRisk->get('instance')->get('c');
            }
            if ($instanceRisk->threat->i) {
                $risks[] = $instanceRisk->get('riskI');
                $impacts[] = $instanceRisk->get('instance')->get('i');
            }
            if ($instanceRisk->threat->a) {
                $risks[] = $instanceRisk->get('riskD');
                $impacts[] = $instanceRisk->get('instance')->get('d');
            }

            $instanceRisk->cacheMaxRisk = (count($risks)) ? max($risks) : -1;
            $instanceRisk->cacheTargetedRisk = $this->getTargetRisk($impacts, $instanceRisk->get('threatRate'), $oldVulRate, $instanceRisk->get('reductionAmount'));

            // Set reduction amount to 0
            $instanceRisk->reductionAmount = 0;

            // Change status to NOT_TREATED
            $instanceRisk->kindOfMeasure = InstanceRisk::KIND_NOT_TREATED;

            /** @var InstanceRiskTable $instanceRiskOpTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $instanceRiskTable->save($instanceRisk);

            // Impact on brothers
            if ($recoRisk->objectGlobal) {

                /** @var InstanceTable $instanceTable */
                $instanceTable = $this->get('instanceTable');
                $brothersInstances = $instanceTable->getEntityByFields([
                    'anr' => $recoRisk->get('anr')->get('id'),
                    'object' => ['anr' => $recoRisk->get('anr')->get('id'), 'uuid' => $recoRisk->get('objectGlobal')->get('uuid')->toString()],
                ]);
                foreach ($brothersInstances as $brotherInstance) {

                    $brothersInstancesRisks = $instanceRiskTable->getEntityByFields([
                        'anr' => $recoRisk->get('anr')->get('id'),
                        'asset' => ['anr' => $recoRisk->get('anr')->get('id'), 'uuid' => $instanceRisk->get('asset')->get('uuid')->toString()],
                        'threat' => ['anr' => $recoRisk->get('anr')->get('id'), 'uuid' => $instanceRisk->get('threat')->get('uuid')->toString()],
                        'vulnerability' => ['anr' => $recoRisk->get('anr')->get('id'), 'uuid' => $instanceRisk->get('vulnerability')->get('uuid')->toString()],
                        'instance' => $brotherInstance->get('id'),
                    ]);

                    $i = 1;
                    $nbBrothersInstancesRisks = count($brothersInstancesRisks);
                    foreach ($brothersInstancesRisks as $brotherInstanceRisk) {
                        $brotherInstanceRisk->comment = $instanceRisk->comment;
                        $brotherInstanceRisk->commentAfter = $instanceRisk->commentAfter;
                        $brotherInstanceRisk->vulnerabilityRate = $instanceRisk->vulnerabilityRate;
                        $brotherInstanceRisk->riskC = $instanceRisk->riskC;
                        $brotherInstanceRisk->riskI = $instanceRisk->riskI;
                        $brotherInstanceRisk->riskD = $instanceRisk->riskD;
                        $brotherInstanceRisk->cacheMaxRisk = $instanceRisk->cacheMaxRisk;
                        $brotherInstanceRisk->cacheTargetedRisk = $instanceRisk->cacheTargetedRisk;
                        $brotherInstanceRisk->reductionAmount = $instanceRisk->reductionAmount;
                        $brotherInstanceRisk->kindOfMeasure = $instanceRisk->kindOfMeasure;

                        $instanceRiskTable->save($instanceRisk, ($i == $nbBrothersInstancesRisks));
                        $i++;
                    }
                }
            }
        }
      }

        // Repositioning recommendation in hierarchy
        $this->detach($recoRisk);

        /*
        si c'est le dernier lien de la reco => position = null
        mais cela doit être géré dans le détach ?
        */

        // Save recommandation
        $reco = $recoRisk->get('recommandation');
        $reco->counterTreated = $reco->get('counterTreated') + 1;

        // If is final, clean comment, duedate and responsable
        if ($final) {
            $reco->duedate = null;
            $reco->responsable = '';
            $reco->comment = '';
        }

        /** @var RecommandationTable $recommandationTable */
        $recommandationTable = $this->get('recommandationTable');
        $recommandationTable->save($reco);
    }

    /**
     * Creates an entry in the recommendation's history for information risks to keep a log of changes.
     * @param array $data The history data (comment)
     * @param RecommandationRisk $recoRisk The recommendation risk to historize
     * @param bool $final Whether or not it's the final event
     */
    public function createRecoRiskHistoric($data, $recoRisk, $final)
    {
        $reco = $recoRisk->recommandation;
        $instanceRisk = $recoRisk->instanceRisk;
        $anr = $recoRisk->anr;

        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');

        /** @var AnrInstanceService $anrInstanceService */
        $anrInstanceService = $this->get('anrInstanceService');

        /** @var RecommandationHistoricTable $recoHistoTable */
        $recoHistoTable = $this->get('recommandationHistoricTable');
        $lang = $this->anrTable->getEntity($anr)->language;


        $histo = [
            'final' => $final,
            'implComment' => $data['comment'],
            'recoCode' => $reco->get('code'),
            'recoDescription' => $reco->get('description'),
            'recoImportance' => $reco->get('importance'),
            'recoComment' => $reco->get('comment'),
            'recoDuedate' => $reco->get('duedate'),
            'recoResponsable' => $reco->get('responsable'),
            'riskInstance' => $instanceRisk->get('instance')->get('name' . $lang),
            'riskInstanceContext' => $anrInstanceService->getDisplayedAscendance($instanceRisk->get('instance')),
            'riskAsset' => $instanceRisk->get('asset')->get('label' . $lang),
            'riskThreat' => $instanceRisk->get('threat')->get('label' . $lang),
            'riskThreatVal' => $instanceRisk->get('threatRate'),
            'riskVul' => $instanceRisk->get('vulnerability')->get('label' . $lang),
            'riskVulValBefore' => $instanceRisk->get('vulnerabilityRate'),
            'riskVulValAfter' => ($final) ? max(0, $instanceRisk->get('vulnerabilityRate') - $instanceRisk->get('reductionAmount')) : $instanceRisk->get('vulnerabilityRate'),
            'riskKindOfMeasure' => $instanceRisk->get('kindOfMeasure'),
            'riskCommentBefore' => $instanceRisk->get('comment'),
            'riskCommentAfter' => ($final) ? $recoRisk->get('commentAfter') : $instanceRisk->get('comment'),
            'riskMaxRiskBefore' => $instanceRisk->get('cacheMaxRisk'),
            'riskMaxRiskAfter' => ($final) ? $instanceRisk->get('cacheTargetedRisk') : $instanceRisk->get('cacheMaxRisk'),
            'riskColorBefore' => ($instanceRisk->get('cacheMaxRisk') != -1) ? $anrService->getColor($anr, $instanceRisk->get('cacheMaxRisk')) : '',
            'cacheCommentAfter' => $recoRisk->get('commentAfter'),
            'riskColorAfter' => ($final)
                ?  ((($instanceRisk->get('cacheTargetedRisk') != -1) ? $anrService->getColor($anr, $instanceRisk->get('cacheTargetedRisk')) : ''))
                : (($instanceRisk->get('cacheMaxRisk') != -1) ? $anrService->getColor($anr, $instanceRisk->get('cacheMaxRisk')) : ''),

        ];

        $class = $this->get('recommandationHistoricEntity');

        /** @var RecommandationHistoric $recoHisto */
        $recoHisto = new $class();
        $recoHisto->setLanguage($this->getLanguage());
        $recoHisto->setDbAdapter($this->get('recommandationHistoricTable')->getDb());
        $recoHisto->exchangeArray($histo);

        $recoHisto->anr = $anr;

        $recoHisto->instanceRisk = $instanceRisk;

        $recoHistoTable->save($recoHisto);
    }

    /**
     * Creates an entry in the recommendation's history for operational risks to keep a log of changes.
     * @param array $data The history data (comment)
     * @param RecommandationRisk $recoRisk The recommendation risk to historize
     * @param bool $final Whether or not it's the final event
     */
    public function createRecoRiskOpHistoric($data, $recoRisk, $final)
    {
        $reco = $recoRisk->recommandation;
        $instanceRiskOp = $recoRisk->instanceRiskOp;
        $anr = $recoRisk->anr;

        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');

        /** @var AnrInstanceService $anrInstanceService */
        $anrInstanceService = $this->get('anrInstanceService');

        /** @var RecommandationHistoricTable $recoHistoTable */
        $recoHistoTable = $this->get('recommandationHistoricTable');
        $lang = $this->anrTable->getEntity($anr)->language;


        $histo = [
            'final' => $final,
            'implComment' => $data['comment'],
            'recoCode' => $reco->get('code'),
            'recoDescription' => $reco->get('description'),
            'recoImportance' => $reco->get('importance'),
            'recoComment' => $reco->get('comment'),
            'recoDuedate' => $reco->get('duedate'),
            'recoResponsable' => $reco->get('responsable'),
            'riskInstance' => $instanceRiskOp->get('instance')->get('name' . $lang),
            'riskInstanceContext' => $anrInstanceService->getDisplayedAscendance($instanceRiskOp->get('instance')),
            'riskAsset' => $instanceRiskOp->get('object')->get('asset')->get('label' . $lang),
            'riskOpDescription' => $instanceRiskOp->get('riskCacheLabel' . $lang),
            'netProbBefore' => $instanceRiskOp->get('netProb'),
            'netRBefore' => $instanceRiskOp->get('netR'),
            'netOBefore' => $instanceRiskOp->get('netO'),
            'netLBefore' => $instanceRiskOp->get('netL'),
            'netFBefore' => $instanceRiskOp->get('netF'),
            'netPBefore' => $instanceRiskOp->get('netP'),
            'riskKindOfMeasure' => $instanceRiskOp->get('kindOfMeasure'),
            'riskCommentBefore' => $instanceRiskOp->get('comment'),
            'riskCommentAfter' => ($final) ? $recoRisk->get('commentAfter') : $instanceRiskOp->get('comment'),
            'riskMaxRiskBefore' => $instanceRiskOp->get('cacheNetRisk'),
            'riskMaxRiskAfter' => ($final) ? $instanceRiskOp->get('cacheTargetedRisk') : $instanceRiskOp->get('cacheNetRisk'),
            'riskColorBefore' => ($instanceRiskOp->get('cacheNetRisk')  != -1) ? $anrService->getColorRiskOp($anr, $instanceRiskOp->get('cacheNetRisk')) : '',
            'cacheCommentAfter' => $recoRisk->get('commentAfter'),
            'riskColorAfter' => ($final)
                ?  (($instanceRiskOp->get('cacheTargetedRisk') != -1) ? $anrService->getColorRiskOp($anr, $instanceRiskOp->get('cacheTargetedRisk')) : '')
                : (($instanceRiskOp->get('cacheMaxRisk') != -1) ? $anrService->getColorRiskOp($anr, $instanceRiskOp->get('cacheMaxRisk')) : ''),

        ];

        $class = $this->get('recommandationHistoricEntity');

        /** @var RecommandationHistoric $recoHisto */
        $recoHisto = new $class();
        $recoHisto->setLanguage($this->getLanguage());
        $recoHisto->setDbAdapter($this->get('recommandationHistoricTable')->getDb());
        $recoHisto->exchangeArray($histo);

        $recoHisto->anr = $anr;

        $recoHisto->instanceRiskOp = $instanceRiskOp;

        $recoHistoTable->save($recoHisto);
    }

    /**
     * Detach a recommendation risk from the recommendations
     * @param RecommandationRisk $recommandationRisk The risk to detach
     */
    protected function detach($recommandationRisk)
    {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $idAnr = $recommandationRisk->anr->id;
        $idReco = ['anr' => $recommandationRisk->recommandation->anr->id, 'uuid' => $recommandationRisk->recommandation->uuid->toString()];
        $id = $recommandationRisk->id;


        //global
        if ($recommandationRisk->objectGlobal) {
            $brothersRecommandationsRisks = $table->getEntityByFields([
                'recommandation' => ['anr' => $idAnr, 'uuid' => $recommandationRisk->get('recommandation')->get('uuid')->toString()],
                'objectGlobal' => ['anr' => $idAnr, 'uuid' => $recommandationRisk->get('objectGlobal')->get('uuid')->toString()],
                'asset' =>['anr' => $idAnr, 'uuid' =>  $recommandationRisk->get('asset')->get('uuid')->toString()],
                'threat' => ['anr' => $idAnr, 'uuid' => $recommandationRisk->get('threat')->get('uuid')->toString()],
                'vulnerability' => ['anr' => $idAnr, 'uuid' => $recommandationRisk->get('vulnerability')->get('uuid')->toString()],
            ]);

            $i = 1;
            $nbBrothersRecommandationsRisks = count($brothersRecommandationsRisks);
            foreach ($brothersRecommandationsRisks as $brotherRecommandationRisk) {
                $table->delete($brotherRecommandationRisk->get('id'), ($i == $nbBrothersRecommandationsRisks));
                $i++;
            }
        } else {
            $table->delete($recommandationRisk->id);
        }

        // Update brother's recommandation position if necessary
        $bros = current($table->getEntityByFields(['anr' => $idAnr,'recommandation'=>$idReco, 'id'=>['op'=>'!=', 'value'=>$id]]));
        if(empty($bros)){ // is last recorisk
            $reco = $this->get('recommandationTable')->getEntity($idReco);
            $recos = $this->get('recommandationTable')->getEntityByFields(['anr'=>$reco->get('anr')->get('id'), 'position' => ['op' => '>', 'value'=>$reco->get('position')]],['position'=>'ASC']);
            foreach($recos as $r){
                $r->set('position',$r->get('position')-1);
                $this->get('recommandationTable')->save($r,false);
            }
            $reco->set('position',null);
            $this->get('recommandationTable')->save($reco);
        }
    }

    /**
     * Get Delivery Recommandations Risks
     *
     * @param $anrId
     * @return array|bool
     */
    public function getDeliveryRecommandationsRisks($anrId) {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $recosRisks = $table->getEntityByFields(['anr' => $anrId]);

        return $recosRisks;
    }
}
