<?php
namespace MonarcFO\Service;

use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Entity\InstanceRiskOp;
use MonarcFO\Model\Entity\Object;
use MonarcFO\Model\Table\InstanceRiskOpTable;
use MonarcFO\Model\Table\InstanceRiskTable;
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
    protected $dependencies = ['anr', 'recommandation'];
    protected $anrTable;
    protected $recommandationTable;
    protected $recommandationHistoricTable;
    protected $recommandationMeasureTable;
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;
    protected $recommandationHistoricEntity;

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null){

        /** @var RecommandationRiskTable $table */
        $table = $this->get('table');
        $recosRisks =  $table->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );

        /** @var RecommandationMeasureTable $recommandationMeasureTable */
        $recommandationMeasureTable = $this->get('recommandationMeasureTable');

        foreach($recosRisks as $key => $recoRisk) {

            $recommandationsMeasures = $recommandationMeasureTable->getEntityByFields(['recommandation' => $recoRisk['recommandation']->id]);

            $measures = [];
            foreach ($recommandationsMeasures as $recommandationMeasure) {
                $recommandationMeasure = $recommandationMeasure->getJsonArray();
                $recommandationMeasure['measure'] = $recommandationMeasure['measure']->getJsonArray();
                $measures[] = $recommandationMeasure;
            }

            $recosRisks[$key]['measures'] = $measures;
        }

        return $recosRisks;
    }

    /**
     * Get Treatment Plans
     *
     * @param $anrId
     * @return mixed
     */
    public function getTreatmentPlan($anrId, $id = false){

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

        foreach($recommandations as $key => $recommandation) {
            $recommandations[$key] = $recommandation->getJsonArray();
            unset($recommandations[$key]['__initializer__']);
            unset($recommandations[$key]['__cloner__']);
            unset($recommandations[$key]['__isInitialized__']);
            $nbRisks = 0;
            $global = [];
            $risksToUnset = [];
            foreach($recommandationsRisks as $recommandationRisk) {
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
                            $recommandations[$key]['risks'][] = $instanceRisk->getJsonArray();
                            $nbRisks++;
                        }
                    }
                    //retrieve instance risk op associated
                    if ($recommandationRisk->instanceRiskOp) {
                        if ($recommandationRisk->instanceRiskOp->kindOfMeasure != InstanceRiskOp::KIND_NOT_TREATED) {
                            $recommandations[$key]['risksop'][] = $recommandationRisk->instanceRiskOp->getJsonArray();
                            $nbRisks++;
                        }
                    }

                    //delete risk of global with risk value is not the higher
                    if ($recommandationRisk->objectGlobal) {
                        foreach($global as $glob) {
                            if ($glob['objectId'] == $recommandationRisk->objectGlobal->id) {
                                if ($glob['maxRisk'] < $recommandationRisk->instanceRisk->cacheMaxRisk) {
                                    $risksToUnset[] = $glob['riskId'];
                                } else {
                                    $risksToUnset[] = $recommandationRisk->instanceRisk->id;
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
                    if (in_array($risk['id'], $risksToUnset)) {
                        unset($recommandations[$key]['risks'][$k]);
                    }
                }
            }

            if (!$nbRisks) {
                unset($recommandations[$key]);
            }
        }

        return array_values($recommandations);
    }

    /**
     * Create
     *
     * @param $data
     * @param bool $last
     * @return mixed
     */
    public function create($data, $last = true) {

        //$entity = $this->get('entity');
        $class = $this->get('entity');
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());
        $entity->exchangeArray($data);

        $dependencies =  (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        //retrieve risk
        if ($data['op']) {
            /** @var InstanceRiskOpTable $instanceRiskOpTable */
            $instanceRiskOpTable = $this->get('instanceRiskOpTable');
            $risk = $instanceRiskOpTable->getEntity($data['risk']);

            $entity->setInstanceRisk(null);
            $entity->setInstanceRiskOp($risk);

        } else {
            /** @var InstanceRiskTable $instanceRiskOpTable */
            $instanceRiskTable = $this->get('instanceRiskTable');
            $risk = $instanceRiskTable->getEntity($data['risk']);

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


        /** @var AnrTable $table */
        $table = $this->get('table');

        return $table->save($entity, $last);
    }

    public function initPosition($anrId) {

        //retrieve recommandations
        /** @var RecommandationTable $recommandationTable */
        $recommandationTable = $this->get('recommandationTable');
        $recommandations = $recommandationTable->getEntityByFields(['anr' => $anrId], ['importance' => 'DESC']);

        $position = 0;
        $i = 1;
        foreach ($recommandations as $recommandation) {
            $last = ($i == count($recommandations)) ? true : false;
            $recommandation->position = $position;
            $recommandationTable->save($recommandation, $last);

            $position++;
            $i++;
        }
    }

    /**
     * Validate For
     *
     * @param $id
     */
    public function validateFor($id) {

        /** @var InstanceRiskTable $table */
        $table = $this->get('table');
        $recommandationRisk = $this->getEntity($id);

        //detach risk of recommendation
        $table->delete($id);

        //repositioning recommendation in hierarchy

        //automatically record in history before modify recommendation and risk values
        $reco = $recommandationRisk['recommandation'];
        $risk = $recommandationRisk['instanceRisk'];
        $data = [
            'implComment' => '',
            'recoCode' => $reco->code,
            'recoDescription' => $reco->description,
            'recoImportance' => $reco->importance,
            'recoComment' => $reco->comment,
            'recoResponsable' => $reco->responsable,
            'recoDuedate' => $reco->duedate,
            'riskInstance' => $risk->instance->label1,
            'riskInstanceContext' => '',
            'riskAsset' => $risk->asset->label1,
            'riskThreat' => $risk->threat->label1,
            'riskThreatVal' => '',
            'riskVul' => $risk->vul->label1,
            'riskVulValBefore' => '',
            'riskVulValAfter' => '',
            'riskKindOfMeasure' => $risk->kindOfMeasure,
            'riskCommentBefore' => $risk->comment,
            'riskCommentAfter' => $risk->commentAfter,
            'riskMaxRiskBefore' => $risk->cacheMaxRisk,
            'riskColorBefore' => '',
            'riskMaxRiskAfter' => '',
            'riskColorAfter' => '',
        ];

        $class = $this->get('recommandationHistoricEntity');
        $recoHisto = new $class();
        $recoHisto->setLanguage($this->getLanguage());
        $recoHisto->setDbAdapter($this->get('recommandationHistoricTable')->getDb());
        $recoHisto->exchangeArray($data);

        $recoHisto->anr = $recommandationRisk->anr;

        /** @var RecommandationHistoricTable $recoHistoTable */
        $recoHistoTable = $this->get('recommandationHistoricTable');
        $recoHistoTable->save($recoHisto);

        //overload constatation for volatile comment (after measure)

        //apply reduction vulnerability on risk

        //set reduction amount to 0

        //change status to NOT_TREATED

        //increment counter treated

        //si la reco n'a plus de lien avec des risques, on nettoie son commentaire, sa duedate et son responsable

    }
}