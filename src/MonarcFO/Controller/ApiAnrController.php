<?php
namespace MonarcFO\Controller;

use MonarcFO\Service\AnrService;
use Zend\View\Model\JsonModel;

class ApiAnrController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'anrs';

    /**
     * Get list
     *
     * @return JsonModel
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');

        /** @var AnrService $service */
        $service = $this->getService();
        $entities = $service->getList($page, $limit, $order, $filter);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel(array(
            'count' => count($entities),
            $this->name => $entities
        ));
    }

    public function create($data)
    {
        /** @var AnrService $service */
        $service = $this->getService();

        if (!isset($data['model'])) {
            throw new \Exception('Model missing', 412);
        }

        $id = $service->createFromModelToClient($data);

        return new JsonModel(
            array(
                'status' => 'ok',
                'id' => $id,
            )
        );
    }
}

