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
    protected $dependencies = ['anr', 'amv', 'asset', 'threat', 'vulnerability', 'instance'];
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
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $id = $this->getService()->updateFromRiskTable($id, $data);

        $entity = $this->getService()->getEntity($id);

        if (count($this->dependencies)) {
            foreach ($this->dependencies as $d) {
                unset($entity[$d]);
            }
        }

        return new JsonModel($entity);
    }
}
