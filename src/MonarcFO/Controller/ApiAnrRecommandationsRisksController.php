<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations Risks
 *
 * Class ApiAnrRecommandationsRisksController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsRisksController extends ApiAnrAbstractController
{
    protected $name = 'recommandations-risks';
    protected $dependencies = ['anr', 'recommandation'];

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($token, $data)
    {
        return $this->methodNotAllowed();
    }
}
