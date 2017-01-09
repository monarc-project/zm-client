<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Instances Risks Op Controller
 *
 * Class ApiAnrInstancesRisksOpController
 * @package MonarcFO\Controller
 */
class ApiAnrInstancesRisksOpController extends ApiAnrAbstractController
{
    protected $name = 'instances-oprisks';


    public function update($id, $data){
        $risk = $this->getService()->update($id, $data);
        unset($risk['anr']);
        unset($risk['instance']);
        unset($risk['object']);
        unset($risk['rolfRisk']);

        return new JsonModel($risk);
    }
}
