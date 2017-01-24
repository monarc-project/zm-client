<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

class ApiAdminUsersRolesController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'roles';

    public function getList()
    {

        $request = $this->getRequest();
        $token = $request->getHeader('token');

        $currentUserRoles = $this->getService()->getByUserToken($token);

        return new JsonModel($currentUserRoles);
    }

    public function get($id)
    {
        $userRoles = $this->getService()->getByUserId($id);

        return new JsonModel(array(
            'count' => count($userRoles),
            $this->name => $userRoles
        ));
    }

    public function create($data)
    {
        return $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }

}

