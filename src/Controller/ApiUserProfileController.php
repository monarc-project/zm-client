<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\UserProfileService;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

/**
 * Api User Profile Controller
 *
 * Class ApiUserProfileController
 * @package Monarc\FrontOffice\Controller
 */
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
        if ($connectedUser === null) {
            throw new Exception('You are not authorized to do this action', 412);
        }

        // TODO: We need to use normalizer for the response fields filtering out.
        return new JsonModel([
            'id' => $connectedUser->getId(),
            'firstname' => $connectedUser->getFirstname(),
            'lastname' => $connectedUser->getLastname(),
            'email' => $connectedUser->getEmail(),
            'status' => $connectedUser->getStatus(),
            'role' => $connectedUser->getRoles(),
            'isTwoFactorAuthEnabled' => $connectedUser->isTwoFactorAuthEnabled(),
            'remainingRecoveryCodes' => \count($connectedUser->getRecoveryCodes()),
            'mospApiKey' => $connectedUser->getMospApiKey(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function patchList($data)
    {
        $this->userProfileService->update($this->connectedUserService->getConnectedUser(), $data);

        // Replace to return the updated object.
        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function replaceList($data)
    {
        $this->userProfileService->update($this->connectedUserService->getConnectedUser(), $data);

        // Replace to return the updated object.
        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function deleteList($id)
    {
        $this->userProfileService->delete($this->connectedUserService->getConnectedUser());

        $this->getResponse()->setStatusCode(204);

        return new JsonModel();
    }
}
