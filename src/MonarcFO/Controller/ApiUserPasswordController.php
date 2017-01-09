<?php

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

class ApiUserPasswordController extends AbstractController
{
    public function create($data)
    {
        return $this->methodNotAllowed();
    }

    public function getList()
    {
        return $this->methodNotAllowed();
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * Update
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function update($id, $data)
    {
        if ($data['new'] == $data['confirm']) {
            $this->getService()->changePassword($id, $data['old'], $data['new']);
        } else {
            throw  new \Exception('Password must be the same', 422);
        }

        return new JsonModel(array('status' => 'ok'));
    }

    public function patch($token, $data)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}

