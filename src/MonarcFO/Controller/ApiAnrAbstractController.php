<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Abstract controller for all ANR-based routes. Allows easy permissions filtering for routes below this one.
 * @package MonarcFO\Controller
 */
abstract class ApiAnrAbstractController extends \MonarcCore\Controller\AbstractController
{
    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status');

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $filterAnd = ['anr' => $anrId];

        if (!is_null($status) && $status != 'all') {
            $filterAnd['status'] = $status;
        }

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $entity = $this->getService()->getEntity($id);


        $anrId = (int)$this->params()->fromRoute('anrid');

        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        if (!$entity['anr'] || $entity['anr']->get('id') != $anrId) {
            throw new \MonarcCore\Exception\Exception('Anr ids diffence', 412);
        }

        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies);
        }

        return new JsonModel($entity);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        if (array_keys($data) !== range(0, count($data) - 1)) {
            # if $data is an associative array
            $data = array($data);
        }

        $created_objects = array();
        foreach ($data as $key => $new_data) {
            $new_data['anr'] = $anrId;
            if($new_data['referential']){
               $new_data['referential'] = ['uniqid' => $new_data['referential'], 'anr'=>$anrId];
            }

            $id = $this->getService()->create($new_data);
            array_push($created_objects, $id);
        }

        return new JsonModel([
            'status' => 'ok',
            'id' => count($created_objects)==1 ? $created_objects[0]: $created_objects,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->update($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->patch($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        if ($this->getService()->deleteFromAnr($id, $anrId)) {
            return new JsonModel(['status' => 'ok']);
        } else {
            return new JsonModel(['status' => 'ok']); // Todo : may be add error message
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteList($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        if ($this->getService()->deleteListFromAnr($data, $anrId)) {
            return new JsonModel(['status' => 'ok']);
        } else {
            return new JsonModel(['status' => 'ok']); // Todo: may be add error message
        }
    }
}
