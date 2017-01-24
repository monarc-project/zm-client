<?php

namespace MonarcFO\Controller;

use MonarcCore\Model\Entity\AbstractEntity;
use Zend\View\Model\JsonModel;

class ApiUserProfileController extends \MonarcCore\Controller\AbstractController
{
    protected $connectedUser;

    public function __construct($services)
    {
        if (!empty($services['service'])) {
            $this->service = $services['service'];
        }
        if (!empty($services['connectedUser'])) {
            $this->connectedUser = $services['connectedUser'];
        }
    }

    public function getList()
    {
        $user = $this->connectedUser->getConnectedUser();
        unset($user['password']);
        return new JsonModel($user);
    }

    public function patchList($data)
    {
        return new JsonModel($this->getService()->update($this->connectedUser->getConnectedUser(), $data));
    }

    public function replaceList($data)
    {
        return new JsonModel($this->getService()->update($this->connectedUser->getConnectedUser(), $data));
    }

    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }

    public function create($data)
    {
        return $this->methodNotAllowed();
    }
}
