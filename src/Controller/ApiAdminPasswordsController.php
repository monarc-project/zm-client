<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Service\PasswordService;
use Zend\View\Model\JsonModel;


/**
 * Api Admin Passwords Controller
 *
 * Class ApiAdminPasswordsController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAdminPasswordsController extends \Monarc\Core\Controller\AbstractController
{
    /**
     * @inheritdoc
     */
    public function create($data)
    {
        /** @var PasswordService $service */
        $service = $this->getService();
        //password forgotten
        if (!empty($data['email']) && empty($data['password'])) {
            try {
                $service->passwordForgotten($data['email']);
            } catch (\Exception $e) {
                // Ignore the \Exception: We don't want to leak any data
            }
        }

        //verify token
        if (!empty($data['token']) && empty($data['password'])) {
            $result = $service->verifyToken($data['token']);

            return new JsonModel(['status' => $result]);
        }

        //change password not logged
        if (!empty($data['token']) && !empty($data['password']) && !empty($data['confirm'])) {
            if ($data['password'] == $data['confirm']) {
                $service->newPasswordByToken($data['token'], $data['password']);
            } else {
                throw new \Monarc\Core\Exception\Exception('Password must be the same', 422);
            }
        }

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($token, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}

