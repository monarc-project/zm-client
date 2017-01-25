<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Categories Controller
 *
 * Class ApiAnrObjectsCategoriesController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsCategoriesController extends ApiAnrAbstractController
{
    protected $name = 'categories';
    protected $dependencies = ['parent', 'root', 'anr'];

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
        if (empty($order)) {
            $order = "position";
        }
        $filter = $this->params()->fromQuery('filter');
        $lock = $this->params()->fromQuery('lock') == "false" ? false : true;

        /** @var ObjectCategoryService $service */
        $service = $this->getService();

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $filterAnd = ['anr' => $anrId];
        $catid = (int)$this->params()->fromQuery('catid');
        $parentId = (int)$this->params()->fromQuery('parentId');
        if (!empty($catid)) {
            $filterAnd['id'] = [
                'op' => 'NOT IN',
                'value' => [$catid],
            ];
            if ($parentId > 0) {
                $filterAnd['id']['value'][] = $parentId;
                $filterAnd['parent'] = $parentId;
            } else {
                $filterAnd['parent'] = null;
            }
        } elseif ($parentId > 0) {
            $filterAnd['parent'] = $parentId;
        } elseif (!$lock) {
            $filterAnd['parent'] = null;
        }

        $objectCategories = $service->getListSpecific($page, $limit, $order, $filter, $filterAnd);

        $fields = ['id', 'label1', 'label2', 'label3', 'label4', 'position'];;

        if ($parentId > 0 && $lock) {
            $recursiveArray = $this->getCleanFields($objectCategories, $fields);
        } else {
            $recursiveArray = $this->recursiveArray($objectCategories, null, 0, $fields);
        }

        return new JsonModel([
            'count' => $this->getService()->getFilteredCount($page, $limit, $order, $filter, $filterAnd),
            $this->name => $recursiveArray
        ]);
    }

    public function getCleanFields($items, $fields)
    {
        $output = [];
        foreach ($items as $item) {
            $item_output = [];

            foreach ($item as $key => $value) {
                if (in_array($key, $fields)) {
                    $item_output[$key] = $value;
                }
            }

            $output[] = $item_output;
        }
        return $output;
    }

    /**
     * Create
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

        $obj = $this->getService()->create($data);

        return new JsonModel([
            'status' => 'ok',
            'categ' => $obj,
        ]);
    }
}
