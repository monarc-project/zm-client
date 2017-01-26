<?php
namespace MonarcFO\Service;

use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Entity\InstanceRiskOp;
use MonarcFO\Model\Entity\Object;
use MonarcFO\Model\Table\AnrTable;
use MonarcFO\Model\Table\InstanceRiskOpTable;
use MonarcFO\Model\Table\InstanceRiskTable;
use MonarcFO\Model\Table\InstanceTable;
use MonarcFO\Model\Table\ObjectTable;
use MonarcFO\Model\Table\RecommandationHistoricTable;
use MonarcFO\Model\Table\RecommandationMeasureTable;
use MonarcFO\Model\Table\RecommandationRiskTable;
use MonarcFO\Model\Table\RecommandationTable;
use MonarcFO\Service\AbstractService;

/**
 * Anr Recommandation Risk Service
 *
 * Class AnrRecommandationRiskService
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
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
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
        $knownGlobObjId = [];
        $objectCache = [];

        if (isset($filterAnd['recommandation'])) {
            return array_filter($recosRisks, function ($in) use (&$knownGlobObjId, &$objectCache) {
                $instance = $this->instanceTable->getEntity($in['instance']);
                $objId = $instance->object->id;

                if (!in_array($objId, $knownGlobObjId)) {
                    if (!isset($objectCache[$objId])) {
                        $object = $this->objectTable->getEntity($objId);
                        $objectCache[$objId] = $object;
                    } else {
                        $object = $objectCache[$objId];
                    }

                    if ($object->scope == 2) { // SCOPE_GLOBAL
                        $knownGlobObjId[] = $objId;
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
     * Get Treatment Plans
     *
     * @param $anrId
     * @return mixed
     */
    public function getTreatmentPlan($anrId, $id = false)
    {
        //retrieve recommandations risks
        /** @var RecommandationTable $table */
        $table = $this->get('table');
        $params = ['anr' => $anrId];
        if ($id) {
            $params['recommandation'] = $id;
        }
        $recommandationsRisks = $table->getEntityByFields($params);

        //retrieve recommandations
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
                    //retrieve instance risk associated
                    if ($recommandationRisk->instanceRisk) {
                        if ($recommandationRisk->instanceRisk->kindOfMeasure != InstanceRisk::KIND_NOT_TREATED) {
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
                    }
                    //retrieve instance risk op associated
                    if ($recommandationRisk->instanceRiskOp) {
                        if ($recommandationRisk->instanceRiskOp->kindOfMeasure != InstanceRiskOp::KIND_NOT_TREATED) {
                            $data = $recommandationRisk->instanceRiskOp->getJsonArray();
                            $instance = $recommandationRisk->instanceRiskOp->instance->getJsonArray();
                            unset($instance['__initializer__']);
                            unset($instance['__cloner__']);
                            unset($instance['__isInitialized__']);
                            $data['instance'] = $instance;
                            $recommandations[$key]['risksop'][] = $data;
                            $nbRisks++;
                        }
                    }

                    //delete risk of global with risk value is not the higher
                    if ($recommandationRisk->objectGlobal) {
                        foreach ($global as $glob) {
                            if ($glob['objectId'] == $recommandationRisk->objectGlobal->id) {
                                if ($glob['maxRisk'] < $recommandationRisk->instanceRisk->cacheMaxRisk) {
                                    $risksToUnset[$glob['riskId']] = $glob['riskId'];
                                } else {
                                    $risksToUnset[$recommandationRisk->instanceRisk->id] = $recommandationRisk->instanceRisk->id;
                                }
                            }
                        }

                        $global[] = [
                            'objectId' => $recommandationRisk->objectGlobal->id,
                            'maxRisk' => $recommandationRisk->instanceRisk->cacheMaxRisk,
                            'riskId' => $recommandationRisk->instanceRisk->id,
                        ];
                    }
                }
            }

            if (isset($recommandations[$key]['risks'])) {
                foreach ($recommandations[$key]['risks'] as $k => $risk) {
                    if (isset($risksToUnset[$risk['id']])) {
                        unset($recommandations[$key]['risks'][$k]);
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
     * Create
     *
     * @param $data
     * @param bool $last
     * @return mixed|null
     * @throws \Exception
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
            throw new \Exception('Risk already link to this recommendation', 412);
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

        return $id;
    }

    /**
     * Create Recommandation Risk
     *
     * @param $data
     * @param $risk
     * @return mixed|null
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
     * Init Position
     *
     * @param $anrId
     */
    public function initPosition($anrId)
    {
        //retrieve recommandations
        /** @var RecommandationTable $recommandationTable */
        $recommandationTable = $this->get('recommandationTable');
        $recommandations = $recommandationTable->getEntityByFields(['anr' => $anrId], ['importance' => 'DESC']);

        $i = 1;
        $nbRecommandations = count($recommandations);
        foreach ($recommandations as $recommandation) {
            $recommandation->position = $i;
            $recommandationTable->save($recommandation, ($i == $nbRecommandations));
            $i++;
        }
    }

    /**
     * Validate For
     *
     * @param $recoRiskId
     * @param $data
     * @throws \Exception
     */
    public function validateFor($recoRiskId, $data)
    {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $recoRisk = $table->getEntity($recoRiskId);
        if (is_null($recoRisk->instanceRisk)) {
            throw new \Exception('Not possible to validate operational risk', 412);
        }

        //verify if risk is final or intermediate (risk attach to others recommandations)
        $riskRecommandations = $table->getEntityByFields(['instanceRisk' => $recoRisk->instanceRisk->id]);
        $final = (count($riskRecommandations) == 1) ? true : false;

        //repositioning recommendation in hierarchy
        $this->detach($recoRisk, $final);

        //automatically record in history before modify recommendation and risk valuesc
        $this->createRecoHistoric($data, $recoRisk, $final);

        if ($final) {

            //overload constatation for volatile comment (after measure)
            $cacheCommentAfter = '';
            /** @var RecommandationHistoricTable $recoHistoTable */
            $recoHistoTable = $this->get('recommandationHistoricTable');
            $riskRecoHistos = $recoHistoTable->getEntityByFields([
                'instanceRisk' => $recoRisk->get('instanceRisk')->get('id')
            ]);
            foreach ($riskRecoHistos as $riskRecoHisto) {
                if (strlen($cacheCommentAfter) && strlen($riskRecoHisto->get('cacheCommentAfter'))) {
                    $cacheCommentAfter .= '<br>' . $riskRecoHisto->get('cacheCommentAfter');
                } else if (strlen($cacheCommentAfter) == 0) {
                    $cacheCommentAfter = $riskRecoHisto->get('cacheCommentAfter');
                }
            }

            //update instance risk
            $instanceRisk = $recoRisk->get('instanceRisk');
            $instanceRisk->comment = $cacheCommentAfter;
            $instanceRisk->commentAfter = '';

            //apply reduction vulnerability on risk
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
            $instanceRisk->cacheTargetedRisk = $this->getTargetRisk($impacts, $instanceRisk->get('threatRate'), $instanceRisk->get('vulnerabilityRate'), $instanceRisk->get('reductionAmount'));

            //set reduction amount to 0
            $instanceRisk->reductionAmount = 0;

            //change status to NOT_TREATED
            $instanceRisk->kindOfMeasure = InstanceRisk::KIND_NOT_TREATED;

            /** @var InstanceRiskTable $instanceRiskOpTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $instanceRiskTable->save($instanceRisk);

            //impact on brothers
            if ($recoRisk->objectGlobal) {

                /** @var InstanceTable $instanceTable */
                $instanceTable = $this->get('instanceTable');
                $brothersInstances = $instanceTable->getEntityByFields([
                    'anr' => $recoRisk->get('anr')->get('id'),
                    'object' => $recoRisk->get('objectGlobal')->get('id'),
                ]);
                foreach ($brothersInstances as $brotherInstance) {
                    $brothersInstancesRisks = $table->getEntityByFields([
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

        //save recommandation
        $reco = $recoRisk->get('recommandation');
        $reco->counterTreated = $reco->get('counterTreated') + 1;

        //if is final, clean comment, duedate and responsable
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
     * Create Reco Historic
     *
     * @param $data
     * @param $recoRisk
     * @param $final
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

        $histo = [
            'final' => $final,
            'implComment' => $data['comment'],
            'recoCode' => $reco->get('code'),
            'recoDescription' => $reco->get('description'),
            'recoImportance' => $reco->get('importance'),
            'recoComment' => $reco->get('comment'),
            'recoDuedate' => $reco->get('duedate'),
            'recoResponsable' => $reco->get('responsable'),
            'riskInstance' => $instanceRisk->get('instance')->get('name1'),
            'riskInstanceContext' => $anrInstanceService->getDisplayedAscendance($instanceRisk->get('instance')),
            'riskAsset' => $instanceRisk->get('asset')->get('code') . ' - ' . $instanceRisk->get('asset')->get('label1'),
            'riskThreat' => $instanceRisk->get('threat')->get('code') . ' - ' . $instanceRisk->get('threat')->get('label1'),
            'riskThreatVal' => $instanceRisk->get('threatRate'),
            'riskVul' => $instanceRisk->get('vulnerability')->get('code') . ' - ' . $instanceRisk->get('vulnerability')->get('label1'),
            'riskVulValBefore' => $instanceRisk->get('vulnerabilityRate'),
            'riskVulValAfter' => ($final) ? max(0, $instanceRisk->get('vulnerabilityRate') - $instanceRisk->get('reductionAmount')) : $instanceRisk->get('vulnerabilityRate'),
            'riskKindOfMeasure' => $instanceRisk->get('kindOfMeasure'),
            'riskCommentBefore' => $instanceRisk->get('comment'),
            'riskCommentAfter' => ($final) ? $instanceRisk->get('commentAfter') : $instanceRisk->get('comment'),
            'riskMaxRiskBefore' => $instanceRisk->get('cacheMaxRisk'),
            'riskMaxRiskAfter' => ($final) ? $instanceRisk->get('cacheTargetedRisk') : $instanceRisk->get('cacheMaxRisk'),
            'riskColorBefore' => ($instanceRisk->get('cacheMaxRisk') != -1) ? $anrService->getColor($anr, $instanceRisk->get('cacheMaxRisk')) : '',
            'cacheCommentAfter' => $instanceRisk->get('commentAfter'),
            'riskColorAfter' => ($final)
                ? ((($instanceRisk->get('cacheTargetedRisk') != -1) ? $anrService->getColor($anr, $instanceRisk->get('cacheTargetedRisk')) : ''))
                : (($instanceRisk->get('cacheMaxRisk') != -1) ? $anrService->getColor($anr, $instanceRisk->get('cacheMaxRisk')) : ''),

        ];

        $class = $this->get('recommandationHistoricEntity');
        $recoHisto = new $class();
        $recoHisto->setLanguage($this->getLanguage());
        $recoHisto->setDbAdapter($this->get('recommandationHistoricTable')->getDb());
        $recoHisto->exchangeArray($histo);

        $recoHisto->anr = $anr;
        $recoHisto->instanceRisk = $instanceRisk;

        $recoHistoTable->save($recoHisto);
    }

    /**
     * Detach
     *
     * @param $recommandationRisk
     * @param bool $final
     */
    public function detach($recommandationRisk, $final = true)
    {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');

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

        $this->updatePosition($recommandationRisk->recommandation, $final);
    }

    /**
     * Update Position
     *
     * @param $recommandation
     * @param bool $final
     */
    public function updatePosition($recommandation, $final = true)
    {
        /** @var RecommandationTable $recommandationTable */
        $recommandationTable = $this->get('recommandationTable');

        if (!$final && $recommandation->get('position') == 0) {
            $recommandation->position = 1;
            $recommandationTable->save($recommandation);
        } else if ($final && $recommandation->get('position') > 0) {
            $recommandation->position = 0;
            $recommandationTable->save($recommandation);
        }
    }

    /**
     * Delete
     *
     * @param $id
     */
    public function delete($id)
    {
        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $recommandationRisk = $table->getEntity($id);

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
                    if ($recommandationRiskReco->instanceRisk) {
                        if (in_array($recommandationRiskReco->instanceRisk->id, $brothersIds)) {
                            $this->get('table')->delete($recommandationRiskReco->id);
                        }
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
                    if ($recommandationRiskReco->instanceRiskOp) {
                        if (in_array($recommandationRiskReco->instanceRiskOp->id, $brothersIds)) {
                            $this->get('table')->delete($recommandationRiskReco->id);
                        }
                    }
                }
            } else {
                $this->get('table')->delete($id);
            }
        }
    }
}