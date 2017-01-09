<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations Measures
 *
 * Class ApiAnrRecommandationsMeasuresController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsMeasuresController extends ApiAnrAbstractController
{
    protected $name = 'recommandations-measures';
    protected $dependencies = ['anr', 'recommandation', 'measure'];

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($token, $data)
    {
        return $this->methodNotAllowed();
    }
}
