<?php
namespace MonarcFO\Service;

use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Entity\InstanceRiskOp;
use MonarcFO\Model\Entity\Object;
use MonarcFO\Model\Table\InstanceRiskOpTable;
use MonarcFO\Model\Table\InstanceRiskTable;
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
    protected $instanceRiskTable;
    protected $instanceRiskOpTable;

    /**
     * Get Treatment Plans
     *
     * @param $anrId
     * @return mixed
     */
    public function getTreatmentPlan($anrId, $id = false){


        /** @var RecommandationTable $table */
        $table = $this->get('table');
        $recommandationsRisks = ($id) ? $table->getEntity($id) : $table->getEntityByFields(['anr' => $anrId]);

        $order = [
            'position' => 'ASC',
            'importance' => 'DESC',
        ];

        /** @var RecommandationTable $recommandationTable */
        $recommandationTable = $this->get('recommandationTable');
        $recommandations = $recommandationTable->getEntityByFields(['anr' => $anrId], $order);
        foreach($recommandations as $key => $recommandation) {
            $recommandations[$key] = $recommandation->getJsonArray();
            $nbRisks = 0;
            foreach($recommandationsRisks as $recommandationRisk) {
                if ($recommandationRisk->recommandation->id == $recommandation->id) {
                    //retrieve instance risk associated
                    if ($recommandationRisk->instanceRisk) {
                        if ($recommandationRisk->instanceRisk->kindOfMeasure != InstanceRisk::KIND_NOT_TREATED) {
                            $instanceRisk = $recommandationRisk->instanceRisk;
                            $instanceRisk->asset = $instanceRisk->asset->getJsonArray();
                            $instanceRisk->threat = $instanceRisk->threat->getJsonArray();
                            $instanceRisk->vulnerability = $instanceRisk->vulnerability->getJsonArray();
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
                }
            }
            if (!$nbRisks) {
                unset($recommandations[$key]);
            }
        }

        return $recommandations;
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
}