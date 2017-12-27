<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Entity\InstanceRiskOp;
use MonarcFO\Model\Entity\Object;
use MonarcFO\Model\Entity\RecommandationHistoric;
use MonarcFO\Model\Entity\RecommandationRisk;
use MonarcFO\Model\Table\InstanceRiskOpTable;
use MonarcFO\Model\Table\InstanceRiskTable;
use MonarcFO\Model\Table\InstanceTable;
use MonarcFO\Model\Table\RecommandationHistoricTable;
use MonarcFO\Model\Table\RecommandationMeasureTable;
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
    protected $recommandationMeasureTable;
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;
    protected $recommandationHistoricEntity;
    protected $anrService;
    protected $anrInstanceService;
    protected $instanceTable;
    protected $objectTable;
    protected $dependencies = [
        'anr', 'recommandation', 'asset', 'threat', 'vulnerability', 'instance', 'instanceRisk', 'instanceRiskOp'
    ];

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $recosRisks = $table->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        /** @var RecommandationMeasureTable $recommandationMeasureTable */
        $recommandationMeasureTable = $this->get('recommandationMeasureTable');

        foreach ($recosRisks as $key => $recoRisk) {

            $recommandationsMeasures = $recommandationMeasureTable->getEntityByFields(['recommandation' => $recoRisk['recommandation']->id]);

            $measures = [];
            foreach ($recommandationsMeasures as $recommandationMeasure) {
                $recommandationMeasure = $recommandationMeasure->getJsonArray();
                $recommandationMeasure['measure'] = $recommandationMeasure['measure']->getJsonArray();
                $measures[] = $recommandationMeasure;
            }

            $recosRisks[$key]['measures'] = $measures;

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
                $recosRisks[$key]['recommandation'] = $recoRisk['recommandation'];
            }
        }

        // Filter out duplicate global objects
        $knownGlobObjId = $objectCache = [];

        if (isset($filterAnd['recommandation'])) {
            return array_filter($recosRisks, function ($in) use (&$knownGlobObjId, &$objectCache) {
                $instance = $this->instanceTable->getEntity($in['instance']);
                $objId = $instance->object->id;

                if (!isset($knownGlobObjId[$objId][$in['threat']->id][$in['vulnerability']->id])) {
                    $objectCache[$objId] = $instance->object;

                    if ($instance->object->scope == 2) { // SCOPE_GLOBAL
                        $knownGlobObjId[$objId][$in['threat']->id][$in['vulnerability']->id] = $objId;
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
                'recommandation' => $data['recommandation'],
                'instanceRiskOp' => $data['risk']
            ]);
            /** @var InstanceRiskOpTable $instanceRiskOpTable */
            $tableUsed = $this->get('instanceRiskOpTable');
        } else {
            $exist = $table->getEntityByFields([
                'anr' => $data['anr'],
                'recommandation' => $data['recommandation'],
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
        if ($gRisk->getInstance()->getObject()->get('scope') == Object::SCOPE_GLOBAL && !$data['op']) {

            $instances = $this->get('instanceTable')->getEntityByFields([
                'anr' => $gRisk->anr->id,
                'object' => $gRisk->getInstance()->getObject()->get('id'),
                'id' => ['op' => '!=', 'value' => $gRisk->getInstance()->get('id')],
            ]);
            $instanceIds = [];
            foreach ($instances as $i) {
                $instanceIds[$i->get('id')] = $i->get('id');
            }

            if (!empty($instanceIds)) {
                $brothers = $tableUsed->getEntityByFields([
                    'anr' => $gRisk->anr->id,
                    'instance' => $instanceIds,
                    'asset' => $gRisk->getAsset()->get('id'),
                    'threat' => $gRisk->getThreat()->get('id'),
                    'vulnerability' => $gRisk->getVulnerability()->get('id'),
                ]);
                foreach ($brothers as $brother) {
                    $this->createRecommandationRisk($data, $brother);
                }
            }
        }

        $reco = $this->get('recommandationTable')->getEntity($data['recommandation']);
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
        $idReco = $recommandationRisk->recommandation->id;
        $pos = $recommandationRisk->recommandation->position;

        if ($recommandationRisk->instanceRisk) {
            /** @var InstanceRiskTable $instanceRiskTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $risk = $instanceRiskTable->getEntity($recommandationRisk->instanceRisk->id);

            if ($risk->getInstance()->getObject()->get('scope') == Object::SCOPE_GLOBAL) {
                $brothers = $instanceRiskTable->getEntityByFields(['anr' => $risk->anr->id, 'amv' => $risk->amv->id]);
                $brothersIds = [];
                foreach ($brothers as $brother) {
                    $brothersIds[] = $brother->id;
                }

                $recommandationRisksReco = $table->getEntityByFields(['anr' => $recommandationRisk->anr->id, 'recommandation' => $recommandationRisk->recommandation->id]);
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

            if ($riskOp->getObject()->get('scope') == Object::SCOPE_GLOBAL) {
                $brothers = $instanceRiskOpTable->getEntityByFields(['anr' => $riskOp->anr->id, 'rolfRisk' => $riskOp->rolfRisk->id]);
                $brothersIds = [];
                foreach ($brothers as $brother) {
                    $brothersIds[] = $brother->id;
                }

                $recommandationRisksReco = $table->getEntityByFields(['anr' => $recommandationRisk->anr->id, 'recommandation' => $recommandationRisk->recommandation->id]);
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
     * @param bool|int $id The ID of a recommendation, or false to retrieve the entire ANR's treatment plan
     * @return mixed An array of recommendations
     */
    public function getTreatmentPlan($anrId, $id = false)
    {
        // Retrieve recommandations risks
        /** @var RecommandationTable $table */
        $table = $this->get('table');
        $params = ['anr' => $anrId];
        if ($id) {
            $params['recommandation'] = $id;
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
                if ($recommandationRisk->recommandation->id == $recommandation->id) {
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
                                ($glob['objectId'] == $recommandationRisk->objectGlobal->id)
                                &&
                                ($glob['assetId'] == $recommandationRisk->asset->id)
                                &&
                                ($glob['threatId'] == $recommandationRisk->threat->id)
                                &&
                                ($glob['vulnerabilityId'] == $recommandationRisk->vulnerability->id)
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
                            'objectId' => $recommandationRisk->objectGlobal->id,
                            'maxRisk' => $recommandationRisk->instanceRisk->cacheMaxRisk,
                            'riskId' => $recommandationRisk->instanceRisk->id,
                            'assetId' => $recommandationRisk->asset->id,
                            'threatId' => $recommandationRisk->threat->id,
                            'vulnerabilityId' => $recommandationRisk->vulnerability->id,
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
        $class = $this->get('entity');
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        if ($data['op']) {
            $entity->setInstanceRisk(null);
            $entity->setInstanceRiskOp($risk);
        } else {
            $entity->setInstanceRisk($risk);
            $entity->setInstanceRiskOp(null);

            $entity->setAsset($risk->getAsset());
            $entity->setThreat($risk->getThreat());
            $entity->setVulnerability($risk->getVulnerability());
        }

        $entity->setInstance($risk->getInstance());

        if ($risk->getInstance()->getObject()->get('scope') == Object::SCOPE_GLOBAL) {
            $entity->setObjectGlobal($risk->getInstance()->getObject());
        }

        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');

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

        $idReco = [];
        foreach ($recoRisks as $rr) {
            if ($rr->instanceRisk && $rr->instanceRisk->kindOfMeasure != InstanceRisk::KIND_NOT_TREATED) {
                $idReco[$rr->recommandation->id] = $rr->recommandation->id;
            }
            if ($rr->instanceRiskOp && $rr->instanceRiskOp->kindOfMeasure != InstanceRisk::KIND_NOT_TREATED) {
                $idReco[$rr->recommandation->id] = $rr->recommandation->id;
            }
        }

        if (!empty($idReco)) {
            // Retrieve recommandations
            /** @var RecommandationTable $recommandationTable */
            $recommandationTable = $this->get('recommandationTable');
            $recommandations = $recommandationTable->getEntityByFields(['anr' => $anrId, 'id' => $idReco], ['importance' => 'DESC', 'code' => 'ASC']);

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
    public function validateFor($recoRiskId, $data)
    {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $recoRisk = $table->getEntity($recoRiskId);

        // We can't validate operational risks, only regular risks
        if (is_null($recoRisk->instanceRisk)) {
            throw new \MonarcCore\Exception\Exception('Not possible to validate operational risk', 412);
        }

        // Verify if risk is final or intermediate (risk attach to others recommandations)
        $riskRecommandations = $table->getEntityByFields(['instanceRisk' => $recoRisk->instanceRisk->id]);
        $final = (count($riskRecommandations) == 1);

        // Automatically record the change in history before modifying values
        $this->createRecoHistoric($data, $recoRisk, $final);

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

            $instanceRisk->comment = implode(' > ', array_reverse($cacheCommentAfter)); // array_reverse because "['id' => 'DESC']"
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
            if ($instanceRisk->threat->d) {
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
                    'object' => $recoRisk->get('objectGlobal')->get('id'),
                ]);
                foreach ($brothersInstances as $brotherInstance) {

                    $brothersInstancesRisks = $instanceRiskTable->getEntityByFields([
                        'anr' => $recoRisk->get('anr')->get('id'),
                        'instance' => $brotherInstance->get('id'),
                        'asset' => $instanceRisk->get('asset')->get('id'),
                        'threat' => $instanceRisk->get('threat')->get('id'),
                        'vulnerability' => $instanceRisk->get('vulnerability')->get('id'),
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
     * Creates an entry in the recommendation's history to keep a log of changes.
     * @param array $data The history data (comment)
     * @param RecommandationRisk $recoRisk The recommendation risk to historize
     * @param bool $final Whether or not it's the final event
     */
    public function createRecoHistoric($data, $recoRisk, $final)
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
                ? ((($instanceRisk->get('cacheTargetedRisk') != -1) ? $anrService->getColor($anr, $instanceRisk->get('cacheTargetedRisk')) : ''))
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
     * Detach a recommendation risk from the recommendations
     * @param RecommandationRisk $recommandationRisk The risk to detach
     */
    protected function detach($recommandationRisk)
    {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $idAnr = $recommandationRisk->anr->id;
        $idReco = $recommandationRisk->recommandation->id;
        $id = $recommandationRisk->id;


        //global
        if ($recommandationRisk->objectGlobal) {
            $brothersRecommandationsRisks = $table->getEntityByFields([
                'recommandation' => $recommandationRisk->get('recommandation')->get('id'),
                'objectGlobal' => $recommandationRisk->get('objectGlobal')->get('id'),
                'asset' => $recommandationRisk->get('asset')->get('id'),
                'threat' => $recommandationRisk->get('threat')->get('id'),
                'vulnerability' => $recommandationRisk->get('vulnerability')->get('id'),
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
        $recosRisks = $table->getEntityByFields(['anr' => $anrId], ['recommandation' => 'ASC']);

        return $recosRisks;
    }
}
