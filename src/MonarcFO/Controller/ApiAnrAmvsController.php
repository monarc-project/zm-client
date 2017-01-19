<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Amvs Controller
 *
 * Class ApiAnrAmvsController
 * @package MonarcFO\Controller
 */
class ApiAnrAmvsController extends ApiAnrAbstractController
{
    protected $name = 'amvs';
    protected $dependencies = ['asset', 'threat', 'vulnerability', 'measure1', 'measure2', 'measure3'];

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
        $asset = $this->params()->fromQuery('asset');
        $amvid = $this->params()->fromQuery('amvid');

        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
            throw new \Exception('Anr id missing', 412);
        }

        $filterAnd = ['anr' => $anrId];

        if (is_null($status)) {
            $status = 1;
        }

        if ($status != 'all') {
            $filterAnd['status'] = (int) $status;
        }
        if ($asset > 0) {
            $filterAnd['asset'] = (int) $asset;
        }

        if(!empty($amvid)){
            $filterAnd['id'] = [
                'op' => '!=',
                'value' => (int)$amvid,
            ];
        }

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

    /**
     * Get
     *
     * @param mixed $id
     * @return JsonModel
     */
    public function get($id)
    {
        $entity = $this->getService()->getEntity($id);

        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies);
        }

        // Find out the entity's implicitPosition and previous
        if ($entity['position'] == 1) {
            $entity['implicitPosition'] = 1;
        } else {
            // We're not at the beginning, get all AMV links of the same asset, and figure out position and previous
            $amvsAsset = $this->getService()->getList(1, 0, 'position', null, ['asset' => $entity['asset']['id']]);

            $i = 0;
            foreach ($amvsAsset as $amv) {
                if ($amv['id'] == $entity['id']) {
                    if ($i == count($amvsAsset) - 1) {
                        $entity['implicitPosition'] = 2;
                    } else {
                        if ($i == 0) {
                            $entity['implicitPosition'] = 1;
                            $entity['previous'] = null;
                        } else {
                            $entity['implicitPosition'] = 3;
                            $entity['previous'] = $amvsAsset[$i - 1];
                            $this->formatDependencies($entity['previous'], $this->dependencies);
                        }
                    }

                    break;
                }

                ++$i;
            }
        }

        return new JsonModel($entity);
    }
}
