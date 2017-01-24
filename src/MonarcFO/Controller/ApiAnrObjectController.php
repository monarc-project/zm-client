<?php

namespace MonarcFO\Controller;

use MonarcCore\Model\Entity\AbstractEntity;
use MonarcCore\Model\Entity\Object;
use MonarcCore\Service\ObjectService;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Object Controller
 *
 * Class ApiAnrObjectController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectController extends ApiAnrAbstractController
{
    protected $name = 'object';

    public function getList()
    {
        $this->methodNotAllowed();
    }

    public function get($id)
    {
        $this->methodNotAllowed();
    }

    public function delete($id)
    {
        $this->methodNotAllowed();
    }

    public function create($data)
    {
        $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        $this->methodNotAllowed();
    }

    public function patch($id, $data)
    {
        $this->methodNotAllowed();
    }

    public function parentsAction()
    {
        $matcher = $this->getEvent()->getRouteMatch();
        return new JsonModel($this->getService()->getParents($matcher->getParam('anrid'), $matcher->getParam('id')));
    }
}
