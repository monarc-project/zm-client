<?php

namespace MonarcFO\Controller;

use MonarcFO\Service\UserAnrService;
use MonarcFO\Service\UserRoleService;
use Zend\View\Model\JsonModel;

class ApiAdminUsersRightsController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'rights';

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

