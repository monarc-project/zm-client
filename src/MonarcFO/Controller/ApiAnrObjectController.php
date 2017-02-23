<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

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
     * GET action that retrieves the parents of the object
     * @return JsonModel The JSON data of the parents
     */
    public function parentsAction()
    {
        $matcher = $this->getEvent()->getRouteMatch();
        return new JsonModel($this->getService()->getParents($matcher->getParam('anrid'), $matcher->getParam('id')));
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $this->methodNotAllowed();
    }
}
