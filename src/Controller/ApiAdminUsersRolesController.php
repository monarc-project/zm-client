<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\UserRoleService;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

/**
 * Api Admin Users Roles Controller
 *
 * Class ApiAdminUsersRolesController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAdminUsersRolesController extends AbstractRestfulController
{
    protected $name = 'roles';

    /** @var UserRoleService */
    private $userRoleService;

    public function __construct(UserRoleService $userRoleService)
    {
        $this->userRoleService = $userRoleService;
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $request = $this->getRequest();
        $token = $request->getHeader('token');

        $currentUserRoles = $this->userRoleService->getByUserToken($token);

        return new JsonModel($currentUserRoles);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $userRoles = $this->userRoleService->getByUserId($id);

        return new JsonModel([
            'count' => count($userRoles),
            $this->name => $userRoles
        ]);
    }
}
