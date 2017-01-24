<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Import Controller
 *
 * Class ApiAnrObjectsImportController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsImportController extends ApiAnrImportAbstractController
{
    protected $name = 'objects';

    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $objects = $this->getService()->getCommonObjects($anrId);
        return new JsonModel(array(
            'count' => count($objects),
            $this->name => $objects,
        ));
    }

    /**
     * Patch
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $newid = $this->getService()->importFromCommon($id, $data);

        return new JsonModel(array('status' => 'ok', 'id' => $newid));
    }

    /**
     * Get
     *
     * @param mixed $id
     * @return JsonModel
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }

        $object = $this->getService()->getCommonEntity($anrId, $id);

        $this->formatDependencies($object, ['asset', 'category', 'rolfTag']);
        unset($object['anrs']);

        return new JsonModel($object);
    }
}
