<?php
namespace MonarcFO\Controller;
;
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

    /**
     * Parents Action
     *
     * @return JsonModel
     */
    public function parentsAction()
    {
        $matcher = $this->getEvent()->getRouteMatch();
        return new JsonModel($this->getService()->getParents($matcher->getParam('anrid'), $matcher->getParam('id')));
    }

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
}
