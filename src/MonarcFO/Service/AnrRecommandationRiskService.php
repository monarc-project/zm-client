<?php
namespace MonarcFO\Service;

use MonarcFO\Model\Entity\Object;
use MonarcFO\Model\Table\InstanceRiskOpTable;
use MonarcFO\Model\Table\InstanceRiskTable;
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