<?php

namespace MonarcFO\Controller;

use MonarcFO\Service\AnrRecommandationRiskService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations Risks
 *
 * Class ApiAnrRecommandationsRisksController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsRisksController extends ApiAnrAbstractController
{
    protected $name = 'recommandations-risks';
    protected $dependencies = ['anr', 'recommandation'];

    /**
     * Get List
     *
     * @return JsonModel
     * @throws \Exception
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status');
        $risk = $this->params()->fromQuery('risk');
        $op = $this->params()->fromQuery('op');

        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
            throw new \Exception('Anr id missing', 412);
        }

        $filterAnd = ['anr' => $anrId];

        if (!is_null($status) && $status != 'all') {
            $filterAnd['status'] = $status;
        }

        if (!is_null($risk)) {
            $fieldName = ($op) ? 'instanceRiskOp' : 'instanceRisk';
            $filterAnd[$fieldName] = intval($risk);
        }

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel(array(
            'count' => $service->getFilteredCount($page, $limit, $order, $filter, $filterAnd),
            $this->name => $entities
        ));
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($token, $data)
    {
        return $this->methodNotAllowed();
    }
}
