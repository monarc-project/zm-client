<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Scales Controller
 *
 * Class ApiAnrScalesController
 * @package MonarcFO\Controller
 */
class ApiAnrScalesController extends ApiAnrAbstractController
{
    protected $name = 'scales';
    protected $dependencies = [];

     public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');

        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }
        $filterAnd = ['anr' => $anrId];

        $service = $this->getService();

        list($entities,$canChange) = $service->getList($page, $limit, $order, $filter,$filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel(array(
            'count' => $service->getFilteredCount($page, $limit, $order, $filter,$filterAnd),
            $this->name => $entities,
            'canChange' => $canChange,
        ));
    }
}
