<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception;
use Monarc\FrontOffice\Service\UserRoleService;

class ApiAdminUsersRolesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private UserRoleService $userRoleService;

    public function __construct(UserRoleService $userRoleService)
    {
        $this->userRoleService = $userRoleService;
    }

    public function getList()
    {
        $token = $this->getRequest()->getHeader('token');
        if ($token === false) {
            throw new Exception\UserNotLoggedInException('The user token is not defined. Please login', 403);
        }

        $currentUserRoles = $this->userRoleService->getUserRolesByToken($token->getFieldValue());

        return $this->getPreparedJsonResponse($currentUserRoles);
    }

    public function get($id)
    {
        $userRoles = $this->userRoleService->getUserRolesByUserId((int)$id);

        return $this->getPreparedJsonResponse([
            'count' => \count($userRoles),
            'roles' => $userRoles
        ]);
    }
}
