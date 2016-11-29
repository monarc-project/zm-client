<?php
namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

abstract class ApiAnrAbstractController extends \MonarcCore\Controller\AbstractController
{
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

        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }
        $filterAnd = ['anr' => $anrId];

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter,$filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel(array(
            'count' => $service->getFilteredCount($page, $limit, $order, $filter,$filterAnd),
            $this->name => $entities
        ));
    }

    /**
     * Get
     *
     * @param mixed $id
     * @return JsonModel
     * @throws \Exception
     */
    public function get($id)
    {
        $entity = $this->getService()->getEntity($id);

        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }
        if(!$entity['anr'] || $entity['anr']->get('id') != $anrId){
        	throw new \Exception('Anr ids diffence', 412);
        }

        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies);
        }

        return new JsonModel($entity);
    }

    /**
     * Create
     *
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function create($data)
    {
    	$anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $id = $this->getService()->create($data);

        return new JsonModel(
            array(
                'status' => 'ok',
                'id' => $id,
            )
        );
    }

    /**
     * Update
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function update($id, $data)
    {
    	$anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->update($id, $data);

        return new JsonModel(array('status' => 'ok'));
    }

    /**
     * Patch
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function patch($id, $data)
    {
    	$anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->patch($id, $data);

        return new JsonModel(array('status' => 'ok'));
    }
}