<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Instance Consequences Controller
 *
 * Class ApiAnrInstancesConsequencesController
 * @package MonarcFO\Controller
 */
class ApiAnrInstancesConsequencesController extends ApiAnrAbstractController
{
    protected $name = 'instances-consequences';

    /**
     * Patch
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     */
    public function patch($id, $data)
    {
        $data['anr'] = (int)$this->params()->fromRoute('anrid');

        $this->getService()->patchConsequence($id, $data);

        return new JsonModel(array('status' => 'ok'));
    }
}
