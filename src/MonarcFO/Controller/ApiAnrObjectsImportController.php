<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Import Controller
 *
 * Class ApiAnrObjectsImportController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsImportController extends ApiAnrAbstractController
{
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

        $mode = $this->params()->from

        $id = $this->getService()->importFromFile($anrId,$data);

        return new JsonModel(['status' => 'ok', 'id' => $id]);
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    public function getList()
    {
        return $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}
