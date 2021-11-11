<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\UserProfileService;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ApiUserProfileController extends AbstractRestfulController
{
    /** @var ConnectedUserService */
    private $connectedUserService;

    /** @var UserProfileService */
    private $userProfileService;

    public function __construct(UserProfileService $userProfileService, ConnectedUserService $connectedUserService)
    {
        $this->userProfileService = $userProfileService;
        $this->connectedUserService = $connectedUserService;
    }

    public function getList()
    {
        $connectedUser = $this->connectedUserService->getConnectedUser();

        // TODO: We need to use normalizer for the response data.
        return new JsonModel([
            'id' => $connectedUser->getId(),
            'firstname' => $connectedUser->getFirstname(),
            'lastname' => $connectedUser->getLastname(),
            'email' => $connectedUser->getEmail(),
            'status' => $connectedUser->getStatus(),
            'role' => $connectedUser->getRolesArray(),
            'mospApiKey' => $connectedUser->getMospApiKey(),
        ]);
    }

    public function patchList($data)
    {
        $this->userProfileService->updateMyData($data);

        return new JsonModel(['status' => 'ok']);
    }

    public function replaceList($data)
    {
        $this->userProfileService->updateMyData($data);

        return new JsonModel(['status' => 'ok']);
    }

    public function deleteList($data)
    {
        $this->userProfileService->deleteMe();

        $this->getResponse()->setStatusCode(204);

        return new JsonModel();
    }
}
