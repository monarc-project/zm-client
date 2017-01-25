<?php
namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Scales Comments Controller
 *
 * Class ApiAnrScalesCommentsController
 * @package MonarcFO\Controller
 */
class ApiAnrScalesCommentsController extends ApiAnrAbstractController
{
    protected $dependencies = ['anr', 'scale', 'scaleImpactType'];
    protected $name = 'comments';

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

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $filterAnd = ['anr' => $anrId];

        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \Exception('Scale id missing', 412);
        }
        $filterAnd['scale'] = $scaleId;

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel([
            'count' => $service->getFilteredCount($page, $limit, $order, $filter, $filterAnd),
            $this->name => $entities
        ]);
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

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        if (!$entity['anr'] || $entity['anr']->get('id') != $anrId) {
            throw new \Exception('Anr ids diffence', 412);
        }

        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \Exception('Scale id missing', 412);
        }
        if (!$entity['scale'] || $entity['scale']->get('id') != $scaleId) {
            throw new \Exception('Scale ids diffence', 412);
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
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \Exception('Scale id missing', 412);
        }
        $data['scale'] = $scaleId;

        $id = $this->getService()->create($data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
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
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \Exception('Scale id missing', 412);
        }
        $data['scale'] = $scaleId;

        $this->getService()->update($id, $data);

        return new JsonModel(['status' => 'ok']);
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
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \Exception('Scale id missing', 412);
        }
        $data['scale'] = $scaleId;

        $this->getService()->patch($id, $data);

        return new JsonModel(['status' => 'ok']);
    }
}