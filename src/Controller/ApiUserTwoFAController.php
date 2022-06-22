<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\UserService;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Model\Table\UserTable;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

/**
 * Api User TwoFA Controller
 *
 * Class ApiUserTwoFAController
 * @package Monarc\FrontOffice\Controller
 */
class ApiUserTwoFAController extends AbstractRestfulController
{
    /** @var ConnectedUserService */
    private $connectedUserService;

    /** @var UserService */
    private $userService;

    /** @var ConfigService */
    private $configService;

    /** @var UserTable */
    private $userTable;

    /** @var TwoFactorAuth */
    private $tfa;

    public function __construct(
        ConfigService $configService,
        UserService $userService,
        ConnectedUserService $connectedUserService,
        UserTable $userTable
    ) {
        $this->configService = $configService;
        $this->userService = $userService;
        $this->connectedUserService = $connectedUserService;
        $this->userTable = $userTable;
        $qr = new EndroidQrCodeProvider();
        $this->tfa = new TwoFactorAuth('MONARC', 6, 30, 'sha1', $qr);
    }

    /**
     * Generates a new secret key for the connected user.
     * Returns the secret key and the corresponding QRCOde.
     */
    public function get($id)
    {
        $connectedUser = $this->connectedUserService->getConnectedUser();
        if ($connectedUser === null) {
            throw new Exception('You are not authorized to do this action', 412);
        }

        // Create a new secret and generate a QRCode
        $label = 'MONARC';
        if ($this->configService->getInstanceName()) {
            $label .= ' ('. $this->configService->getInstanceName() .')';
        }
        $secret = $this->tfa->createSecret();
        $qrcode = $this->tfa->getQRCodeImageAsDataUri($label, $secret);

        return new JsonModel([
            'id' => $connectedUser->getId(),
            'secret' => $secret,
            'qrcode' => $qrcode,
        ]);
    }

    /**
     * Confirms the newly generated key with a token given by the user.
     * This is just good practice.
     */
    public function create($data)
    {
        $connectedUser = $this->connectedUserService->getConnectedUser();
        $res = $this->tfa->verifyCode($data['secretKey'], $data['verificationCode']);

        if ($res) {
            $connectedUser->setSecretKey($data['secretKey']);
            $connectedUser->setTwoFactorAuthEnabled(true);
            $this->userTable->saveEntity($connectedUser);
        }

        return new JsonModel([
            'status' => $res,
        ]);
    }

    /**
     * Disable the Two Factor Authentication for the connected user.
     * Also delete the secret key.
     */
    public function delete($id)
    {
        $connectedUser = $this->connectedUserService->getConnectedUser();
        if ($connectedUser === null) {
            throw new Exception('You are not authorized to do this action', 412);
        }

        $connectedUser->setTwoFactorAuthEnabled(false);
        $connectedUser->setSecretKey(null);
        $connectedUser->setRecoveryCodes(null);
        $this->userTable->saveEntity($connectedUser);

        $this->getResponse()->setStatusCode(204);

        return new JsonModel();
    }
}
