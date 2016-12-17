<?php
namespace MonarcFO\Service;

use \Doctrine\ORM\Query\Expr\Join;
use \MonarcFO\Model\Entity\InstanceRisk;
use \MonarcFO\Model\Entity\Object;
use \MonarcFO\Model\Entity\Asset;
use \MonarcFO\Model\Entity\InstanceRiskOp;

/**
 * Anr RiskOp Service
 *
 * Class AnrRiskOpService
 * @package MonarcFO\Service
 */
class AnrRiskOpService extends \MonarcCore\Service\AbstractService
{
    protected $filterColumns = [];
    protected $dependencies = ['anr', 'scale'];

    protected $instanceRiskOpService;
    protected $instanceTable;

	public function getRisksOp($anrId, $instance = null, $params = []) {
        /** @var InstanceTable $instanceTable */
        $instanceTable = $this->get('instanceTable');

        $instances = [];
        if ($instance) {
            $instanceEntity = $instanceTable->getEntity($instance['id']);
            $instances[] = $instanceEntity;

            // Get children instances
            $instanceTable->initTree($instanceEntity);
            $temp = isset($instanceEntity->parameters['children']) ? $instanceEntity->parameters['children'] : [];
            while( ! empty($temp) ){
                $sub = array_shift($temp);
                $instances[] = $sub;

                if(!empty($sub->parameters['children'])){
                    foreach($sub->parameters['children'] as $subsub){
                        array_unshift($temp, $subsub);
                    }
                }
            }
        } else {
            $instances = $instanceTable->getEntityByFields(['anr' => $anrId]);
        }
        $instancesIds = [];
        foreach ($instances as $i) {
            if($i->get('asset')->get('type') == Asset::ASSET_PRIMARY){
                $instancesIds[] = $i->id;
            }
        }

        //retrieve risks instances
        /** @var InstanceRiskOpService $instanceRiskServiceOp */
        $instanceRiskOpService = $this->get('instanceRiskOpService');
        $instancesRisksOp = $instanceRiskOpService->getInstancesRisksOp($instancesIds, $anrId);

        //order by net risk
        $tmpInstancesRisksOp = [];
        $tmpInstancesMaxRisksOp = [];
        foreach($instancesRisksOp as $instancesRiskOp) {
            $tmpInstancesRisksOp[$instancesRiskOp->id] = $instancesRiskOp;
            $tmpInstancesMaxRisksOp[$instancesRiskOp->id] = $instancesRiskOp->cacheNetRisk;
        }
        arsort($tmpInstancesMaxRisksOp);
        $instancesRisksOp = [];
        foreach($tmpInstancesMaxRisksOp as $id => $tmpInstancesMaxRiskOp) {
            $instancesRisksOp[] = $tmpInstancesRisksOp[$id];
        }

        $riskOps = [];
        foreach ($instancesRisksOp as $instanceRiskOp) {
            // Process filters
            if (isset($params['kindOfMeasure'])) {
                if ($instanceRiskOp->kindOfMeasure != $params['kindOfMeasure']) {
                    continue;
                }
            }

            if (isset($params['thresholds'])) {
                $min = $params['thresholds'];

                if ($instanceRiskOp->cacheNetRisk < $min) {
                    continue;
                }
            }

            if (isset($params['keywords']) && !empty($params['keywords'])) {
                if (!$this->findInFields($instanceRiskOp, $params['keywords'], ['riskCacheLabel1', 'riskCacheLabel2', 'riskCacheLabel3', 'riskCacheLabel4',
                    'riskCacheDescription1', 'riskCacheDescription2', 'riskCacheDescription3', 'riskCacheDescription4', 'comment'])) {
                    continue;
                }
            }

            // Add risk
            $riskOps[] = [
                'id' => $instanceRiskOp->id,
                'label1' => $instanceRiskOp->riskCacheLabel1,
                'label2' => $instanceRiskOp->riskCacheLabel2,
                'label3' => $instanceRiskOp->riskCacheLabel3,
                'label4' => $instanceRiskOp->riskCacheLabel4,

                'description1' => $instanceRiskOp->riskCacheDescription1,
                'description2' => $instanceRiskOp->riskCacheDescription2,
                'description3' => $instanceRiskOp->riskCacheDescription3,
                'description4' => $instanceRiskOp->riskCacheDescription4,

                'netProb' => $instanceRiskOp->netProb,
                'netR' => $instanceRiskOp->netR,
                'netO' => $instanceRiskOp->netO,
                'netL' => $instanceRiskOp->netL,
                'netF' => $instanceRiskOp->netF,
                'netP' => $instanceRiskOp->netP,
                'cacheNetRisk' => $instanceRiskOp->cacheNetRisk,

                'brutProb' => $instanceRiskOp->brutProb,
                'brutR' => $instanceRiskOp->brutR,
                'brutO' => $instanceRiskOp->brutO,
                'brutL' => $instanceRiskOp->brutL,
                'brutF' => $instanceRiskOp->brutF,
                'brutP' => $instanceRiskOp->brutP,
                'cacheBrutRisk' => $instanceRiskOp->cacheBrutRisk,

                'kindOfMeasure' => $instanceRiskOp->kindOfMeasure,
                'comment' => $instanceRiskOp->comment,
                't' => (($instanceRiskOp->kindOfMeasure == InstanceRiskOp::KIND_NOT_TREATED) || (!$instanceRiskOp->kindOfMeasure)) ? false : true,

                'targetedProb' => $instanceRiskOp->targetedProb,
                'targetedR' => $instanceRiskOp->targetedR,
                'targetedO' => $instanceRiskOp->targetedO,
                'targetedL' => $instanceRiskOp->targetedL,
                'targetedF' => $instanceRiskOp->targetedF,
                'targetedP' => $instanceRiskOp->targetedP,
                'cacheTargetedRisk' => $instanceRiskOp->cacheTargetedRisk,
            ];
        }

        return $riskOps;
    }
}
