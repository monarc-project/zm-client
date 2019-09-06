<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Model\Entity\User;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\UserProfileService;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

/**
 * Api User Profile Controller
 *
 * Class ApiUserProfileController
 * @package Monarc\FrontOffice\Controller
 */
class ApiUserProfileController extends AbstractRestfulController
{
    /** @var User */
    private $connectedUser;

    /** @var UserProfileService */
    private $userProfileService;

    public function __construct(UserProfileService $userProfileService, ConnectedUserService $connectedUserService)
    {
        $this->userProfileService = $userProfileService;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $userData = $this->connectedUser->toArray();
        unset($userData['password']);

        // TODO: We need to use normalizer for the response fields filtering out.
        return new JsonModel($userData);
    }

    /**
     * @inheritdoc
     */
    public function patchList($data)
    {
        return new JsonModel($this->userProfileService->update($this->connectedUser->toArray(), $data));
    }

    /**
     * @inheritdoc
     */
    public function replaceList($data)
    {
        return new JsonModel($this->userProfileService->update($this->connectedUser->toArray(), $data));
    }

    /**
     * @inheritdoc
     */
    public function deleteList($id)
    {
        return new JsonModel($this->userProfileService->delete($this->connectedUser->getId()));
    }
}
