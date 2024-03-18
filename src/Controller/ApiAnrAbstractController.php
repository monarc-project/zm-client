<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Ramsey\Uuid\Uuid;
use function in_array;

/**
 * Abstract controller for all ANR-based routes. Allows easy permissions filtering for routes below this one.
 * @package Monarc\FrontOffice\Controller
 */
abstract class ApiAnrAbstractController extends AbstractController
{
    use ControllerRequestResponseHandlerTrait;

    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status');

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
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

        return $this->getPreparedJsonResponse([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $identifier = [];

        $class = $this->getService()->get('entity');
        $entity = new $class();
        $ids = $class->getDbAdapter()->getClassMetadata(get_class($entity))->getIdentifierFieldNames();
        if (\count($ids) === 2 && empty(array_diff(['uuid', 'anr'], $ids)) && !is_array($id) && Uuid::isValid($id)) {
            $identifier['uuid'] = $id;
            $identifier['anr'] = $anrId;
        } else {
            $identifier = $id;
        }

        $entity = $this->getService()->getEntity($identifier);

        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies);
        }

        return $this->getPreparedJsonResponse($entity);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        if (array_keys($data) !== range(0, count($data) - 1)) {
            # if $data is an associative array
            $data = array($data);
        }

        $created_objects = [];
        foreach ($data as $key => $new_data) {
            $new_data['anr'] = $anrId;
            if (isset($new_data['referential'])) {
                $new_data['referential'] = ['uuid' => $new_data['referential'], 'anr' => $anrId];
            }
            if (isset($new_data['threat']) && !is_array($new_data['threat'])) {
                $new_data['threat'] = ['uuid' => $new_data['threat'], 'anr' => $anrId];
            }
            if (isset($new_data['vulnerability']) && !is_array($new_data['vulnerability'])) {
                $new_data['vulnerability'] = ['uuid' => $new_data['vulnerability'], 'anr' => $anrId];
            }
            if (isset($new_data['asset']) && !is_array($new_data['asset'])) {
                $new_data['asset'] = ['uuid' => $new_data['asset'], 'anr' => $anrId];
            }
            if (isset($new_data['amv']) && !is_array($new_data['amv'])) {
                $new_data['amv'] = ['uuid' => $new_data['amv'], 'anr' => $anrId];
            }
            if (isset($new_data['father'], $new_data['child'])) {
                $new_data['father'] = ['anr' => $anrId, 'uuid' => $new_data['father']];
                $new_data['child'] = ['anr' => $anrId, 'uuid' => $new_data['child']];
            }
            if (isset($new_data['parent'], $new_data['child'])) {
                $new_data['parent'] = ['anr' => $anrId, 'uuid' => $new_data['parent']];
                $new_data['child'] = ['anr' => $anrId, 'uuid' => $new_data['child']];
            }

            $id = $this->getService()->create($new_data);
            $created_objects[] = $id;
        }

        return $this->getSuccessfulJsonResponse([
            'id' => count($created_objects) == 1 ? $created_objects[0] : $created_objects,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $identifier = $this->getService()->get('entity')->getDbAdapter()->getClassMetadata(
            \get_class($this->getService()->get('entity'))
        )->getIdentifierFieldNames();
        if (\count($identifier) > 1
            && in_array('anr', $identifier)
            && in_array('uuid', $identifier)
            && !is_array($id)
        ) {
            $id = ['uuid' => $id, 'anr' => $anrId];
        }
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->update($id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $identifier = $this->getService()->get('entity')->getDbAdapter()->getClassMetadata(
            \get_class($this->getService()->get('entity'))
        )->getIdentifierFieldNames();
        if (\count($identifier) > 1
            && in_array('anr', $identifier)
            && in_array('uuid', $identifier)
            && !is_array($id)
        ) {
            $id = ['uuid' => $id, 'anr' => $anrId];
        }

        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->patch($id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        $identifier = $this->getService()->get('entity')->getDbAdapter()->getClassMetadata(
            \get_class($this->getService()->get('entity'))
        )->getIdentifierFieldNames();
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (\count($identifier) > 1
            && in_array('anr', $identifier)
            && in_array('uuid', $identifier)
            && !is_array($id)
        ) {
            $id = ['uuid' => $id, 'anr' => $anrId];
        }

        $this->getService()->deleteFromAnr($id, $anrId);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * @inheritdoc
     */
    public function deleteList($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        $class = $this->getService()->get('entity');
        $entity = new $class();
        $ids = $class->getDbAdapter()->getClassMetadata(get_class($entity))->getIdentifierFieldNames();
        if (\count($ids) > 1) {
            foreach ($data as $key => $value) {
                if (in_array('anr', $ids) && in_array('uuid', $ids) && !is_array($value)) {
                    $data[$key] = ['uuid' => $value, 'anr' => $anrId];
                }
            }
        }

        $this->getService()->deleteListFromAnr($data, $anrId);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * Function who put in all records of an array the anr_id
     *
     * @param array $input list of uuid for which one we want a composite id [uuid,anrid]
     *
     * @return array The correct list of composite id
     */
    public function addAnrId($input)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $withId = array();
        if (is_array($input)) {
            foreach ($input as $item) {
                $withId[] = ['uuid' => $item, 'anr' => $anrId];
            }

            return $withId;
        }

        return ['uuid' => $input, 'anr' => $anrId];
    }
}
