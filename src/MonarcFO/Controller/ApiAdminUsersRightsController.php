<?php
namespace MonarcFO\Controller;

use MonarcFO\Service\UserAnrService;
use Zend\View\Model\JsonModel;

/**
 * Api Admin Users Rights Controller
 *
 * Class ApiAdminUsersRightsController
 * @package MonarcFO\Controller
 */
class ApiAdminUsersRightsController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'rights';

    /**
     * Get List
     *
     * @return JsonModel
     */
    public function getList()
    {
        /** @var UserAnrService $service */
        $service = $this->getService();
        $rights = $service->getMatrix();

        return new JsonModel($rights);
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }
}

