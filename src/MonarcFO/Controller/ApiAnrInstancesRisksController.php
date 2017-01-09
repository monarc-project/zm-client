<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Instances Risks Controller
 *
 * Class ApiAnrInstancesRisksController
 * @package MonarcFO\Controller
 */
class ApiAnrInstancesRisksController extends ApiAnrAbstractController
{
    protected $name = 'instances-risks';

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

        $this->getService()->updateFromRiskTable($id, $data);

        return new JsonModel(array('status' => 'ok'));
    }
}
