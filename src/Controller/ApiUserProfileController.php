<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\UserProfileService;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ApiUserProfileController extends AbstractRestfulController
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private UserProfileService $userProfileService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList()
    {
        return new JsonModel([
            'id' => $this->connectedUser->getId(),
            'firstname' => $this->connectedUser->getFirstname(),
            'lastname' => $this->connectedUser->getLastname(),
            'email' => $this->connectedUser->getEmail(),
            'status' => $this->connectedUser->getStatus(),
            'role' => $this->connectedUser->getRolesArray(),
            'isTwoFactorAuthEnabled' => $this->connectedUser->isTwoFactorAuthEnabled(),
            'remainingRecoveryCodes' => \count($this->connectedUser->getRecoveryCodes()),
            'mospApiKey' => $this->connectedUser->getMospApiKey(),
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
